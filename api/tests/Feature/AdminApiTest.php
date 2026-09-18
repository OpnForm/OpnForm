<?php

use App\Jobs\AdminApi\ExecuteAdminAction;
use App\Models\AdminApiAction;
use App\Models\User;
use App\Service\AdminApi\ActionExecutor;
use App\Service\AdminApi\Operations;
use App\Service\AdminApi\State;
use App\Service\UserActionService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

function adminApiCredentials(array $abilities = []): array
{
    $actor = User::factory()->create();
    config(['opnform.moderator_emails' => [$actor->email], 'queue.default' => 'database']);
    $token = $actor->createToken('Bureau test', [Operations::TOKEN_MARKER, ...$abilities], now()->addDay());
    return [$actor, $token];
}

beforeEach(function () {
    Bus::fake([ExecuteAdminAction::class]);
    Mail::fake();
    Log::spy();
    Log::shouldReceive('channel')->andReturnSelf();
});

it('requires an explicit admin token and moderator role for reads', function () {
    $target = User::factory()->create();
    [$actor, $token] = adminApiCredentials(['admin:users:read']);
    $url = '/external/admin/v1/users/'.$target->id;
    $this->getJson($url)->assertUnauthorized();
    Auth::forgetGuards();
    $this->withToken($token->plainTextToken)->getJson($url)->assertOk()->assertJsonPath('id', $target->id)->assertJsonMissingPath('password')->assertJsonMissingPath('meta');
    config(['opnform.moderator_emails' => []]);
    Auth::forgetGuards();
    $this->withToken($token->plainTextToken)->getJson($url)->assertForbidden();
});

it('rejects public wildcard tokens and missing scopes', function () {
    $target = User::factory()->create();
    [$actor] = adminApiCredentials();
    foreach ([['*'], ['admin:users:read'], [Operations::TOKEN_MARKER, '*']] as $abilities) {
        $token = $actor->createToken('test', $abilities);
        Auth::forgetGuards();
        $this->withToken($token->plainTextToken)->getJson('/external/admin/v1/users/'.$target->id)->assertForbidden();
    }
});

it('rejects expired tokens and does not expose impersonation', function () {
    [$actor, $token] = adminApiCredentials(['admin:users:read']);
    $token->accessToken->update(['expires_at' => now()->subMinute()]);
    $this->withToken($token->plainTextToken)->getJson('/external/admin/v1/users/'.$actor->id)->assertUnauthorized();
    $this->getJson('/external/admin/v1/impersonate/'.$actor->id)->assertNotFound();
});

it('deduplicates actions durably and rejects conflicting idempotency content', function () {
    [$actor, $token] = adminApiCredentials(['admin:users:block']);
    $target = User::factory()->create();
    $id = (string) Str::uuid();
    $statusUrl = '/external/admin/v1/actions/'.$id.'?operation=block-user&user_id='.$target->id;
    $snapshot = $this->withToken($token->plainTextToken)->getJson($statusUrl)->assertOk()->assertJsonPath('status', 'not_started')->json('snapshot_hash');
    $body = ['action_id' => $id, 'user_id' => $target->id, 'reason' => 'Spam confirmed', 'expected_state' => $snapshot];
    $this->withHeader('Idempotency-Key', 'test-block-0001')->postJson('/external/admin/v1/actions/block-user', $body)->assertAccepted();
    $this->postJson('/external/admin/v1/actions/block-user', array_reverse($body, true))->assertAccepted();
    Bus::assertDispatchedTimes(ExecuteAdminAction::class, 1);
    $this->postJson('/external/admin/v1/actions/block-user', [...$body, 'reason' => 'Changed'])->assertConflict();
    app(ActionExecutor::class)->execute($id);
    expect($target->fresh()->is_blocked)->toBeTrue();
    expect(AdminApiAction::find($id)->status)->toBe('completed');
    $this->getJson($statusUrl)->assertOk()->assertJsonPath('status', 'completed');
    app(ActionExecutor::class)->execute($id);
    Mail::assertSentCount(1);
    $this->postJson('/external/admin/v1/actions/block-user', $body)->assertOk();
    expect(AdminApiAction::count())->toBe(1);
});

