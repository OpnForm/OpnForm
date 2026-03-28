<?php

use App\Models\Forms\FormSubmission;

it('tracks IP address when ip tracking is enabled on pro form', function () {
    $user = $this->actingAsProUser();
    $workspace = $this->createUserWorkspace($user);
    $form = $this->createForm($user, $workspace, [
        'enable_ip_tracking' => true
    ]);

    $formData = $this->generateFormSubmissionData($form, ['text' => 'Test submission']);

    $this->postJson(route('forms.answer', $form->slug), $formData)
        ->assertSuccessful()
        ->assertJson([
            'type' => 'success',
            'message' => 'Form submission saved.',
        ]);

    // Verify IP was tracked in meta
    $submission = FormSubmission::first();
    expect($submission->meta)->not->toBeNull();
    expect($submission->meta)->toHaveKey('ip_address');
    expect($submission->meta['ip_address'])->toContain('127.0.0.1');
});

it('does not track IP when ip tracking is disabled', function () {
    $user = $this->actingAsProUser();
    $workspace = $this->createUserWorkspace($user);
    $form = $this->createForm($user, $workspace, [
        'enable_ip_tracking' => false
    ]);

    $formData = $this->generateFormSubmissionData($form, ['text' => 'Test submission']);

    $this->postJson(route('forms.answer', $form->slug), $formData)
        ->assertSuccessful();

    // Verify IP was not tracked
    $submission = FormSubmission::first();
    expect($submission->meta)->toBeNull();
});

it('does not track IP on non-pro forms even when enabled', function () {
    $user = $this->actingAsUser(); // Non-pro user
    $workspace = $this->createUserWorkspace($user);
    $form = $this->createForm($user, $workspace, [
        'enable_ip_tracking' => true
    ]);

    $formData = $this->generateFormSubmissionData($form, ['text' => 'Test submission']);

    $this->postJson(route('forms.answer', $form->slug), $formData)
        ->assertSuccessful();

    // Verify IP was not tracked on non-pro form
    $submission = FormSubmission::first();
    expect($submission->meta)->toBeNull();
});

it('tracks IP in partial submissions when enabled', function () {
    $user = $this->actingAsProUser();
    $workspace = $this->createUserWorkspace($user);
    $form = $this->createForm($user, $workspace, [
        'enable_ip_tracking' => true,
        'enable_partial_submissions' => true
    ]);

    // Create partial submission
    $formData = $this->generateFormSubmissionData($form, ['text' => 'Partial submission']);
    $formData['is_partial'] = true;

    $response = $this->postJson(route('forms.answer', $form->slug), $formData)
        ->assertSuccessful();

    $submissionHash = $response->json('submission_hash');

    // Verify IP was tracked in partial submission
    $submission = FormSubmission::first();
    expect($submission->meta)->not->toBeNull();
    expect($submission->meta)->toHaveKey('ip_address');
    expect($submission->meta['ip_address'])->toContain('127.0.0.1');

    // Complete the submission
    $completeData = $this->generateFormSubmissionData($form, ['text' => 'Complete submission']);
    $completeData['submission_hash'] = $submissionHash;

    $this->postJson(route('forms.answer', $form->slug), $completeData)
        ->assertSuccessful();

    // Verify IP is still tracked after completion
    $submission->refresh();
    expect($submission->meta)->not->toBeNull();
    expect($submission->meta)->toHaveKey('ip_address');
    expect($submission->status)->toBe(FormSubmission::STATUS_COMPLETED);
});

it('preserves existing meta data when adding IP tracking', function () {
    $user = $this->actingAsProUser();
    $workspace = $this->createUserWorkspace($user);
    $form = $this->createForm($user, $workspace, [
        'enable_ip_tracking' => true
    ]);

    // Create initial partial submission with existing meta data
    $formData = $this->generateFormSubmissionData($form, ['text' => 'Initial submission']);
    $formData['is_partial'] = true;

    $response = $this->postJson(route('forms.answer', $form->slug), $formData)
        ->assertSuccessful();

    $submissionHash = $response->json('submission_hash');
    $submission = FormSubmission::first();

    // Manually add some custom meta data
    $submission->update([
        'meta' => array_merge($submission->meta ?? [], ['custom_field' => 'custom_value'])
    ]);

    // Submit form data to update the submission
    $updateData = $this->generateFormSubmissionData($form, ['text' => 'Updated submission']);
    $updateData['submission_hash'] = $submissionHash;

    $this->postJson(route('forms.answer', $form->slug), $updateData)
        ->assertSuccessful();

    // Verify both existing meta and IP are preserved
    $submission->refresh();
    expect($submission->meta)->toHaveKey('custom_field');
    expect($submission->meta['custom_field'])->toBe('custom_value');
    expect($submission->meta)->toHaveKey('ip_address');
    expect($submission->meta['ip_address'])->toContain('127.0.0.1');
});

it('does not modify existing meta when IP tracking conditions are not met', function () {
    $user = $this->actingAsUser(); // Non-pro user
    $workspace = $this->createUserWorkspace($user);
    $form = $this->createForm($user, $workspace, [
        'enable_ip_tracking' => true // Enabled but user is not pro
    ]);

    // Create initial submission with existing meta data
    $formData = $this->generateFormSubmissionData($form, ['text' => 'Initial submission']);
    $formData['is_partial'] = true;

    $response = $this->postJson(route('forms.answer', $form->slug), $formData)
        ->assertSuccessful();

    $submissionHash = $response->json('submission_hash');
    $submission = FormSubmission::first();

    // Manually add some existing meta data
    $submission->update([
        'meta' => ['existing_field' => 'existing_value']
    ]);

    // Submit form data to update the submission
    $updateData = $this->generateFormSubmissionData($form, ['text' => 'Updated submission']);
    $updateData['submission_hash'] = $submissionHash;

    $this->postJson(route('forms.answer', $form->slug), $updateData)
        ->assertSuccessful();

    // Verify existing meta is preserved and no IP was added
    $submission->refresh();
    expect($submission->meta)->toHaveKey('existing_field');
    expect($submission->meta['existing_field'])->toBe('existing_value');
    expect($submission->meta)->not->toHaveKey('ip_address');
});
