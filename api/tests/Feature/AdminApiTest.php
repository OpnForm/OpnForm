<?php

use App\Models\AdminApiAction;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

function adminApiCredentials(array $scopes = []): array
{
    $actor = User::factory()->create();
    config(['opnform.moderator_emails' => [$actor->email], 'app.self_hosted' => false]);
    return [$actor, $actor->createToken('Support', $scopes, now()->addDay())];
}

beforeEach(function () {
    Mail::fake();
    Log::spy();
    Log::shouldReceive('channel')->andReturnSelf();
});

it('requires cloud and an explicit live moderator PAT scope', function () {
    $target = User::factory()->create();
    [$actor, $token] = adminApiCredentials(['admin:users:read']);
    $url = '/external/admin/v1/users/'.$target->id;
    $this->getJson($url)->assertUnauthorized();
    Auth::forgetGuards();
    $this->withToken($token->plainTextToken)->getJson($url)->assertOk()->assertJsonPath('user.id', $target->id)->assertJsonMissingPath('user.meta');
    foreach ([['*'], ['forms-read']] as $scopes) {
        Auth::forgetGuards();
        $this->withToken($actor->createToken('legacy', $scopes, now()->addDay())->plainTextToken)->getJson($url)->assertForbidden();
    }
    Auth::forgetGuards();
    config(['opnform.moderator_emails' => []]);
    $this->withToken($token->plainTextToken)->getJson($url)->assertForbidden();
    config(['app.self_hosted' => true]);
    $this->getJson($url)->assertNotFound();
});

it('executes once and binds receipt to exact content key actor token and operation', function () {
    [$actor, $token] = adminApiCredentials(['admin:users:block', 'admin:users:unblock']);
    $user = User::factory()->create();
    $id = (string) Str::uuid();
    $read = '/external/admin/v1/actions/block-user/'.$id.'?'.http_build_query(['user_id' => $user->id, 'reason' => 'Confirmed spam']);
    $body = ['action_id' => $id, 'user_id' => $user->id, 'reason' => 'Confirmed spam'];
    $this->withToken($token->plainTextToken)->getJson($read)->assertOk()->assertJsonPath('status', 'not_started');
    $this->withHeader('Idempotency-Key', 'block-request-1')->postJson('/external/admin/v1/actions/block-user', $body)->assertOk()->assertJsonPath('status', 'completed');
    expect($user->fresh()->is_blocked)->toBeTrue();
    $this->postJson('/external/admin/v1/actions/block-user', array_reverse($body, true))->assertOk();
    Mail::assertSentCount(1);
    $this->getJson($read)->assertOk()->assertJsonPath('status', 'completed');
    $this->getJson(str_replace('Confirmed+spam', 'Other+reason', $read))->assertConflict();
    $this->getJson(str_replace('user_id='.$user->id, 'user_id=99999', $read))->assertConflict();
    $this->getJson('/external/admin/v1/actions/unblock-user/'.$id.'?'.http_build_query(['user_id' => $user->id, 'reason' => 'Confirmed spam']))->assertNotFound();
    $this->postJson('/external/admin/v1/actions/block-user', [...$body, 'reason' => 'changed'])->assertConflict();
    $this->withHeader('Idempotency-Key', 'another-key-1')->postJson('/external/admin/v1/actions/block-user', $body)->assertConflict();
    Auth::forgetGuards();
    $this->withToken($actor->createToken('other', ['admin:users:block'], now()->addDay())->plainTextToken)->getJson($read)->assertNotFound();
    expect(AdminApiAction::count())->toBe(1);
});

it('refuses invalid payloads before receipt and never exposes impersonation', function () {
    [$actor, $token] = adminApiCredentials(['admin:users:block']);
    $this->withToken($token->plainTextToken)->withHeader('Idempotency-Key', 'bad-body-123')->postJson('/external/admin/v1/actions/block-user', ['action_id' => (string) Str::uuid(), 'user_id' => $actor->id, 'reason' => 'reason', 'extra' => 'no'])->assertUnprocessable();
    expect(AdminApiAction::count())->toBe(0);
    $this->postJson('/external/admin/v1/actions/impersonate', [])->assertNotFound();
});

