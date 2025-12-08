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
