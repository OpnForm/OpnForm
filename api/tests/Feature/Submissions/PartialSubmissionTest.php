<?php

use App\Models\Forms\FormSubmission;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

it('can submit form partially and complete it later using submission hash', function () {
    $user = $this->actingAsProUser();
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
    $user = $this->actingAsProUser();
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

it('calculates stats correctly for partial vs completed submissions', function () {
    $user = $this->actingAsProUser();
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

    $user = $this->actingAsProUser();
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

    $user = $this->actingAsProUser();
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
    $user = $this->actingAsProUser();
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
    $user = $this->actingAsProUser();
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

it('submits as completed on non-pro forms', function () {
    $user = $this->actingAsUser();
    $workspace = $this->createUserWorkspace($user);

    // Create non-pro form with partial submissions enabled
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

    // Verify submission was saved as completed
    $submission = FormSubmission::first();
    expect($submission->status)->toBe(FormSubmission::STATUS_COMPLETED);
});

it('cannot revert a completed submission back to partial', function () {
    $user = $this->actingAsProUser();
    $workspace = $this->createUserWorkspace($user);
    $form = $this->createForm($user, $workspace, [
        'enable_partial_submissions' => true
    ]);

    // Step 1: Create a partial submission
    $formData = $this->generateFormSubmissionData($form, ['text' => 'Initial']);
    $formData['is_partial'] = true;

    $partialResponse = $this->postJson(route('forms.answer', $form->slug), $formData)
        ->assertSuccessful();

    $submissionHash = $partialResponse->json('submission_hash');

    // Step 2: Complete the submission
    $completeData = $this->generateFormSubmissionData($form, ['text' => 'Complete']);
    $completeData['submission_hash'] = $submissionHash;

    $this->postJson(route('forms.answer', $form->slug), $completeData)
        ->assertSuccessful();

    // Verify it's completed
    $submission = FormSubmission::first();
    expect($submission->status)->toBe(FormSubmission::STATUS_COMPLETED);

    // Step 3: Try to send another partial submission with the same hash
    // This simulates a race condition where a delayed partial request arrives after completion
    $latePartialData = $this->generateFormSubmissionData($form, ['text' => 'Late partial update']);
    $latePartialData['is_partial'] = true;
    $latePartialData['submission_hash'] = $submissionHash;

    $this->postJson(route('forms.answer', $form->slug), $latePartialData)
        ->assertSuccessful();

    // Verify the submission status was NOT reverted to partial
    $submission->refresh();
    expect($submission->status)->toBe(FormSubmission::STATUS_COMPLETED);
});