it('rejects stale state both before enqueueing and before execution', function () {
    [$actor, $token] = adminApiCredentials(['admin:users:block']);
    $target = User::factory()->create();
    $id = (string) Str::uuid();
    $body = ['action_id' => $id, 'user_id' => $target->id, 'reason' => 'Spam', 'expected_state' => str_repeat('0', 64)];
    $this->withToken($token->plainTextToken)->withHeader('Idempotency-Key', 'test-stale-0001')->postJson('/external/admin/v1/actions/block-user', $body)->assertConflict();
    $body['expected_state'] = app(State::class)->snapshot('block-user', $body)['snapshot_hash'];
    $this->postJson('/external/admin/v1/actions/block-user', $body)->assertAccepted();
    $target->update(['name' => 'Changed while awaiting execution']);
    app(ActionExecutor::class)->execute($id);
    expect(AdminApiAction::find($id)->status)->toBe('rejected');
    expect($target->fresh()->is_blocked)->toBeFalse();
    Mail::assertNothingSent();
});

it('rechecks token revocation in the worker', function () {
    [$actor, $token] = adminApiCredentials(['admin:users:block']);
    $target = User::factory()->create();
    $body = ['action_id' => (string) Str::uuid(), 'user_id' => $target->id, 'reason' => 'Spam'];
    $body['expected_state'] = app(State::class)->snapshot('block-user', $body)['snapshot_hash'];
    $this->withToken($token->plainTextToken)->withHeader('Idempotency-Key', 'test-revoke-0001')->postJson('/external/admin/v1/actions/block-user', $body)->assertAccepted();
    $token->accessToken->delete();
    app(ActionExecutor::class)->execute($body['action_id']);
    expect(AdminApiAction::find($body['action_id'])->status)->toBe('rejected');
    expect($target->fresh()->is_blocked)->toBeFalse();
});

it('never reruns a write with an uncertain outcome', function () {
    [$actor, $token] = adminApiCredentials(['admin:users:block']);
    $target = User::factory()->create();
    $body = ['action_id' => (string) Str::uuid(), 'user_id' => $target->id, 'reason' => 'Spam'];
    $body['expected_state'] = app(State::class)->snapshot('block-user', $body)['snapshot_hash'];
    $this->withToken($token->plainTextToken)->withHeader('Idempotency-Key', 'test-uncertain-0001')->postJson('/external/admin/v1/actions/block-user', $body)->assertAccepted();
    $service = Mockery::mock(UserActionService::class);
    $service->shouldReceive('block')->once()->andThrow(new RuntimeException('Provider connection interrupted'));
    app()->instance(UserActionService::class, $service);
    app(ActionExecutor::class)->execute($body['action_id']);
    app(ActionExecutor::class)->execute($body['action_id']);
    expect(AdminApiAction::find($body['action_id'])->status)->toBe('uncertain');
    $this->assertDatabaseHas('admin_api_action_locks', ['action_id' => $body['action_id']]);
});

it('rejects unknown fields and synchronous queues before recording a write', function () {
    [$actor, $token] = adminApiCredentials(['admin:users:clear-cache']);
    $target = User::factory()->create();
    $body = ['action_id' => (string) Str::uuid(), 'user_id' => $target->id];
    $body['expected_state'] = app(State::class)->snapshot('clear-user-cache', $body)['snapshot_hash'];
    $this->withToken($token->plainTextToken)->withHeader('Idempotency-Key', 'test-input-0001')->postJson('/external/admin/v1/actions/clear-user-cache', $body + ['unknown' => true])->assertUnprocessable();
    config(['queue.default' => 'sync']);
    $this->postJson('/external/admin/v1/actions/clear-user-cache', $body)->assertStatus(503);
    expect(AdminApiAction::count())->toBe(0);
});

it('cannot target administrator accounts', function () {
    [$actor, $token] = adminApiCredentials(['admin:users:block', 'admin:users:read']);
    $target = User::factory()->create();
    config(['opnform.admin_emails' => [$target->email]]);
    $this->withToken($token->plainTextToken)->getJson('/external/admin/v1/users/'.$target->id)->assertForbidden();
    $this->getJson('/external/admin/v1/actions/'.Str::uuid().'?operation=block-user&user_id='.$target->id)->assertForbidden();
});