it('keeps uncertain requests non replayable', function () {
    [$actor, $token] = adminApiCredentials(['admin:users:clear-cache']);
    $user = User::factory()->create();
    $id = (string) Str::uuid();
    $body = ['action_id' => $id, 'user_id' => $user->id];
    $this->withToken($token->plainTextToken)->withHeader('Idempotency-Key', 'cache-key-123')->postJson('/external/admin/v1/actions/clear-user-cache', $body)->assertOk();
    AdminApiAction::find($id)->update(['status' => 'uncertain']);
    $this->postJson('/external/admin/v1/actions/clear-user-cache', $body)->assertConflict()->assertJsonPath('status', 'uncertain');
});

it('admin token settings require cloud moderator and expiration', function () {
    $user = $this->actingAsUser();
    $body = ['name' => 'Bureau', 'abilities' => ['admin:users:block'], 'expires_at' => now()->addDay()->toIso8601String()];
    $this->postJson(route('settings.tokens.store'), $body)->assertForbidden();
    config(['opnform.moderator_emails' => [$user->email]]);
    $this->postJson(route('settings.tokens.store'), [...$body, 'expires_at' => null])->assertUnprocessable();
    $this->postJson(route('settings.tokens.store'), $body)->assertOk();
    expect($user->tokens()->latest('id')->first()->abilities)->toBe(['admin:users:block']);
    $this->getJson(route('settings.tokens.index'))->assertOk()->assertJsonStructure([['expires_at']]);
    config(['app.self_hosted' => true]);
    $this->postJson(route('settings.tokens.store'), $body)->assertForbidden();
    $this->getJson(route('settings.tokens.abilities'))->assertExactJson([]);
});

it('refunds through the shared service with a provider key and verifies the remote receipt', function ($refundStatus) {
    [$actor, $token] = adminApiCredentials(['admin:billing:refund']);
    $target = User::factory()->create(['stripe_id' => 'cus_test']);
    $refundRequests = [];
    $invoiceReadsForbidden = false;
    $fake = Mockery::mock(\Stripe\HttpClient\ClientInterface::class);
    $fake->shouldReceive('request')->andReturnUsing(function ($method, $url, $headers, $params) use (&$refundRequests, &$invoiceReadsForbidden, $refundStatus) {
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
            '/v1/refunds', '/v1/refunds/re_test' => ['object' => 'refund', 'id' => 're_test', 'status' => $refundStatus, 'charge' => 'ch_test', 'amount' => 500, 'currency' => 'usd'],
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
        $this->withToken($token->plainTextToken)->withHeader('Idempotency-Key', 'test-refund-0001')->postJson('/external/admin/v1/actions/refund-payment', $body)->assertStatus($refundStatus === 'succeeded' ? 200 : 500);
        $this->postJson('/external/admin/v1/actions/refund-payment', $body)->assertStatus($refundStatus === 'succeeded' ? 200 : 409);
        $this->postJson('/external/admin/v1/actions/refund-payment', $body)->assertStatus($refundStatus === 'succeeded' ? 200 : 409);
        expect(AdminApiAction::find($body['action_id'])->status)->toBe($refundStatus === 'succeeded' ? 'completed' : 'uncertain');
        expect($refundRequests)->toHaveCount(1);
        expect(implode(' ', $refundRequests[0]['headers']))->toContain('admin-api:'.$body['action_id']);
        expect($refundRequests[0]['params']['charge'])->toBe('ch_test');
        $invoiceReadsForbidden = true;
        $this->getJson('/external/admin/v1/actions/refund-payment/'.$body['action_id'].'?'.http_build_query(\Illuminate\Support\Arr::except($body, 'action_id')))
            ->assertOk()->assertJsonPath('status', $refundStatus === 'succeeded' ? 'completed' : 'uncertain')->assertJsonPath('result.refund_id', 're_test');
    } finally {
        \Stripe\ApiRequestor::setHttpClient($originalClient);
    }
})->with(['succeeded', 'pending']);

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
        app(\App\Service\Admin\AdminStripe::class)->refundablePayment($target, $invoice);
        $this->fail('Shared payment should be rejected.');
    } catch (\Symfony\Component\HttpKernel\Exception\HttpException $exception) {
        expect($exception->getStatusCode())->toBe(422);
    } finally {
        \Stripe\ApiRequestor::setHttpClient($previous);
    }
});


