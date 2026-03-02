<?php

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
            'webhook_url' => 'https://victim.example/webhook'
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
