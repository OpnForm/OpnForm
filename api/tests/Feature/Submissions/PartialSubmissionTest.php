<?php

use App\Models\Forms\FormSubmission;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

it('can submit form partially and complete it later using submission hash', function () {
    $user = $this->actingAsBusinessUser();
    $workspace = $this->createUserWorkspace($user);
    $form = $this->createForm($user, $workspace, [
        'enable_partial_submissions' => true
    ]);

    // Initial partial submission
    $formData = $this->generateFormSubmissionData($form, ['text' => 'Initial Text']);
    $formData['is_partial'] = true;

    $partialResponse = $this->postJson(route('forms.answer', $form->slug), $formData)
        ->assertSuccessful()
        ->assertJson([
            'type' => 'success',
        ]);

    $submissionHash = $partialResponse->json('submission_hash');
    expect($submissionHash)->not->toBeEmpty();

    // Complete the submission using the hash
    $completeData = $this->generateFormSubmissionData($form, [
        'text' => 'Complete Text',
        'email' => 'test@example.com'
    ]);
    $completeData['submission_hash'] = $submissionHash;

    $this->postJson(route('forms.answer', $form->slug), $completeData)
        ->assertSuccessful()
        ->assertJson([
            'type' => 'success',
            'message' => 'Form submission saved.',
        ]);

    // Verify final submission state
    $submission = FormSubmission::first();
    expect($submission->status)->toBe(FormSubmission::STATUS_COMPLETED);
    expect($submission->data)->toHaveKey(array_key_first($completeData));
});

it('can update partial submission multiple times', function () {
    $user = $this->actingAsBusinessUser();
    $workspace = $this->createUserWorkspace($user);
    $form = $this->createForm($user, $workspace, [
        'enable_partial_submissions' => true
    ]);
    $targetField = collect($form->properties)->where('name', 'Name')->first();

    // First partial submission
    $formData = $this->generateFormSubmissionData($form, [$targetField['id'] => 'First Draft']);
    $formData['is_partial'] = true;

    $firstResponse = $this->postJson(route('forms.answer', $form->slug), $formData)
        ->assertSuccessful();

    $submissionHash = $firstResponse->json('submission_hash');

    // Second partial update
    $secondData = $this->generateFormSubmissionData($form, [$targetField['id'] => 'Second Draft']);
    $secondData['is_partial'] = true;
    $secondData['submission_hash'] = $submissionHash;

    $this->postJson(route('forms.answer', $form->slug), $secondData)
        ->assertSuccessful();

    // Verify submission was updated
    $submission = FormSubmission::first();
    expect($submission->status)->toBe(FormSubmission::STATUS_PARTIAL);
    expect($submission->data)->toHaveKey(array_key_first($secondData));
    expect($submission->data[array_key_first($secondData)])->toBe('Second Draft');
});

it('does not update a partial submission from a raw numeric submission id', function () {
    $user = $this->actingAsBusinessUser();
    $workspace = $this->createUserWorkspace($user);
    $form = $this->createForm($user, $workspace, [
        'enable_partial_submissions' => true
    ]);
    $nameField = collect($form->properties)->where('name', 'Name')->first();

    $this->actingAsGuest();

    $victimData = $this->generateFormSubmissionData($form, [
        $nameField['id'] => 'VICTIM_CONFIDENTIAL_DATA',
    ]);
    $victimData['is_partial'] = true;

    $this->postJson(route('forms.answer', $form->slug), $victimData)
        ->assertSuccessful();

    $victimSubmission = $form->submissions()->first();

    $attackData = $this->generateFormSubmissionData($form, [
        $nameField['id'] => 'ATTACKER_POISONED_DATA',
    ]);
    $attackData['is_partial'] = true;
    $attackData['submission_id'] = (string) $victimSubmission->id;

    $this->postJson(route('forms.answer', $form->slug), $attackData)
        ->assertSuccessful();

    expect($form->submissions()->count())->toBe(2);

    $victimSubmission->refresh();
    expect($victimSubmission->data[$nameField['id']])->toBe('VICTIM_CONFIDENTIAL_DATA');
});