it('dispatches every allowed action through its shared UI service', function (string $operation, array $parameters) {
    [$serviceClass, $method, $scope] = Operations::ACTIONS[$operation];
    [$actor, $token] = adminApiCredentials([$scope]);
    $target = User::factory()->create();
    $body = ['action_id' => (string) Str::uuid(), 'expected_state' => str_repeat('a', 64), ...$parameters];
    if (!in_array($operation, ['restore-form', 'create-template'], true)) {
        $body['user_id'] = $target->id;
    }
    if ($operation === 'extend-trial') {
        $body['trial_ends_at'] = now()->addDays(3)->toIso8601String();
    }
    $state = Mockery::mock(State::class);
    $state->shouldReceive('snapshot')->with($operation, Mockery::type('array'))->times($operation === 'refund-payment' ? 2 : 3)->andReturn(['snapshot_hash' => $body['expected_state'], 'target' => 'user:'.$target->id, 'state' => ['billing' => ['local_subscriptions' => [['stripe_id' => 'sub_test', 'stripe_status' => 'trialing']], 'discount_coupon_id' => 'coupon_test', 'latest_invoice' => ['id' => 'in_test', 'payment' => ['amount_paid' => 500, 'amount_refunded' => 0, 'currency' => 'usd']]]]]);
    $verification = Mockery::mock(\App\Service\AdminApi\RemoteVerification::class)->makePartial();
    $verification->shouldReceive('confirmed')->once()->andReturnTrue();
    app()->instance(\App\Service\AdminApi\RemoteVerification::class, $verification);
    if ($operation === 'refund-payment') {
        $state->shouldReceive('refundReceiptSnapshot')->once()->andReturn(['state' => []]);
    }
    app()->instance(State::class, $state);
    $service = Mockery::mock($serviceClass);
    $service->shouldReceive($method)->once()->andReturn(response()->json(['type' => 'success']));
    app()->instance($serviceClass, $service);
    $this->withToken($token->plainTextToken)->withHeader('Idempotency-Key', 'test-dispatch-'.$operation)
        ->postJson('/external/admin/v1/actions/'.$operation, $body)->assertAccepted();
    app(ActionExecutor::class)->execute($body['action_id']);
    expect(AdminApiAction::find($body['action_id'])->status)->toBe('completed');
})->with([
    ['block-user', ['reason' => 'Confirmed spam']],
    ['unblock-user', ['reason' => 'Appeal accepted']],
    ['send-password-reset-email', []],
    ['disable-two-factor-authentication', ['reason' => 'Identity verified']],
    ['clear-user-cache', []],
    ['apply-discount', []],
    ['extend-trial', []],
    ['cancel-subscription', ['subscription_id' => 10, 'cancellation_reason' => 'Requested']],
    ['refund-payment', ['invoice_id' => 'in_test', 'refund_reason' => 'Requested', 'expected_amount' => 500, 'expected_currency' => 'usd']],
    ['update-customer', ['billing_email' => 'billing@example.test', 'billing_name' => 'Example']],
    ['restore-form', ['slug' => 'test-form']],
    ['create-template', ['template_prompt' => 'Create a contact form']],
]);

it('does not let regular token issuance mint admin permissions', function () {
    $this->actingAsUser();
    $response = $this->postJson('/settings/tokens', ['name' => 'Escalation', 'abilities' => [Operations::TOKEN_MARKER, 'admin:users:block']])->assertOk();
    $token = \Laravel\Sanctum\PersonalAccessToken::findToken($response->json('token'));
    expect($token->abilities)->toBe([]);
});

it('refuses a second action while an uncertain write holds the same target', function () {
    [$actor, $token] = adminApiCredentials(['admin:users:clear-cache']);
    $target = User::factory()->create();
    $body = ['action_id' => (string) Str::uuid(), 'user_id' => $target->id];
    $body['expected_state'] = app(State::class)->snapshot('clear-user-cache', $body)['snapshot_hash'];
    \Illuminate\Support\Facades\DB::table('admin_api_action_locks')->insert(['target' => 'user:'.$target->id, 'action_id' => (string) Str::uuid()]);
    $this->withToken($token->plainTextToken)->withHeader('Idempotency-Key', 'test-lock-0001')->postJson('/external/admin/v1/actions/clear-user-cache', $body)->assertAccepted();
    app(ActionExecutor::class)->execute($body['action_id']);
    expect(AdminApiAction::find($body['action_id'])->status)->toBe('rejected');
});

