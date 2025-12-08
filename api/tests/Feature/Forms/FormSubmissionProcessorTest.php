<?php

use App\Service\Forms\FormSubmissionProcessor;

it('processes synchronously with editable submissions', function () {
    $user = $this->actingAsUser();
    $workspace = $this->createUserWorkspace($user);
    $form = $this->createForm($user, $workspace, [
        'editable_submissions' => true
    ]);

    $processor = new FormSubmissionProcessor();
    expect($processor->shouldProcessSynchronously($form))->toBeTrue();
});

it('processes synchronously with UUID field in redirect URL', function () {
    $user = $this->actingAsUser();
    $workspace = $this->createUserWorkspace($user);
    $form = $this->createForm($user, $workspace, [
        'redirect_url' => 'https://example.com/{field_1}',
        'properties' => [
            [
                'id' => 'field_1',
                'type' => 'text',
                'generates_uuid' => true,
                'name' => 'UUID Field'
            ]
        ]
    ]);

    $processor = new FormSubmissionProcessor();
    expect($processor->shouldProcessSynchronously($form))->toBeTrue();
});

it('processes synchronously with auto increment field in redirect URL', function () {
    $user = $this->actingAsUser();
    $workspace = $this->createUserWorkspace($user);
    $form = $this->createForm($user, $workspace, [
        'redirect_url' => 'https://example.com/{field_1}',
        'properties' => [
            [
                'id' => 'field_1',
                'type' => 'text',
                'generates_auto_increment_id' => true,
                'name' => 'Auto Increment Field'
            ]
        ]
    ]);

    $processor = new FormSubmissionProcessor();
    expect($processor->shouldProcessSynchronously($form))->toBeTrue();
});

it('processes asynchronously with no generated fields in redirect URL', function () {
    $user = $this->actingAsUser();
    $workspace = $this->createUserWorkspace($user);
    $form = $this->createForm($user, $workspace, [
        'redirect_url' => 'https://example.com/{field_1}',
        'properties' => [
            [
                'id' => 'field_1',
                'type' => 'text',
                'name' => 'Regular Field'
            ]
        ]
    ]);

    $processor = new FormSubmissionProcessor();
    expect($processor->shouldProcessSynchronously($form))->toBeFalse();
});

it('processes asynchronously when generated field is not used in redirect URL', function () {
    $user = $this->actingAsUser();
    $workspace = $this->createUserWorkspace($user);
    $form = $this->createForm($user, $workspace, [
        'redirect_url' => 'https://example.com/{field_2}',
        'properties' => [
            [
                'id' => 'field_1',
                'type' => 'text',
                'generates_uuid' => true,
                'name' => 'UUID Field'
            ],
            [
                'id' => 'field_2',
                'type' => 'text',
                'name' => 'Regular Field'
            ]
        ]
    ]);

    $processor = new FormSubmissionProcessor();
    expect($processor->shouldProcessSynchronously($form))->toBeFalse();
});

it('processes asynchronously with no redirect URL', function () {
    $user = $this->actingAsUser();
    $workspace = $this->createUserWorkspace($user);
    $form = $this->createForm($user, $workspace, [
        'redirect_url' => null,
        'properties' => [
            [
                'id' => 'field_1',
                'type' => 'text',
                'generates_uuid' => true,
                'name' => 'UUID Field'
            ]
        ]
    ]);

    $processor = new FormSubmissionProcessor();
    expect($processor->shouldProcessSynchronously($form))->toBeFalse();
});

it('formats redirect data correctly for pro users', function () {
    $user = $this->actingAsProUser();
    $workspace = $this->createUserWorkspace($user);
    $form = $this->createForm($user, $workspace, [
        'redirect_url' => 'https://example.com/<span mention mention-field-id="field_1"></span>'
    ]);

    $processor = new FormSubmissionProcessor();
    $redirectData = $processor->getRedirectData($form, [
        'field_1' => 'test-value'
    ]);

    expect($redirectData)->toBe([
        'redirect' => true,
        'redirect_url' => 'https://example.com/test-value'
    ]);
});

it('returns no redirect for non-pro users', function () {
    $user = $this->actingAsUser();
    $workspace = $this->createUserWorkspace($user);
    $form = $this->createForm($user, $workspace, [
        'redirect_url' => 'https://example.com/<span mention mention-field-id="field_1"></span>'
    ]);

    $processor = new FormSubmissionProcessor();
    $redirectData = $processor->getRedirectData($form, [
        'field_1' => 'test-value'
    ]);

    expect($redirectData)->toBe([
        'redirect' => false
    ]);
});


describe('Clear Empty Fields On Update', function () {
    it('sends empty values when editing submission via unique URL', function () {
        $user = $this->actingAsProUser();
        $workspace = $this->createUserWorkspace($user);
        $form = $this->createForm($user, $workspace, [
            'editable_submissions' => true,
            'clear_empty_fields_on_update' => false, // Default safe mode
        ]);

        // Submit initial form with data
        $initialData = $this->generateFormSubmissionData($form);
        $response = $this->postJson(route('forms.answer', $form), $initialData)
            ->assertSuccessful();

        $submissionId = $response->json('submission_id');

        // Edit submission with empty field (should still send empty to clear it)
        $editData = $this->generateFormSubmissionData($form);
        // Clear one field to test empty value handling
        $firstFieldKey = array_key_first($editData);
        $editData[$firstFieldKey] = null;
        $editData['submission_id'] = $submissionId;

        $this->postJson(route('forms.answer', $form), $editData)
            ->assertSuccessful()
            ->assertJson([
                'type' => 'success',
                'message' => 'Form submission saved.',
            ]);
    });

    it('respects clear_empty_fields_on_update=false for field-matching updates', function () {
        $user = $this->actingAsProUser();
        $workspace = $this->createUserWorkspace($user);
        $form = $this->createForm($user, $workspace, [
            'database_fields_update' => ['field_id_for_email'], // Match by email
            'clear_empty_fields_on_update' => false, // Default: don't clear empty fields
        ]);

        // Submit initial form
        $initialData = $this->generateFormSubmissionData($form);
        $this->postJson(route('forms.answer', $form), $initialData)
            ->assertSuccessful();

        // Update same record with empty field (should be skipped, not sent to Notion)
        $updateData = $this->generateFormSubmissionData($form);
        // Get the first field key to clear it
        $fieldKeys = array_keys($updateData);
        $fieldToClear = $fieldKeys[0];
        $updateData[$fieldToClear] = null; // Empty field should NOT be sent

        $this->postJson(route('forms.answer', $form), $updateData)
            ->assertSuccessful();
    });

    it('clears empty fields when clear_empty_fields_on_update=true for field-matching updates', function () {
        $user = $this->actingAsProUser();
        $workspace = $this->createUserWorkspace($user);
        $form = $this->createForm($user, $workspace, [
            'database_fields_update' => ['field_id_for_email'], // Match by email
            'clear_empty_fields_on_update' => true, // ENABLED: do clear empty fields
        ]);

        // Submit initial form
        $initialData = $this->generateFormSubmissionData($form);
        $this->postJson(route('forms.answer', $form), $initialData)
            ->assertSuccessful();

        // Update same record with empty field (should be sent and clear the field)
        $updateData = $this->generateFormSubmissionData($form);
        // Get the first field key to clear it
        $fieldKeys = array_keys($updateData);
        $fieldToClear = $fieldKeys[0];
        $updateData[$fieldToClear] = null; // Empty field SHOULD be sent to clear it

        $this->postJson(route('forms.answer', $form), $updateData)
            ->assertSuccessful();
    });
});