it('validates ownership before restoring and restores once', function () {
    [, $token] = adminApiCredentials(['admin:forms:restore']);
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $form = $this->createForm($owner, $this->createUserWorkspace($owner));
    $form->delete();
    $body = ['action_id' => (string) Str::uuid(), 'user_id' => $other->id, 'slug' => $form->slug];
    $this->withToken($token->plainTextToken)->withHeader('Idempotency-Key', 'restore-wrong-1')->postJson('/external/admin/v1/actions/restore-form', $body)->assertNotFound();
    expect($form->fresh()->deleted_at)->not->toBeNull();
    $body['action_id'] = (string) Str::uuid();
    $body['user_id'] = $owner->id;
    $this->withHeader('Idempotency-Key', 'restore-right-1')->postJson('/external/admin/v1/actions/restore-form', $body)->assertOk();
    expect($form->fresh()->deleted_at)->toBeNull();
});

it('queues only the template job and rechecks token revocation before generation', function () {
    \Illuminate\Support\Facades\Bus::fake([\App\Jobs\Template\GenerateTemplateJob::class]);
    [, $token] = adminApiCredentials(['admin:templates:create']);
    config(['queue.default' => 'database']);
    $id = (string) Str::uuid();
    $this->withToken($token->plainTextToken)->withHeader('Idempotency-Key', 'template-key-1')->postJson('/external/admin/v1/actions/create-template', ['action_id' => $id, 'template_prompt' => 'Contact template'])->assertAccepted()->assertJsonPath('status', 'running');
    $token->accessToken->delete();
    \Illuminate\Support\Facades\Bus::assertDispatched(\App\Jobs\Template\GenerateTemplateJob::class, function ($job) {
        $job->handle();
        return true;
    });
    expect(AdminApiAction::find($id)->status)->toBe('uncertain');
});

it('restores Stripe settings even on errors and preserves injected clients', function () {
    $original = \Stripe\ApiRequestor::httpClient();
    $client = new \Stripe\HttpClient\CurlClient();
    $client->setTimeout(45);
    $client->setConnectTimeout(10);
    \Stripe\ApiRequestor::setHttpClient($client);
    \Stripe\Stripe::setMaxNetworkRetries(2);
    try {
        try {
            \App\Service\Admin\AdminStripe::withTimeout(function () use ($client) {
                expect($client->getTimeout())->toBe(4);
                expect(\Stripe\Stripe::getMaxNetworkRetries())->toBe(0);
                throw new RuntimeException('test');
            });
        } catch (RuntimeException) {
        }
        expect($client->getTimeout())->toBe(45)->and($client->getConnectTimeout())->toBe(10)->and(\Stripe\Stripe::getMaxNetworkRetries())->toBe(2);
        $fake = Mockery::mock(\Stripe\HttpClient\ClientInterface::class);
        \Stripe\ApiRequestor::setHttpClient($fake);
        \App\Service\Admin\AdminStripe::withTimeout(fn () => expect(\Stripe\ApiRequestor::httpClient())->toBe($fake));
    } finally {
        \Stripe\ApiRequestor::setHttpClient($original);
        \Stripe\Stripe::setMaxNetworkRetries(0);
    }
});

it('allows public token creation with omitted or null abilities', function () {
    $this->actingAsUser();
    $this->postJson(route('settings.tokens.store'), ['name' => 'empty'])->assertOk();
    $this->postJson(route('settings.tokens.store'), ['name' => 'empty', 'abilities' => null])->assertOk();
});

it('rejects revoked expired blocked and non PAT authentication', function () {
    [$actor, $token] = adminApiCredentials(['admin:users:read']);
    $target = User::factory()->create();
    $url = '/external/admin/v1/users/'.$target->id;
    $actor->update(['blocked_at' => now()]);
    $this->withToken($token->plainTextToken)->getJson($url)->assertForbidden();
    $actor->update(['blocked_at' => null]);
    $token->accessToken->update(['expires_at' => now()->subMinute()]);
    Auth::forgetGuards();
    $this->getJson($url)->assertUnauthorized();
    $token->accessToken->delete();
    Auth::forgetGuards();
    $this->getJson($url)->assertUnauthorized();
    \Laravel\Sanctum\Sanctum::actingAs($actor, ['admin:users:read']);
    $this->getJson($url)->assertUnauthorized();
});