it('supports deleted form reads with existing global user model bindings', function () {
    [$actor, $token] = adminApiCredentials(['admin:forms:read']);
    $target = User::factory()->create();
    $this->withToken($token->plainTextToken)->getJson('/external/admin/v1/users/'.$target->id.'/deleted-forms')->assertOk()->assertJsonPath('forms', []);
});

it('shows a pending verification when the post-action state no longer matches', function () {
    [$actor, $token] = adminApiCredentials(['admin:users:block']);
    $target = User::factory()->create();
    $body = ['action_id' => (string) Str::uuid(), 'user_id' => $target->id, 'reason' => 'Spam'];
    $body['expected_state'] = app(State::class)->snapshot('block-user', $body)['snapshot_hash'];
    $this->withToken($token->plainTextToken)->withHeader('Idempotency-Key', 'test-verify-0001')->postJson('/external/admin/v1/actions/block-user', $body)->assertAccepted();
    app(ActionExecutor::class)->execute($body['action_id']);
    $target->update(['blocked_at' => null]);
    $this->getJson('/external/admin/v1/actions/'.$body['action_id'].'?operation=block-user&user_id='.$target->id)->assertOk()->assertJsonPath('status', 'verification_pending');
});

it('issues only explicit expiring moderator tokens from the operator command', function () {
    $owner = User::factory()->create();
    config(['opnform.moderator_emails' => [$owner->email]]);
    $this->artisan('admin-api:token', ['owner' => $owner->email, '--ability' => ['*']])->assertFailed();
    $this->artisan('admin-api:token', ['owner' => $owner->email, '--ability' => ['admin:users:read'], '--days' => 91])->assertFailed();
    $this->artisan('admin-api:token', ['owner' => $owner->email, '--ability' => ['admin:users:read'], '--days' => 7])->assertSuccessful();
    $token = $owner->tokens()->sole();
    expect($token->abilities)->toBe([Operations::TOKEN_MARKER, 'admin:users:read']);
    expect($token->expires_at->isFuture())->toBeTrue();
    config(['opnform.moderator_emails' => []]);
    $this->artisan('admin-api:token', ['owner' => $owner->email, '--revoke' => $token->id])->assertSuccessful();
    expect($owner->tokens()->count())->toBe(0);
});

it('rejects a job after its moderator role is removed', function () {
    [$actor, $token] = adminApiCredentials(['admin:users:clear-cache']);
    $target = User::factory()->create();
    $body = ['action_id' => (string) Str::uuid(), 'user_id' => $target->id];
    $body['expected_state'] = app(State::class)->snapshot('clear-user-cache', $body)['snapshot_hash'];
    $this->withToken($token->plainTextToken)->withHeader('Idempotency-Key', 'test-role-0001')->postJson('/external/admin/v1/actions/clear-user-cache', $body)->assertAccepted();
    config(['opnform.moderator_emails' => []]);
    app(ActionExecutor::class)->execute($body['action_id']);
    expect(AdminApiAction::find($body['action_id'])->status)->toBe('rejected');
});

it('redacts persisted and exposed action data', function () {
    [$actor, $token] = adminApiCredentials(['admin:users:block']);
    $target = User::factory()->create();
    $body = ['action_id' => (string) Str::uuid(), 'user_id' => $target->id, 'reason' => 'Private support case details'];
    $body['expected_state'] = app(State::class)->snapshot('block-user', $body)['snapshot_hash'];
    $this->withToken($token->plainTextToken)->withHeader('Idempotency-Key', 'private-key-0001')->postJson('/external/admin/v1/actions/block-user', $body)->assertAccepted();
    $action = AdminApiAction::find($body['action_id']);
    expect($action->getRawOriginal('payload'))->not->toContain($body['reason']);
    expect($action->toArray())->not->toHaveKeys(['payload', 'request_hash', 'idempotency_hash']);
    $this->getJson('/external/admin/v1/actions/'.$body['action_id'].'?operation=block-user&user_id='.$target->id)
        ->assertOk()->assertJsonMissingPath('payload')->assertJsonMissingPath('idempotency_hash');
});

