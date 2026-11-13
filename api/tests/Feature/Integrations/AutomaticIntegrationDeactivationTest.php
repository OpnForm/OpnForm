<?php

use App\Events\Forms\FormSubmitted;
use App\Jobs\Form\StoreFormSubmissionJob;
use App\Listeners\Forms\NotifyFormSubmission;
use App\Mail\Forms\FormIntegrationsEventCreationConfirmationMail;
use App\Models\Integration\FormIntegration;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;

beforeEach(function () {
    Mail::fake();
    $user = $this->actingAsProUser();
    $this->form = $this->createForm($user, $this->createUserWorkspace($user));
});

it('deactivates a failing integration and skips delivery from a stale handler', function (string $type, string $urlKey) {
    Http::fake(['https://example.com/*' => Http::response([], 405)]);

    $integration = $this->form->integrations()->create([
        'status' => FormIntegration::STATUS_ACTIVE,
        'integration_id' => $type,
        'data' => [$urlKey => 'https://example.com/test'],
    ]);
    $event = new FormSubmitted($this->form, []);
    $staleHandler = NotifyFormSubmission::getIntegrationHandler($event, $integration->fresh());
    $listener = app(NotifyFormSubmission::class);

    $listener->handle($event);
    $listener->handle($event);
    expect($integration->fresh()->status)->toBe(FormIntegration::STATUS_ACTIVE);

    $listener->handle($event);
    expect($integration->fresh()->status)->toBe(FormIntegration::STATUS_INACTIVE)
        ->and($integration->events()->latest('id')->first()->data->automatic_deactivation)
        ->toContain('HTTP 405');

    $listener->handle($event);
    expect($staleHandler->run()->status)->toBe('skipped');
    Http::assertSentCount(3);
    Mail::assertQueued(FormIntegrationsEventCreationConfirmationMail::class, 3);

    $queuedSubmission = new StoreFormSubmissionJob($this->form, []);
    $integration->refresh()->update(['status' => FormIntegration::STATUS_ACTIVE]);
    expect($staleHandler->run()->status)->toBe('skipped');
    $listener->handle($event);
    Http::assertSentCount(3);

    $this->travel(1)->seconds();
    $queuedSubmission->handle();
    Http::assertSentCount(3);
    expect($this->form->submissions()->count())->toBe(1);

    $listener->handle(new FormSubmitted($this->form, []));
    Http::assertSentCount(4);
    expect($integration->fresh()->status)->toBe(FormIntegration::STATUS_ACTIVE);
})->with([
    'webhook' => ['webhook', 'webhook_url'],
    'Zapier' => ['zapier', 'hook_url'],
]);

it('only counts consecutive configuration errors inside a rolling window', function () {
    Http::fake(['https://example.com/*' => Http::sequence()
        ->pushStatus(405)->pushStatus(405)->pushStatus(200)
        ->pushStatus(405)->pushStatus(405)->pushStatus(429)
        ->pushStatus(405)->pushStatus(405)->pushStatus(404)
        ->pushStatus(404)->pushStatus(404)->pushStatus(404)]);

    $integration = $this->form->integrations()->create([
        'status' => FormIntegration::STATUS_ACTIVE,
        'integration_id' => 'webhook',
        'data' => ['webhook_url' => 'https://example.com/test'],
    ]);
    $event = new FormSubmitted($this->form, []);
    $listener = app(NotifyFormSubmission::class);

    for ($i = 0; $i < 9; $i++) {
        $listener->handle($event);
        expect($integration->fresh()->status)->toBe(FormIntegration::STATUS_ACTIVE);
    }

    $this->travel(16)->minutes();
    $listener->handle($event);
    $listener->handle($event);
    expect($integration->fresh()->status)->toBe(FormIntegration::STATUS_ACTIVE);

    $listener->handle($event);
    expect($integration->fresh()->status)->toBe(FormIntegration::STATUS_INACTIVE);
    Http::assertSentCount(12);
});

it('does not count an in-flight failure against updated settings', function () {
    $integration = $this->form->integrations()->create([
        'status' => FormIntegration::STATUS_ACTIVE,
        'integration_id' => 'webhook',
        'data' => ['webhook_url' => 'https://example.com/old'],
    ]);
    $event = new FormSubmitted($this->form, []);
    $listener = app(NotifyFormSubmission::class);

    Http::fake([
        'https://example.com/old' => function () use ($integration) {
            $integration->fresh()->update(['data' => ['webhook_url' => 'https://example.com/new']]);

            return Http::response([], 405);
        },
        'https://example.com/new' => Http::response([], 405),
    ]);

    $listener->handle($event);
    $listener->handle($event);
    $listener->handle($event);
    expect($integration->fresh()->status)->toBe(FormIntegration::STATUS_ACTIVE);

    $listener->handle($event);
    expect($integration->fresh()->status)->toBe(FormIntegration::STATUS_INACTIVE);
    Http::assertSentCount(4);
});

it('reactivates an existing Zapier hook through the integration update route', function () {
    $hook = ['hook_url' => 'https://example.com/zapier'];
    $integration = $this->form->integrations()->create([
        'status' => FormIntegration::STATUS_INACTIVE,
        'integration_id' => 'zapier',
        'data' => $hook,
    ]);

    $integration->failure_streak = ['auto_disabled' => true];
    $integration->saveQuietly();

    $url = route('open.forms.integrations.update', [$this->form, $integration->id]);
    $payload = [
        'integration_id' => 'zapier',
        'status' => FormIntegration::STATUS_ACTIVE,
        'data' => $hook,
        'logic' => null,
        'oauth_id' => null,
    ];

    $this->putJson($url, $payload)->assertSuccessful()
        ->assertJsonPath('form_integration.status', FormIntegration::STATUS_INACTIVE);

    $this->putJson($url, $payload + ['reactivate' => true])->assertSuccessful()
        ->assertJsonPath('form_integration.status', FormIntegration::STATUS_ACTIVE)
        ->assertJsonPath('form_integration.data.hook_url', $hook['hook_url']);
    expect($integration->fresh()->failure_streak['resume_after'])->toBeFloat();
});