it('routes explicit actions to their existing admin services', function ($operation, $method, $scope, $parameters, $class) {
    [, $token] = adminApiCredentials([$scope]);
    $user = User::factory()->create();
    $service = Mockery::mock($class);
    $service->shouldReceive($method)->once()->andReturn(response()->json(['type' => 'success']));
    app()->instance($class, $service);
    $body = ['action_id' => (string) Str::uuid(), ...($operation === 'create-template' ? [] : ['user_id' => $user->id]), ...$parameters];
    $this->withToken($token->plainTextToken)->withHeader('Idempotency-Key', 'dispatch-'.$operation)->postJson('/external/admin/v1/actions/'.$operation, $body)->assertOk()->assertJsonPath('status', 'completed');
})->with([
    ['block-user', 'blockUser', 'admin:users:block', ['reason' => 'Spam'], \App\Service\Admin\AdminOperations::class],
    ['unblock-user', 'unblockUser', 'admin:users:unblock', ['reason' => 'Reviewed'], \App\Service\Admin\AdminOperations::class],
    ['send-password-reset-email', 'sendPasswordResetEmail', 'admin:users:password-reset', [], \App\Service\Admin\AdminOperations::class],
    ['disable-two-factor-authentication', 'disableTwoFactorAuthentication', 'admin:users:disable-2fa', ['reason' => 'Confirmed'], \App\Service\Admin\AdminOperations::class],
    ['clear-user-cache', 'clearUserCache', 'admin:users:clear-cache', [], \App\Service\Admin\AdminOperations::class],
    ['apply-discount', 'applyDiscount', 'admin:billing:discount', [], \App\Service\Admin\AdminOperations::class],
    ['extend-trial', 'extendTrial', 'admin:billing:extend-trial', ['trial_ends_at' => '2030-01-01T00:00:00Z'], \App\Service\Admin\AdminOperations::class],
    ['cancel-subscription', 'cancelSubscription', 'admin:billing:cancel', ['subscription_id' => 1, 'cancellation_reason' => 'Requested'], \App\Service\Admin\AdminOperations::class],
    ['refund-payment', 'refundPayment', 'admin:billing:refund', ['invoice_id' => 'in_test', 'refund_reason' => 'Requested', 'expected_amount' => 100, 'expected_currency' => 'usd'], \App\Service\Admin\AdminOperations::class],
    ['update-customer', 'updateCustomer', 'admin:billing:update', ['billing_email' => 'test@example.test', 'billing_name' => 'Name'], \App\Service\Admin\AdminBilling::class],
    ['restore-form', 'restoreDeletedForm', 'admin:forms:restore', ['slug' => 'form'], \App\Service\Admin\AdminForms::class],
    ['create-template', 'createTemplate', 'admin:templates:create', ['template_prompt' => 'Contact form'], \App\Service\Admin\AdminOperations::class],
]);

it('routes explicit reads to existing services with their scope', function ($path, $method, $scope, $class) {
    [, $token] = adminApiCredentials([$scope]);
    $user = User::factory()->create();
    $service = Mockery::mock($class);
    $service->shouldReceive($method)->once()->andReturn(response()->json(['type' => 'success']));
    app()->instance($class, $service);
    $this->withToken($token->plainTextToken)->getJson('/external/admin/v1/users/'.$user->id.$path)->assertOk();
})->with([
    ['', 'fetchUser', 'admin:users:read', \App\Service\Admin\AdminOperations::class],
    ['/billing/customer', 'getCustomer', 'admin:billing:read', \App\Service\Admin\AdminBilling::class],
    ['/billing/subscriptions', 'getSubscriptions', 'admin:billing:read', \App\Service\Admin\AdminBilling::class],
    ['/billing/payments', 'getPayments', 'admin:billing:read', \App\Service\Admin\AdminBilling::class],
    ['/deleted-forms', 'getDeletedForms', 'admin:forms:read', \App\Service\Admin\AdminForms::class],
]);