it('records password reset acceptance without claiming delivery', function () {
    [$actor, $token] = adminApiCredentials(['admin:users:password-reset']);
    $target = User::factory()->create();
    \Illuminate\Support\Facades\Password::shouldReceive('sendResetLink')->once()->with(['email' => $target->email])->andReturn(\Illuminate\Support\Facades\Password::RESET_LINK_SENT);
    $body = ['action_id' => (string) Str::uuid(), 'user_id' => $target->id];
    $body['expected_state'] = app(State::class)->snapshot('send-password-reset-email', $body)['snapshot_hash'];
    $this->withToken($token->plainTextToken)->withHeader('Idempotency-Key', 'test-password-0001')->postJson('/external/admin/v1/actions/send-password-reset-email', $body)->assertAccepted();
    app(ActionExecutor::class)->execute($body['action_id']);
    $this->getJson('/external/admin/v1/actions/'.$body['action_id'].'?operation=send-password-reset-email&user_id='.$target->id)
        ->assertOk()->assertJsonPath('status', 'completed')->assertJsonPath('result.delivery', 'accepted_by_mailer');
});

it('restores a deleted form and verifies its live state', function () {
    [$actor, $token] = adminApiCredentials(['admin:forms:restore']);
    $target = User::factory()->create();
    $workspace = $this->createUserWorkspace($target);
    $form = $this->createForm($target, $workspace);
    $form->delete();
    $body = ['action_id' => (string) Str::uuid(), 'slug' => $form->slug];
    $body['expected_state'] = app(State::class)->snapshot('restore-form', $body)['snapshot_hash'];
    $this->withToken($token->plainTextToken)->withHeader('Idempotency-Key', 'test-restore-0001')->postJson('/external/admin/v1/actions/restore-form', $body)->assertAccepted();
    app(ActionExecutor::class)->execute($body['action_id']);
    $this->getJson('/external/admin/v1/actions/'.$body['action_id'].'?operation=restore-form&slug='.$form->slug)
        ->assertOk()->assertJsonPath('status', 'completed')->assertJsonPath('state.deleted_at', null);
});

