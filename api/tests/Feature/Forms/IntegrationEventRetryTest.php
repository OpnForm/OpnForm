<?php

use App\Events\Models\FormIntegrationsEventCreated;
use App\Http\Resources\FormIntegrationsEventResource;
use App\Models\Forms\FormSubmission;
use App\Models\Integration\FormIntegration;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Auth;

beforeEach(function () {
    Http::fake(fn () => Http::response(['ok' => true], $this->webhookStatus ?? 200));
    Mail::fake();
    $this->user = $this->actingAsProUser();
    $this->workspace = $this->createUserWorkspace($this->user);
    $this->form = $this->createForm($this->user, $this->workspace, [
        'properties' => [['id' => 'name', 'name' => 'Name', 'type' => 'text']],
    ]);
    $this->integration = FormIntegration::create([
        'form_id' => $this->form->id, 'status' => 'active', 'integration_id' => 'webhook',
        'data' => ['webhook_url' => 'https://example.com/webhook'],
    ]);
    $this->submission = $this->form->submissions()->create([
        'data' => ['name' => 'Latest answer'], 'status' => FormSubmission::STATUS_COMPLETED,
        'meta' => ['attribution' => ['utm_source' => 'newsletter']],
    ]);
    $this->failedEvent = $this->integration->events()->create([
        'status' => 'error', 'data' => ['submission_id' => $this->submission->id, 'message' => 'Connection failed'],
    ]);
    $this->retryUrl = route('open.forms.integrations.events.retry', [$this->form, $this->integration->id, $this->failedEvent->id]);
    $this->eventsUrl = route('open.forms.integrations.events', [$this->form, $this->integration->id]);
});

it('rejects inactive integrations and reports why in the history', function () {
    $this->integration->update(['status' => 'inactive']);
    $this->getJson($this->eventsUrl)->assertJsonFragment(['id' => $this->failedEvent->id, 'can_retry' => false]);
    $this->postJson($this->retryUrl)->assertUnprocessable();
    Http::assertNothingSent();
});

it('rejects unavailable or incomplete submissions in both the API and the history', function ($state) {
    if ($state === 'deleted') {
        $this->submission->delete();
    } else {
        $this->submission->update(['status' => FormSubmission::STATUS_PARTIAL]);
    }
    $this->getJson($this->eventsUrl)->assertJsonFragment(['id' => $this->failedEvent->id, 'can_retry' => false]);
    $this->postJson($this->retryUrl)->assertUnprocessable();
    Http::assertNothingSent();
})->with(['deleted', 'partial']);

it('only offers the latest failed attempt for the same submission', function () {
    $latest = $this->integration->events()->create(['status' => 'error', 'data' => ['submission_id' => $this->submission->id]]);
    $this->getJson($this->eventsUrl)
        ->assertJsonFragment(['id' => $this->failedEvent->id, 'can_retry' => false])
        ->assertJsonFragment(['id' => $latest->id, 'can_retry' => true]);
    $this->postJson($this->retryUrl)->assertUnprocessable();
    Http::assertNothingSent();
});

it('returns the actual retry result when another submission creates a newer event', function () {
    \App\Models\Integration\FormIntegrationsEvent::updated(function ($event) {
        if ($event->status === 'success') {
            $this->integration->events()->createQuietly([
                'status' => 'error', 'data' => ['submission_id' => $this->submission->id + 1000],
            ]);
        }
    });
    Event::listen(FormIntegrationsEventCreated::class, function ($event) {
        if ($event->formIntegrationsEvent->status === 'success') {
            $this->integration->events()->createQuietly([
                'status' => 'error', 'data' => ['submission_id' => $this->submission->id + 1000],
            ]);
        }
    });
    $response = $this->postJson($this->retryUrl)->assertSuccessful()->assertJsonPath('event.status', 'Success');
    expect($response->json('event.data.submission_id'))->toBe($this->submission->id);
    Http::assertSentCount(1);
});

it('preserves attribution metadata and uses the current stored answers', function () {
    $this->postJson($this->retryUrl)->assertSuccessful();
    Http::assertSent(fn ($request) => $request['submission_id'] === $this->submission->id
        && $request['data']['name']['value'] === 'Latest answer'
        && $request['meta']['attribution']['utm_source'] === 'newsletter');
});

it('reports skipped conditions without claiming a successful send', function () {
    $this->integration->update(['logic' => ['value' => [
        'property_meta' => ['id' => 'name', 'type' => 'text'], 'operator' => 'equals', 'value' => 'Different answer',
    ]]]);
    $this->postJson($this->retryUrl)->assertSuccessful()->assertJsonPath('event.status', 'Skipped');
    Http::assertNothingSent();
});

