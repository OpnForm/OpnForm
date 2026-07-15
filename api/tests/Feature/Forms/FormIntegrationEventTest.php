<?php

use App\Models\Forms\FormSubmission;
use App\Models\Integration\FormIntegrationsEvent;
use Illuminate\Support\Facades\Http;

it('can fetch form integration events', function () {
    $user = $this->actingAsProUser();
    $workspace = $this->createUserWorkspace($user);
    $form = $this->createForm($user, $workspace);

    $data = [
        'status' => 'active',
        'integration_id' => 'email',
        'logic' => null,
        'data' => [
            'send_to' => 'test@test.com',
            'sender_name' => 'OpnForm',
            'subject' => 'New form submission',
            'email_content' => 'Hello there 👋 <br>New form submission received.',
            'include_submission_data' => true,
            'include_hidden_fields_submission_data' => false,
            'reply_to' => null
        ]
    ];

    $response = $this->postJson(route('open.forms.integrations.create', $form), $data)
        ->assertSuccessful()
        ->assertJson([
            'type' => 'success',
            'message' => 'Form Integration was created.'
        ]);

    $this->getJson(route('open.forms.integrations.events', [$form, $response->json('form_integration.id')]))
        ->assertSuccessful()
        ->assertJsonCount(0);
});

it('prevents fetching another form integration events via mismatched form and integration ids', function () {
    $victim = $this->actingAsProUser();
    $victimWorkspace = $this->createUserWorkspace($victim);
    $victimForm = $this->createForm($victim, $victimWorkspace);

    $victimIntegrationResponse = $this->postJson(route('open.forms.integrations.create', $victimForm), [
        'status' => 'active',
        'integration_id' => 'webhook',
        'logic' => null,
        'data' => [
            'webhook_url' => 'https://example.com/victim-webhook'
        ]
    ])->assertSuccessful();

    $victimIntegrationId = $victimIntegrationResponse->json('form_integration.id');

    \App\Models\Integration\FormIntegrationsEvent::create([
        'integration_id' => $victimIntegrationId,
        'status' => \App\Models\Integration\FormIntegrationsEvent::STATUS_SUCCESS,
        'data' => ['message' => 'delivered']
    ]);

    $attacker = $this->createProUser();
    $this->actingAs($attacker, 'api');
    $attackerWorkspace = $this->createUserWorkspace($attacker);
    $attackerForm = $this->createForm($attacker, $attackerWorkspace);

    $this->getJson(route('open.forms.integrations.events', [$attackerForm, $victimIntegrationId]))
        ->assertStatus(404);
});

it('can retry a failed integration event', function () {
    Http::fake([
        'https://example.com/*' => Http::sequence()
            ->push(['error' => 'Server error'], 500)
            ->push(['ok' => true], 200),
    ]);

    $user = $this->actingAsProUser();
    $workspace = $this->createUserWorkspace($user);
    $form = $this->createForm($user, $workspace, [
        'properties' => [
            [
                'id' => 'name',
                'name' => 'Name',
                'type' => 'text',
                'hidden' => false,
                'required' => true,
            ],
        ],
    ]);

    $integrationResponse = $this->postJson(route('open.forms.integrations.create', $form), [
        'status' => 'active',
        'integration_id' => 'webhook',
        'logic' => null,
        'data' => [
            'webhook_url' => 'https://example.com/webhook',
        ],
    ])->assertSuccessful();

    $integrationId = $integrationResponse->json('form_integration.id');

    $this->postJson(route('forms.answer', $form->slug), $this->generateFormSubmissionData($form))
        ->assertSuccessful();

    $failedEvent = FormIntegrationsEvent::where('integration_id', $integrationId)
        ->where('status', FormIntegrationsEvent::STATUS_ERROR)
        ->first();

    expect($failedEvent)->not->toBeNull();

    $this->postJson(route('open.forms.integrations.events.retry', [$form, $integrationId, $failedEvent->id]))
        ->assertSuccessful()
        ->assertJson([
            'type' => 'success',
            'message' => 'Event retried successfully.',
            'event' => [
                'status' => 'Success',
            ],
        ]);

    Http::assertSentCount(2);

    $this->getJson(route('open.forms.integrations.events', [$form, $integrationId]))
        ->assertSuccessful()
        ->assertJsonFragment([
            'id' => $failedEvent->id,
            'can_retry' => false,
        ]);
});

it('rejects retrying a successful integration event', function () {
    Http::fake([
        'https://example.com/*' => Http::response(['ok' => true], 200),
    ]);

    $user = $this->actingAsProUser();
    $workspace = $this->createUserWorkspace($user);
    $form = $this->createForm($user, $workspace, [
        'properties' => [
            [
                'id' => 'name',
                'name' => 'Name',
                'type' => 'text',
                'hidden' => false,
                'required' => true,
            ],
        ],
    ]);

    $integrationResponse = $this->postJson(route('open.forms.integrations.create', $form), [
        'status' => 'active',
        'integration_id' => 'webhook',
        'logic' => null,
        'data' => [
            'webhook_url' => 'https://example.com/webhook',
        ],
    ])->assertSuccessful();

    $integrationId = $integrationResponse->json('form_integration.id');

    $this->postJson(route('forms.answer', $form->slug), $this->generateFormSubmissionData($form))
        ->assertSuccessful();

    $successEvent = FormIntegrationsEvent::where('integration_id', $integrationId)
        ->where('status', FormIntegrationsEvent::STATUS_SUCCESS)
        ->first();

    $this->postJson(route('open.forms.integrations.events.retry', [$form, $integrationId, $successEvent->id]))
        ->assertStatus(422)
        ->assertJson([
            'message' => 'Only failed events can be retried.',
        ]);
});