it('refunds through the shared service with a provider key and verifies the remote receipt', function () {
    $this->withoutMiddleware(\App\Http\Middleware\AdminApiRemoteTimeout::class);
    [$actor, $token] = adminApiCredentials(['admin:billing:refund']);
    $target = User::factory()->create(['stripe_id' => 'cus_test']);
    $refundRequests = [];
    $invoiceReadsForbidden = false;
    $fake = Mockery::mock(\Stripe\HttpClient\ClientInterface::class);
    $fake->shouldReceive('request')->andReturnUsing(function ($method, $url, $headers, $params) use (&$refundRequests, &$invoiceReadsForbidden) {
        if ($invoiceReadsForbidden && str_contains($url, '/invoices')) {
            throw new RuntimeException('Current invoice unavailable');
        }
        expect(implode(' ', $headers))->toContain('Stripe-Version: 2025-08-27.basil');
        $invoice = ['object' => 'invoice', 'id' => 'in_test', 'customer' => 'cus_test', 'status' => 'paid', 'paid' => true, 'amount_paid' => 500, 'currency' => 'usd', 'created' => 1700000000];
        $data = match (parse_url($url, PHP_URL_PATH)) {
            '/v1/customers/cus_test' => ['object' => 'customer', 'id' => 'cus_test', 'name' => 'Test', 'email' => 'billing@example.test'],
            '/v1/subscriptions' => ['object' => 'list', 'data' => [], 'has_more' => false],
            '/v1/invoices' => ['object' => 'list', 'data' => [$invoice], 'has_more' => false],
            '/v1/invoice_payments' => ['object' => 'list', 'has_more' => false, 'data' => [[
                'object' => 'invoice_payment', 'id' => 'inpay_test', 'invoice' => 'in_test', 'amount_paid' => 500, 'status' => 'paid',
                'payment' => ['type' => 'payment_intent', 'payment_intent' => ['object' => 'payment_intent', 'id' => 'pi_test',
                    'latest_charge' => ['object' => 'charge', 'id' => 'ch_test', 'customer' => 'cus_test', 'paid' => true, 'amount' => 500,
                        'currency' => 'usd', 'amount_refunded' => count($refundRequests) ? 500 : 0]]],
            ]]],
            '/v1/refunds', '/v1/refunds/re_test' => ['object' => 'refund', 'id' => 're_test', 'status' => 'succeeded', 'charge' => 'ch_test', 'amount' => 500, 'currency' => 'usd'],
            default => throw new RuntimeException('Unexpected Stripe path'),
        };
        if ($method === 'post' && str_ends_with($url, '/refunds')) {
            $refundRequests[] = ['headers' => $headers, 'params' => $params];
        }
        return [json_encode($data), 200, []];
    });
    $originalClient = \Stripe\ApiRequestor::httpClient();
    \Stripe\ApiRequestor::setHttpClient($fake);
    try {
        $body = ['action_id' => (string) Str::uuid(), 'user_id' => $target->id, 'invoice_id' => 'in_test', 'refund_reason' => 'Customer request', 'expected_amount' => 500, 'expected_currency' => 'usd'];
        $body['expected_state'] = app(State::class)->snapshot('refund-payment', $body)['snapshot_hash'];
        $this->withToken($token->plainTextToken)->withHeader('Idempotency-Key', 'test-refund-0001')->postJson('/external/admin/v1/actions/refund-payment', $body)->assertAccepted();
        app(ActionExecutor::class)->execute($body['action_id']);
        app(ActionExecutor::class)->execute($body['action_id']);
        expect(AdminApiAction::find($body['action_id'])->status)->toBe('completed');
        expect($refundRequests)->toHaveCount(1);
        expect(implode(' ', $refundRequests[0]['headers']))->toContain('admin-api:'.$body['action_id']);
        expect($refundRequests[0]['params']['charge'])->toBe('ch_test');
        $invoiceReadsForbidden = true;
        $this->getJson('/external/admin/v1/actions/'.$body['action_id'].'?operation=refund-payment&user_id='.$target->id)
            ->assertOk()->assertJsonPath('status', 'completed')->assertJsonPath('result.refund_id', 're_test');
    } finally {
        \Stripe\ApiRequestor::setHttpClient($originalClient);
    }
});

it('keeps the target locked until read-back succeeds and reconciles without replay', function () {
    [$actor, $token] = adminApiCredentials(['admin:users:block']);
    $target = User::factory()->create();
    $body = ['action_id' => (string) Str::uuid(), 'user_id' => $target->id, 'reason' => 'Spam'];
    $body['expected_state'] = app(State::class)->snapshot('block-user', $body)['snapshot_hash'];
    $this->withToken($token->plainTextToken)->withHeader('Idempotency-Key', 'verify-later-0001')->postJson('/external/admin/v1/actions/block-user', $body)->assertAccepted();
    $verifier = Mockery::mock(\App\Service\AdminApi\RemoteVerification::class)->makePartial();
    $verifier->shouldReceive('confirmed')->twice()->andReturn(false, true);
    app()->instance(\App\Service\AdminApi\RemoteVerification::class, $verifier);
    app(ActionExecutor::class)->execute($body['action_id']);
    expect(AdminApiAction::find($body['action_id'])->status)->toBe('verifying');
    $this->assertDatabaseHas('admin_api_action_locks', ['action_id' => $body['action_id']]);
    app(ActionExecutor::class)->execute($body['action_id']);
    $this->getJson('/external/admin/v1/actions/'.$body['action_id'].'?operation=block-user&user_id='.$target->id)
        ->assertOk()->assertJsonPath('status', 'completed');
    $this->assertDatabaseMissing('admin_api_action_locks', ['action_id' => $body['action_id']]);
    Mail::assertSentCount(1);
});