it('does not update a completed submission from a raw numeric submission id in a partial request', function () {
    $user = $this->actingAsBusinessUser();
    $workspace = $this->createUserWorkspace($user);
    $form = $this->createForm($user, $workspace, [
        'enable_partial_submissions' => true
    ]);
    $nameField = collect($form->properties)->where('name', 'Name')->first();

    $this->actingAsGuest();

    $completedData = $this->generateFormSubmissionData($form, [
        $nameField['id'] => 'COMPLETED_ORIGINAL_DATA',
    ]);

    $this->postJson(route('forms.answer', $form->slug), $completedData)
        ->assertSuccessful();

    $completedSubmission = $form->submissions()->first();

    $attackData = $this->generateFormSubmissionData($form, [
        $nameField['id'] => 'ATTACKER_POISONED_DATA',
    ]);
    $attackData['is_partial'] = true;
    $attackData['submission_id'] = (string) $completedSubmission->id;

    $this->postJson(route('forms.answer', $form->slug), $attackData)
        ->assertSuccessful();

    expect($form->submissions()->count())->toBe(2);

    $completedSubmission->refresh();
    expect($completedSubmission->status)->toBe(FormSubmission::STATUS_COMPLETED);
    expect($completedSubmission->data[$nameField['id']])->toBe('COMPLETED_ORIGINAL_DATA');
});

it('calculates stats correctly for partial vs completed submissions', function () {
    $user = $this->actingAsBusinessUser();
    $workspace = $this->createUserWorkspace($user);
    $form = $this->createForm($user, $workspace, [
        'enable_partial_submissions' => true
    ]);

    // Create partial submission
    $partialData = $this->generateFormSubmissionData($form, ['text' => 'Partial']);
    $partialData['is_partial'] = true;
    $this->postJson(route('forms.answer', $form->slug), $partialData);

    // Create completed submission
    $completeData = $this->generateFormSubmissionData($form, ['text' => 'Complete']);
    $this->postJson(route('forms.answer', $form->slug), $completeData);

    // Verify stats
    $form->refresh();
    expect($form->submissions()->where('status', FormSubmission::STATUS_PARTIAL)->count())->toBe(1);
    expect($form->submissions()->where('status', FormSubmission::STATUS_COMPLETED)->count())->toBe(1);
});

it('handles file uploads in partial submissions', function () {
    Storage::fake();

    $user = $this->actingAsBusinessUser();
    $workspace = $this->createUserWorkspace($user);
    $form = $this->createForm($user, $workspace, [
        'enable_partial_submissions' => true,
        'clear_empty_fields_on_update' => true
    ]);

    // Create a fake file
    $file = UploadedFile::fake()->create('test.pdf', 100);

    // First partial submission with file
    $formData = $this->generateFormSubmissionData($form);
    $fileFieldId = collect($form->properties)->where('type', 'files')->first()['id'];
    $formData[$fileFieldId] = $file;
    $formData['is_partial'] = true;

    $response = $this->postJson(route('forms.answer', $form->slug), $formData)
        ->assertSuccessful();

    $submissionHash = $response->json('submission_hash');

    // Complete the submission
    $completeData = $this->generateFormSubmissionData($form, ['text' => 'Complete']);
    $completeData['submission_hash'] = $submissionHash;

    $this->postJson(route('forms.answer', $form->slug), $completeData)
        ->assertSuccessful();

    // Verify file was preserved
    $submission = FormSubmission::first();
    expect($submission->data)->toHaveKey($fileFieldId);
    $filePath = str_replace('storage/', '', $submission->data[$fileFieldId]);
    expect(Storage::disk('local')->exists($filePath))->toBeTrue();
});

it('handles signature field in partial submissions', function () {
    Storage::fake();

    $user = $this->actingAsBusinessUser();
    $workspace = $this->createUserWorkspace($user);
    $form = $this->createForm($user, $workspace, [
        'enable_partial_submissions' => true,
        'clear_empty_fields_on_update' => true
    ]);

    // Create partial submission with signature
    $formData = $this->generateFormSubmissionData($form);
    $signatureFieldId = collect($form->properties)->where('type', 'files')->first()['id'];
    $formData[$signatureFieldId] = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAA...'; // Base64 signature data
    $formData['is_partial'] = true;

    $response = $this->postJson(route('forms.answer', $form->slug), $formData)
        ->assertSuccessful();

    $submissionHash = $response->json('submission_hash');

    // Complete the submission
    $completeData = $this->generateFormSubmissionData($form, ['text' => 'Complete']);
    $completeData['submission_hash'] = $submissionHash;

    $this->postJson(route('forms.answer', $form->slug), $completeData)
        ->assertSuccessful();

    // Verify signature was preserved
    $submission = FormSubmission::first();
    expect($submission->data)->toHaveKey($signatureFieldId);
    $filePath = str_replace('storage/', '', $submission->data[$signatureFieldId]);
    expect(Storage::disk('local')->exists($filePath))->toBeTrue();
});