it('blocks email retries when any recipient may already have received the message', function ($recipientStatus) {
    $this->integration->update(['integration_id' => 'email']);
    $this->failedEvent->update([
        'tracking_id' => (string) \Illuminate\Support\Str::uuid(),
        'data' => ['submission_id' => $this->submission->id, 'email' => ['version' => 1, 'recipients' => [
            'one' => ['address' => 'bad', 'status' => 'invalid'],
            'two' => ['address' => 'recipient@example.com', 'status' => $recipientStatus],
        ]]],
    ]);
    $this->getJson($this->eventsUrl)->assertJsonFragment(['id' => $this->failedEvent->id, 'can_retry' => false]);
    $this->postJson($this->retryUrl)->assertUnprocessable();
    Mail::assertNothingSent();
})->with(['accepted', 'delivered', 'unknown', 'sending', 'pending', 'bounced', 'complained']);

it('does not share retry eligibility between resource collections', function () {
    $otherIntegration = FormIntegration::create([
        'form_id' => $this->form->id, 'status' => 'active', 'integration_id' => 'webhook',
        'data' => ['webhook_url' => 'https://example.com/other'],
    ]);
    $other = $otherIntegration->events()->create(['status' => 'success', 'data' => ['submission_id' => $this->submission->id]]);
    FormIntegrationsEventResource::collection(collect([$other]))->resolve();
    expect(FormIntegrationsEventResource::make($this->failedEvent)->resolve()['can_retry'])->toBeTrue();
});

it('scopes the event and submission to the requested integration and form', function () {
    $otherIntegration = FormIntegration::create([
        'form_id' => $this->form->id, 'status' => 'active', 'integration_id' => 'webhook',
        'data' => ['webhook_url' => 'https://example.com/other'],
    ]);
    $other = $otherIntegration->events()->create(['status' => 'error', 'data' => ['submission_id' => $this->submission->id]]);
    $this->postJson(route('open.forms.integrations.events.retry', [$this->form, $this->integration->id, $other->id]))->assertNotFound();
    $otherForm = $this->createForm($this->user, $this->workspace);
    $otherSubmission = $otherForm->submissions()->create(['status' => 'completed', 'data' => []]);
    $this->failedEvent->update(['data' => ['submission_id' => $otherSubmission->id]]);
    $this->postJson($this->retryUrl)->assertUnprocessable();
    Http::assertNothingSent();
});

it('requires integration management permission for personal access tokens', function ($abilities, $allowed) {
    $this->actingAsGuest();
    $token = $this->user->createToken('integration-retry-test', $abilities)->plainTextToken;
    Auth::forgetGuards();
    $this->withToken($token);
    $response = $this->postJson($this->retryUrl);
    $allowed ? $response->assertSuccessful() : $response->assertForbidden();
    Http::assertSentCount($allowed ? 1 : 0);
})->with([
    'read token' => [['forms-read'], false],
    'write token' => [['forms-write'], false],
    'integration token' => [['manage-integrations'], true],
]);

it('records another failure and refuses replaying the original attempt', function () {
    $this->webhookStatus = 503;
    $result = $this->postJson($this->retryUrl)->assertSuccessful()
        ->assertJsonPath('type', 'error')->assertJsonPath('event.status', 'Error');
    expect($result->json('event.id'))->toBeGreaterThan($this->failedEvent->id);
    $this->postJson($this->retryUrl)->assertUnprocessable();
    $this->getJson($this->eventsUrl)
        ->assertJsonFragment(['id' => $this->failedEvent->id, 'can_retry' => false])
        ->assertJsonFragment(['id' => $result->json('event.id'), 'can_retry' => true]);
    Http::assertSentCount(1);
});

it('rejects legacy email events whose recipient outcomes are unavailable', function () {
    $this->integration->update(['integration_id' => 'email']);
    $this->postJson($this->retryUrl)->assertUnprocessable();
    $this->getJson($this->eventsUrl)->assertJsonFragment(['id' => $this->failedEvent->id, 'can_retry' => false]);
    Mail::assertNothingSent();
});

it('returns the new tracked email event for a safe retry without claiming delivery', function () {
    \Illuminate\Support\Facades\Notification::fake();
    $this->integration->update(['integration_id' => 'email', 'data' => [
        'send_to' => 'recipient@example.com', 'subject' => 'Submission', 'sender_name' => 'OpnForm',
        'email_content' => 'New submission', 'include_submission_data' => false,
    ]]);
    $this->failedEvent->update([
        'tracking_id' => (string) \Illuminate\Support\Str::uuid(),
        'data' => ['submission_id' => $this->submission->id, 'email' => ['version' => 1, 'recipients' => [
            'one' => ['address' => 'invalid', 'status' => 'invalid'],
        ]]],
    ]);
    $result = $this->postJson($this->retryUrl)->assertSuccessful()
        ->assertJsonPath('type', 'error')->assertJsonPath('event.status', 'Error')
        ->assertJsonPath('event.email_tracking', true);
    expect($result->json('event.id'))->toBeGreaterThan($this->failedEvent->id);
    expect($result->json('event.tracking_id'))->not->toBe($this->failedEvent->tracking_id);
    \Illuminate\Support\Facades\Notification::assertSentOnDemand(\App\Notifications\Forms\FormEmailNotification::class);
});