it('repairs form effects on an already blocked account and keeps snapshots bounded', function () {
    [$actor] = adminApiCredentials();
    $target = User::factory()->create(['blocked_at' => now()]);
    $workspace = $this->createUserWorkspace($target);
    $form = $this->createForm($target, $workspace, ['visibility' => 'public', 'tags' => ['private-tag']]);
    $snapshot = app(State::class)->snapshot('block-user', ['user_id' => $target->id]);
    expect($snapshot['state']['forms']['non_draft_count'])->toBe(1);
    expect(json_encode($snapshot))->not->toContain('private-tag');
    app(UserActionService::class)->block($target, 'Complete moderation', $actor->id);
    expect($form->fresh()->visibility)->toBe('draft');
    expect(app(State::class)->snapshot('block-user', ['user_id' => $target->id])['state']['forms']['non_draft_count'])->toBe(0);
});

it('requires finite expiration even for an explicitly scoped token', function () {
    [$actor] = adminApiCredentials();
    $target = User::factory()->create();
    $token = $actor->createToken('unbounded', [Operations::TOKEN_MARKER, 'admin:users:read']);
    $this->withToken($token->plainTextToken)->getJson('/external/admin/v1/users/'.$target->id)->assertForbidden();
});

it('requires the exact refund amount and currency before any write is queued', function () {
    [, $token] = adminApiCredentials(['admin:billing:refund']);
    $target = User::factory()->create();
    $body = ['action_id' => (string) Str::uuid(), 'user_id' => $target->id, 'invoice_id' => 'in_test', 'refund_reason' => 'Requested', 'expected_state' => str_repeat('a', 64)];
    $this->withToken($token->plainTextToken)->withHeader('Idempotency-Key', 'refund-amount-0001')
        ->postJson('/external/admin/v1/actions/refund-payment', $body)->assertUnprocessable()->assertJsonValidationErrors(['expected_amount', 'expected_currency']);
    $state = Mockery::mock(State::class);
    $state->shouldReceive('snapshot')->twice()->andReturn(['target' => 'user:'.$target->id, 'snapshot_hash' => $body['expected_state'], 'state' => ['billing' => ['local_subscriptions' => [['stripe_id' => 'sub_test', 'stripe_status' => 'trialing']], 'discount_coupon_id' => 'coupon_test', 'latest_invoice' => ['id' => 'in_test', 'payment' => ['amount_paid' => 500, 'amount_refunded' => 100, 'currency' => 'usd']]]]]);
    app()->instance(State::class, $state);
    $this->postJson('/external/admin/v1/actions/refund-payment', $body + ['expected_amount' => 500, 'expected_currency' => 'usd'])->assertConflict();
    $this->postJson('/external/admin/v1/actions/refund-payment', $body + ['expected_amount' => 400, 'expected_currency' => 'eur'])->assertConflict();
    Bus::assertNotDispatched(ExecuteAdminAction::class);
});

it('refuses refunding a payment whose charge also covers another invoice', function () {
    $target = User::factory()->create(['stripe_id' => 'cus_test']);
    $fake = Mockery::mock(\Stripe\HttpClient\ClientInterface::class);
    $fake->shouldReceive('request')->once()->andReturn([json_encode(['object' => 'list', 'has_more' => false, 'data' => [[
        'object' => 'invoice_payment', 'id' => 'inpay_test', 'amount_paid' => 500,
        'payment' => ['type' => 'charge', 'charge' => ['object' => 'charge', 'id' => 'ch_test', 'customer' => 'cus_test', 'paid' => true, 'amount' => 1000, 'amount_refunded' => 0, 'currency' => 'usd']],
    ]]]), 200, []]);
    $previous = \Stripe\ApiRequestor::httpClient();
    \Stripe\ApiRequestor::setHttpClient($fake);
    try {
        $invoice = \Stripe\Invoice::constructFrom(['id' => 'in_test', 'customer' => 'cus_test', 'amount_paid' => 500]);
        app(\App\Service\Admin\AdminStripeState::class)->refundablePayment($target, $invoice);
        $this->fail('Shared payment should be rejected.');
    } catch (\Symfony\Component\HttpKernel\Exception\HttpException $exception) {
        expect($exception->getStatusCode())->toBe(422);
    } finally {
        \Stripe\ApiRequestor::setHttpClient($previous);
    }
});