it('requires at least one field with value for partial submission', function () {
    $user = $this->actingAsBusinessUser();
    $workspace = $this->createUserWorkspace($user);
    $form = $this->createForm($user, $workspace, [
        'enable_partial_submissions' => true
    ]);

    // Try to submit with empty data
    $formData = ['is_partial' => true];

    $this->postJson(route('forms.answer', $form->slug), $formData)
        ->assertStatus(422)
        ->assertJson([
            'message' => 'At least one field must have a value for partial submissions.'
        ]);
});

it('submits as completed when partial feature is disabled', function () {
    $user = $this->actingAsBusinessUser();
    $workspace = $this->createUserWorkspace($user);

    // Create form with partial submissions disabled
    $form = $this->createForm($user, $workspace, [
        'enable_partial_submissions' => false
    ]);

    $formData = $this->generateFormSubmissionData($form, ['text' => 'Test']);
    $formData['is_partial'] = true;

    $this->postJson(route('forms.answer', $form->slug), $formData)
        ->assertSuccessful()
        ->assertJson([
            'type' => 'success',
            'message' => 'Form submission saved.',
        ]);

    // Verify submission was saved as completed
    $submission = FormSubmission::first();
    expect($submission->status)->toBe(FormSubmission::STATUS_COMPLETED);
});

it('submits as completed on free tier forms', function () {
    $user = $this->actingAsUser();
    $workspace = $this->createUserWorkspace($user);

    // Create free tier form with partial submissions enabled
    $form = $this->createForm($user, $workspace, [
        'enable_partial_submissions' => true
    ]);

    $formData = $this->generateFormSubmissionData($form, ['text' => 'Test']);
    $formData['is_partial'] = true;

    $this->postJson(route('forms.answer', $form->slug), $formData)
        ->assertSuccessful()
        ->assertJson([
            'type' => 'success',
            'message' => 'Form submission saved.',
        ]);

    // Verify submission was saved as completed (free tier cannot use partial submissions)
    $submission = FormSubmission::first();
    expect($submission->status)->toBe(FormSubmission::STATUS_COMPLETED);
});

it('submits as completed on pro tier forms - requires business', function () {
    $user = $this->actingAsProUser();
    $workspace = $this->createUserWorkspace($user);

    // Pro tier form with partial submissions enabled - but partial requires business
    $form = $this->createForm($user, $workspace, [
        'enable_partial_submissions' => true
    ]);

    $formData = $this->generateFormSubmissionData($form, ['text' => 'Test']);
    $formData['is_partial'] = true;

    $this->postJson(route('forms.answer', $form->slug), $formData)
        ->assertSuccessful()
        ->assertJson([
            'type' => 'success',
            'message' => 'Form submission saved.',
        ]);

    // Verify submission was saved as completed (pro tier cannot use partial submissions - needs business)
    $submission = FormSubmission::first();
    expect($submission->status)->toBe(FormSubmission::STATUS_COMPLETED);
});

it('allows partial submissions on business tier forms', function () {
    $user = $this->actingAsBusinessUser();
    $workspace = $this->createUserWorkspace($user);
    $form = $this->createForm($user, $workspace, [
        'enable_partial_submissions' => true
    ]);

    $formData = $this->generateFormSubmissionData($form, ['text' => 'Test']);
    $formData['is_partial'] = true;

    $response = $this->postJson(route('forms.answer', $form->slug), $formData)
        ->assertSuccessful()
        ->assertJson([
            'type' => 'success',
        ]);

    $submissionHash = $response->json('submission_hash');
    expect($submissionHash)->not->toBeEmpty();

    $submission = FormSubmission::first();
    expect($submission->status)->toBe(FormSubmission::STATUS_PARTIAL);
});

it('allows partial submissions on enterprise tier forms', function () {
    $user = $this->actingAsEnterpriseUser();
    $workspace = $this->createUserWorkspace($user);
    $form = $this->createForm($user, $workspace, [
        'enable_partial_submissions' => true
    ]);

    $formData = $this->generateFormSubmissionData($form, ['text' => 'Test']);
    $formData['is_partial'] = true;

    $response = $this->postJson(route('forms.answer', $form->slug), $formData)
        ->assertSuccessful()
        ->assertJson([
            'type' => 'success',
        ]);

    $submissionHash = $response->json('submission_hash');
    expect($submissionHash)->not->toBeEmpty();

    $submission = FormSubmission::first();
    expect($submission->status)->toBe(FormSubmission::STATUS_PARTIAL);
});