it('denies read-only workspace members and users outside the workspace', function ($role) {
    $otherUser = $this->createProUser();
    if ($role === 'readonly') {
        $this->workspace->users()->attach($otherUser, ['role' => 'readonly']);
    }
    $this->actingAs($otherUser, 'api');
    $this->postJson($this->retryUrl)->assertForbidden();
    Http::assertNothingSent();
})->with(['readonly', 'outsider']);

it('limits retry requests without sending extra requests to the destination', function () {
    $this->withMiddleware(\Illuminate\Routing\Middleware\ThrottleRequests::class);
    $this->integration->update(['status' => 'inactive']);
    for ($attempt = 0; $attempt < 10; $attempt++) {
        $this->postJson($this->retryUrl)->assertUnprocessable();
    }
    $this->postJson($this->retryUrl)->assertTooManyRequests()->assertHeader('Retry-After');
    Http::assertNothingSent();
});

it('keeps a committed attempt when execution is interrupted and blocks a second send', function () {
    Http::swap(new \Illuminate\Http\Client\Factory());
    $sendAttempts = 0;
    Http::fake(function () use (&$sendAttempts) {
        $sendAttempts++;
        throw new Error('Simulated interruption after a possible send');
    });
    expect(fn () => app(\App\Service\Integrations\RetryIntegrationEvent::class)->handle($this->integration, $this->failedEvent->id))
        ->toThrow(Error::class);
    $attempt = $this->integration->events()->orderByDesc('id')->first();
    expect($attempt->status)->toBe('processing');
    expect(data_get($attempt->data, 'retry_of'))->toBe($this->failedEvent->id);
    $this->postJson($this->retryUrl)->assertUnprocessable();
    $this->getJson($this->eventsUrl)->assertJsonFragment(['id' => $this->failedEvent->id, 'can_retry' => false]);
    expect($sendAttempts)->toBe(1);
});

it('rejects malformed retry route identifiers', function ($eventId) {
    $this->postJson(route('open.forms.integrations.events.retry', [$this->form, $this->integration->id, $eventId]))->assertNotFound();
    Http::assertNothingSent();
})->with(['invalid', '0', '-1', '9223372036854775808', str_repeat('9', 40)]);

it('keeps the reserved attempt when saving a successful send fails', function () {
    \App\Models\Integration\FormIntegrationsEvent::updating(function ($event) {
        if ($event->status === 'success') {
            throw new RuntimeException('Simulated persistence failure after sending');
        }
    });

    expect(fn () => app(\App\Service\Integrations\RetryIntegrationEvent::class)->handle($this->integration, $this->failedEvent->id))
        ->toThrow(RuntimeException::class);
    $attempt = $this->integration->events()->orderByDesc('id')->first();
    expect($attempt->status)->toBe('processing');
    $this->postJson($this->retryUrl)->assertUnprocessable();
    Http::assertSentCount(1);
});

it('blocks tracked email failures without structured recipient outcomes', function () {
    $this->integration->update(['integration_id' => 'email']);
    $this->failedEvent->update([
        'tracking_id' => (string) \Illuminate\Support\Str::uuid(),
        'data' => ['submission_id' => $this->submission->id, 'email' => ['version' => 1]],
    ]);
    $this->postJson($this->retryUrl)->assertUnprocessable();
    Mail::assertNothingSent();
});

it('continues other integrations without making a sent event retryable when its success cannot be recorded', function () {
    $firstIntegrationId = $this->integration->id;
    \App\Models\Integration\FormIntegrationsEvent::creating(function ($event) use ($firstIntegrationId) {
        if ($event->integration_id === $firstIntegrationId && $event->status === 'success') {
            throw new RuntimeException('Simulated persistence failure after sending');
        }
    });
    $other = FormIntegration::create([
        'form_id' => $this->form->id, 'status' => 'active', 'integration_id' => 'webhook',
        'data' => ['webhook_url' => 'https://example.com/other'],
    ]);

    (new \App\Listeners\Forms\NotifyFormSubmission())->handle(new \App\Events\Forms\FormSubmitted(
        $this->form,
        ['name' => 'Latest answer', 'submission_id' => $this->submission->id]
    ));

    Http::assertSentCount(2);
    Http::assertSent(fn ($request) => $request->url() === 'https://example.com/other');
    $uncertain = $this->integration->events()->latest('id')->first();
    expect($uncertain->status)->toBe('unknown');
    expect($uncertain->canRetry())->toBeFalse();
    expect($this->failedEvent->canRetry())->toBeFalse();
    expect($other->events()->first()->status)->toBe('success');
});