it('rejects an expired trial deadline before effects and releases any reservation', function () {
    [$actor, $token] = adminApiCredentials(['admin:billing:extend-trial']);
    $target = User::factory()->create();
    $action = AdminApiAction::create(['id' => (string) Str::uuid(), 'actor_id' => $actor->id, 'token_id' => $token->accessToken->id,
        'operation' => 'extend-trial', 'target' => 'user:'.$target->id, 'idempotency_hash' => hash('sha256', 'expired'), 'request_hash' => hash('sha256', 'payload'),
        'status' => 'queued', 'payload' => ['user_id' => $target->id, 'trial_ends_at' => now()->subMinute()->toIso8601String(), 'expected_state' => str_repeat('a', 64)]]);
    app(ActionExecutor::class)->execute($action->id);
    expect($action->fresh()->status)->toBe('rejected');
    $this->assertDatabaseMissing('admin_api_action_locks', ['action_id' => $action->id]);
});

it('applies and reads discounts using the installed Stripe Basil contract', function () {
    [$actor] = adminApiCredentials();
    $target = User::factory()->create(['stripe_id' => 'cus_test']);
    $target->subscriptions()->create(['type' => 'default', 'stripe_id' => 'sub_test', 'stripe_status' => 'active']);
    config(['pricing.discount_coupon_id' => 'coupon_test']);
    $fake = Mockery::mock(\Stripe\HttpClient\ClientInterface::class);
    $fake->shouldReceive('request')->twice()->andReturnUsing(function ($method, $url, $headers, $params) {
        expect(implode(' ', $headers))->toContain('Stripe-Version: 2025-08-27.basil');
        $subscription = ['object' => 'subscription', 'id' => 'sub_test', 'status' => 'active', 'trial_end' => null, 'cancel_at_period_end' => false,
            'discounts' => [['object' => 'discount', 'id' => 'di_test', 'coupon' => ['object' => 'coupon', 'id' => 'coupon_test']]]];
        if ($method === 'post') {
            expect($params['discounts'])->toBe([['coupon' => 'coupon_test']]);
            expect($params)->not->toHaveKey('coupon');
            return [json_encode($subscription), 200, []];
        }
        expect($params['expand'])->toBe(['data.discounts']);
        return [json_encode(['object' => 'list', 'has_more' => false, 'data' => [$subscription]]), 200, []];
    });
    $previous = \Stripe\ApiRequestor::httpClient();
    \Stripe\ApiRequestor::setHttpClient($fake);
    $originalRequest = app('request');
    Auth::setUser($actor);
    $request = \Illuminate\Http\Request::create('/', 'POST', ['user_id' => $target->id]);
    $request->setUserResolver(fn () => $actor);
    app()->instance('request', $request);
    try {
        expect(app(\App\Service\Admin\AdminOperations::class)->applyDiscount($request)->getStatusCode())->toBe(200);
        $subscriptions = app(\App\Service\Admin\AdminStripeState::class)->subscriptions($target);
        expect($subscriptions[0]['discounts'])->toBe(['coupon_test']);
    } finally {
        \Stripe\ApiRequestor::setHttpClient($previous);
        app()->instance('request', $originalRequest);
    }
});

it('never verifies a trial or discount against another subscription', function () {
    $verifier = app(\App\Service\AdminApi\RemoteVerification::class);
    $until = now()->addDays(3)->toIso8601String();
    $snapshot = ['state' => ['billing' => ['discount_coupon_id' => 'coupon_test', 'subscriptions' => [
        ['id' => 'sub_other', 'status' => 'trialing', 'discounts' => ['coupon_test'], 'trial_end' => \Carbon\Carbon::parse($until)->timestamp],
        ['id' => 'sub_target', 'status' => 'trialing', 'discounts' => [], 'trial_end' => null],
    ]]]];
    $action = new AdminApiAction(['operation' => 'extend-trial', 'payload' => ['trial_ends_at' => $until], 'result' => ['subscription_id' => 'sub_target', 'coupon_id' => 'coupon_test']]);
    expect($verifier->confirmed($action, $snapshot))->toBeFalse();
    $action->operation = 'apply-discount';
    expect($verifier->confirmed($action, $snapshot))->toBeFalse();
});