it('cannot revert a completed submission back to partial', function () {
    $user = $this->actingAsBusinessUser();
    $workspace = $this->createUserWorkspace($user);
    $form = $this->createForm($user, $workspace, [
        'enable_partial_submissions' => true
    ]);

    $targetField = collect($form->properties)->where('name', 'Name')->first();

    // Step 1: Create a partial submission
    $formData = $this->generateFormSubmissionData($form, [$targetField['id'] => 'Initial']);
    $formData['is_partial'] = true;

    $partialResponse = $this->postJson(route('forms.answer', $form->slug), $formData)
        ->assertSuccessful();

    $submissionHash = $partialResponse->json('submission_hash');

    // Step 2: Complete the submission
    $completeData = $this->generateFormSubmissionData($form, [$targetField['id'] => 'Complete']);
    $completeData['submission_hash'] = $submissionHash;

    $this->postJson(route('forms.answer', $form->slug), $completeData)
        ->assertSuccessful();

    // Verify it's completed
    $submission = FormSubmission::first();
    expect($submission->status)->toBe(FormSubmission::STATUS_COMPLETED);
    expect($submission->data[$targetField['id']])->toBe('Complete');

    // Step 3: Try to send another partial submission with the same hash
    $latePartialData = $this->generateFormSubmissionData($form, [$targetField['id'] => 'Late partial update']);
    $latePartialData['is_partial'] = true;
    $latePartialData['submission_hash'] = $submissionHash;

    $this->postJson(route('forms.answer', $form->slug), $latePartialData)
        ->assertSuccessful();

    // Verify the completed submission was not overwritten
    $submission->refresh();
    expect($submission->status)->toBe(FormSubmission::STATUS_COMPLETED);
    expect($submission->data[$targetField['id']])->toBe('Complete');
});

it('validates required fields when is_partial sent to form without partial submissions', function () {
    $user = $this->actingAsBusinessUser();
    $workspace = $this->createUserWorkspace($user);
    $form = $this->createForm($user, $workspace, [
        'enable_partial_submissions' => false,
    ]);

    // Make a required field to ensure validation fires
    $properties = $form->properties;
    $properties[0]['required'] = true;
    $form->update(['properties' => $properties]);

    // Send only is_partial with no field data — should be rejected by validation
    $this->postJson(route('forms.answer', $form->slug), [
        'is_partial' => true,
    ])->assertStatus(422);
});

it('validates required fields when is_partial sent on free tier form', function () {
    $user = $this->actingAsUser();
    $workspace = $this->createUserWorkspace($user);
    $form = $this->createForm($user, $workspace, [
        'enable_partial_submissions' => true,
    ]);

    $properties = $form->properties;
    $properties[0]['required'] = true;
    $form->update(['properties' => $properties]);

    // Free tier cannot use partial submissions, so validation should run
    $this->postJson(route('forms.answer', $form->slug), [
        'is_partial' => true,
    ])->assertStatus(422);
});

it('validates required fields when is_partial is false on partial-enabled form', function () {
    $user = $this->actingAsBusinessUser();
    $workspace = $this->createUserWorkspace($user);
    $form = $this->createForm($user, $workspace, [
        'enable_partial_submissions' => true,
    ]);

    $properties = $form->properties;
    $properties[0]['required'] = true;
    $form->update(['properties' => $properties]);

    // is_partial: false is a completed submission — required fields must be validated
    $this->postJson(route('forms.answer', $form->slug), [
        'is_partial' => false,
    ])->assertStatus(422);
});

it('stores completed submission when is_partial is string false on partial-enabled form', function () {
    $user = $this->actingAsBusinessUser();
    $workspace = $this->createUserWorkspace($user);
    $form = $this->createForm($user, $workspace, [
        'enable_partial_submissions' => true,
    ]);

    $formData = $this->generateFormSubmissionData($form);
    $formData['is_partial'] = 'false';

    $this->postJson(route('forms.answer', $form->slug), $formData)
        ->assertSuccessful();

    $submission = FormSubmission::first();
    expect($submission->status)->toBe(FormSubmission::STATUS_COMPLETED);
});