it('rejects retrying a failed event without submission data', function () {
    $user = $this->actingAsProUser();
    $workspace = $this->createUserWorkspace($user);
    $form = $this->createForm($user, $workspace);

    $integrationResponse = $this->postJson(route('open.forms.integrations.create', $form), [
        'status' => 'active',
        'integration_id' => 'webhook',
        'logic' => null,
        'data' => [
            'webhook_url' => 'https://example.com/webhook',
        ],
    ])->assertSuccessful();

    $integrationId = $integrationResponse->json('form_integration.id');

    $failedEvent = FormIntegrationsEvent::create([
        'integration_id' => $integrationId,
        'status' => FormIntegrationsEvent::STATUS_ERROR,
        'data' => ['message' => 'Connection failed'],
    ]);

    $this->postJson(route('open.forms.integrations.events.retry', [$form, $integrationId, $failedEvent->id]))
        ->assertStatus(422)
        ->assertJson([
            'message' => 'This event cannot be retried because submission data is unavailable.',
        ]);
});

it('includes can_retry in integration event responses', function () {
    $user = $this->actingAsProUser();
    $workspace = $this->createUserWorkspace($user);
    $form = $this->createForm($user, $workspace);

    $integrationResponse = $this->postJson(route('open.forms.integrations.create', $form), [
        'status' => 'active',
        'integration_id' => 'webhook',
        'logic' => null,
        'data' => [
            'webhook_url' => 'https://example.com/webhook',
        ],
    ])->assertSuccessful();

    $integrationId = $integrationResponse->json('form_integration.id');
    $submission = new FormSubmission([
        'data' => ['name' => 'Test'],
        'status' => FormSubmission::STATUS_COMPLETED,
    ]);
    $submission->form_id = $form->id;
    $submission->save();

    $otherSubmission = new FormSubmission([
        'data' => ['name' => 'Other'],
        'status' => FormSubmission::STATUS_COMPLETED,
    ]);
    $otherSubmission->form_id = $form->id;
    $otherSubmission->save();

    $retryableFailedEvent = FormIntegrationsEvent::create([
        'integration_id' => $integrationId,
        'status' => FormIntegrationsEvent::STATUS_ERROR,
        'data' => [
            'submission_id' => $otherSubmission->id,
            'message' => 'Connection failed',
        ],
    ]);

    $alreadyRetriedFailedEvent = FormIntegrationsEvent::create([
        'integration_id' => $integrationId,
        'status' => FormIntegrationsEvent::STATUS_ERROR,
        'data' => [
            'submission_id' => $submission->id,
            'message' => 'Connection failed',
        ],
    ]);

    FormIntegrationsEvent::create([
        'integration_id' => $integrationId,
        'status' => FormIntegrationsEvent::STATUS_SUCCESS,
        'data' => ['submission_id' => $submission->id],
    ]);

    $this->getJson(route('open.forms.integrations.events', [$form, $integrationId]))
        ->assertSuccessful()
        ->assertJsonFragment([
            'id' => $retryableFailedEvent->id,
            'can_retry' => true,
        ])
        ->assertJsonFragment([
            'id' => $alreadyRetriedFailedEvent->id,
            'can_retry' => false,
        ]);
});

it('rejects retrying a failed event that was already successfully retried', function () {
    Http::fake([
        'https://example.com/*' => Http::response(['ok' => true], 200),
    ]);

    $user = $this->actingAsProUser();
    $workspace = $this->createUserWorkspace($user);
    $form = $this->createForm($user, $workspace);

    $integrationResponse = $this->postJson(route('open.forms.integrations.create', $form), [
        'status' => 'active',
        'integration_id' => 'webhook',
        'logic' => null,
        'data' => [
            'webhook_url' => 'https://example.com/webhook',
        ],
    ])->assertSuccessful();

    $integrationId = $integrationResponse->json('form_integration.id');
    $submission = new FormSubmission([
        'data' => ['name' => 'Test'],
        'status' => FormSubmission::STATUS_COMPLETED,
    ]);
    $submission->form_id = $form->id;
    $submission->save();

    $failedEvent = FormIntegrationsEvent::create([
        'integration_id' => $integrationId,
        'status' => FormIntegrationsEvent::STATUS_ERROR,
        'data' => [
            'submission_id' => $submission->id,
            'message' => 'Connection failed',
        ],
    ]);

    FormIntegrationsEvent::create([
        'integration_id' => $integrationId,
        'status' => FormIntegrationsEvent::STATUS_SUCCESS,
        'data' => ['submission_id' => $submission->id],
    ]);

    $this->postJson(route('open.forms.integrations.events.retry', [$form, $integrationId, $failedEvent->id]))
        ->assertStatus(422)
        ->assertJson([
            'message' => 'This event has already been successfully retried.',
        ]);
});
