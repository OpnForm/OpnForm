<?php

use App\Models\Integration\FormIntegration;

test('free user can create one email integration to their own email', function () {
    $user = $this->actingAsUser();
    $workspace = $this->createUserWorkspace($user);
    $form = $this->createForm($user, $workspace);

    // First email integration should succeed with user's own email
    $response = $this->postJson(route('open.forms.integrations.create', $form), [
        'integration_id' => 'email',
        'status' => 'active',
        'data' => [
            'send_to' => $user->email,
            'sender_name' => 'Test Sender',
            'subject' => 'Test Subject',
            'email_content' => 'Test Content',
            'include_submission_data' => true
        ]
    ]);

    $response->assertSuccessful();
    expect(FormIntegration::where('form_id', $form->id)->count())->toBe(1);

    // Second email integration should fail
    $response = $this->postJson(route('open.forms.integrations.create', $form), [
        'integration_id' => 'email',
        'status' => 'active',
        'data' => [
            'send_to' => $user->email,
            'sender_name' => 'Test Sender',
            'subject' => 'Test Subject',
            'email_content' => 'Test Content',
            'include_submission_data' => true
        ]
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['data.send_to']);
});

test('free user cannot send to other email addresses', function () {
    $user = $this->actingAsUser();
    $workspace = $this->createUserWorkspace($user);
    $form = $this->createForm($user, $workspace);

    $response = $this->postJson(route('open.forms.integrations.create', $form), [
        'integration_id' => 'email',
        'status' => 'active',
        'data' => [
            'send_to' => 'other@example.com',
            'sender_name' => 'Test Sender',
            'subject' => 'Test Subject',
            'email_content' => 'Test Content',
            'include_submission_data' => true
        ]
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['data.send_to']);

    // Check that the error message contains the expected text with user's email
    $responseData = $response->json();
    $errorMessage = $responseData['errors']['data.send_to'][0];
    expect($errorMessage)->toContain('You can only send email notification to your own email address');
    expect($errorMessage)->toContain($user->email);
    expect($errorMessage)->toContain('Please upgrade to the Pro plan to send to other email addresses.');
});

test('pro user can create multiple email integrations', function () {
    $user = $this->actingAsProUser();
    $workspace = $this->createUserWorkspace($user);
    $form = $this->createForm($user, $workspace);

    // First email integration
    $response = $this->postJson(route('open.forms.integrations.create', $form), [
        'integration_id' => 'email',
        'status' => 'active',
        'data' => [
            'send_to' => 'test@example.com',
            'sender_name' => 'Test Sender',
            'subject' => 'Test Subject',
            'email_content' => 'Test Content',
            'include_submission_data' => true
        ]
    ]);

    $response->assertSuccessful();

    // Second email integration should also succeed for pro users
    $response = $this->postJson(route('open.forms.integrations.create', $form), [
        'integration_id' => 'email',
        'status' => 'active',
        'data' => [
            'send_to' => 'another@example.com',
            'sender_name' => 'Test Sender',
            'subject' => 'Test Subject',
            'email_content' => 'Test Content',
            'include_submission_data' => true
        ]
    ]);

    $response->assertSuccessful();
    expect(FormIntegration::where('form_id', $form->id)->count())->toBe(2);
});

test('free user cannot add multiple emails', function () {
    $user = $this->actingAsUser();
    $workspace = $this->createUserWorkspace($user);
    $form = $this->createForm($user, $workspace);

    $response = $this->postJson(route('open.forms.integrations.create', $form), [
        'integration_id' => 'email',
        'status' => 'active',
        'data' => [
            'send_to' => "test@example.com\nanother@example.com",
            'sender_name' => 'Test Sender',
            'subject' => 'Test Subject',
            'email_content' => 'Test Content',
            'include_submission_data' => true
        ]
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['data.send_to'])
        ->assertJson([
            'errors' => [
                'data.send_to' => ['You can only send to a single email address on the free plan. Please upgrade to the Pro plan to create a new integration.']
            ]
        ]);
});

test('pro user can add multiple emails', function () {
    $user = $this->actingAsProUser();
    $workspace = $this->createUserWorkspace($user);
    $form = $this->createForm($user, $workspace);

    $response = $this->postJson(route('open.forms.integrations.create', $form), [
        'integration_id' => 'email',
        'status' => 'active',
        'data' => [
            'send_to' => "test@example.com\nanother@example.com\nthird@example.com",
            'sender_name' => 'Test Sender',
            'subject' => 'Test Subject',
            'email_content' => 'Test Content',
            'include_submission_data' => true
        ]
    ]);

    $response->assertSuccessful();

    $integration = FormIntegration::where('form_id', $form->id)->first();
    expect($integration)->not->toBeNull();
    expect($integration->data->send_to)->toContain('test@example.com');
    expect($integration->data->send_to)->toContain('another@example.com');
    expect($integration->data->send_to)->toContain('third@example.com');
});

test('free user can update their single email integration to their own email', function () {
    $user = $this->actingAsUser();
    $workspace = $this->createUserWorkspace($user);
    $form = $this->createForm($user, $workspace);

    // Create initial integration with user's email
    $response = $this->postJson(route('open.forms.integrations.create', $form), [
        'integration_id' => 'email',
        'status' => 'active',
        'data' => [
            'send_to' => $user->email,
            'sender_name' => 'Test Sender',
            'subject' => 'Test Subject',
            'email_content' => 'Test Content',
            'include_submission_data' => true
        ]
    ]);

    $response->assertSuccessful();
    $integrationId = $response->json('form_integration.id');

    // Update the integration - still with user's email
    $response = $this->putJson(route('open.forms.integrations.update', [$form, $integrationId]), [
        'integration_id' => 'email',
        'status' => 'active',
        'data' => [
            'send_to' => $user->email,
            'sender_name' => 'Updated Sender',
            'subject' => 'Updated Subject',
            'email_content' => 'Updated Content',
            'include_submission_data' => true
        ]
    ]);

    $response->assertSuccessful();

    $integration = FormIntegration::find($integrationId);
    expect($integration->data->send_to)->toBe($user->email);
    expect($integration->data->sender_name)->toBe('Updated Sender');
});

test('free user cannot update integration to other email addresses', function () {
    $user = $this->actingAsUser();
    $workspace = $this->createUserWorkspace($user);
    $form = $this->createForm($user, $workspace);

    // Create initial integration with user's email
    $response = $this->postJson(route('open.forms.integrations.create', $form), [
        'integration_id' => 'email',
        'status' => 'active',
        'data' => [
            'send_to' => $user->email,
            'sender_name' => 'Test Sender',
            'subject' => 'Test Subject',
            'email_content' => 'Test Content',
            'include_submission_data' => true
        ]
    ]);

    $response->assertSuccessful();
    $integrationId = $response->json('form_integration.id');

    // Try to update to another email address - should fail
    $response = $this->putJson(route('open.forms.integrations.update', [$form, $integrationId]), [
        'integration_id' => 'email',
        'status' => 'active',
        'data' => [
            'send_to' => 'other@example.com',
            'sender_name' => 'Updated Sender',
            'subject' => 'Updated Subject',
            'email_content' => 'Updated Content',
            'include_submission_data' => true
        ]
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['data.send_to']);

    // Check that the error message contains the expected text with user's email
    $responseData = $response->json();
    $errorMessage = $responseData['errors']['data.send_to'][0];
    expect($errorMessage)->toContain('You can only send email notification to your own email address');
    expect($errorMessage)->toContain($user->email);
    expect($errorMessage)->toContain('Please upgrade to the Pro plan to send to other email addresses.');
});
