<?php

use App\Models\Forms\FormSubmission;
use App\Service\Storage\FilenameUrlEncoder;
use App\Service\Storage\FileUploadPathService;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;

it('can update form submission', function () {
    $user = $this->actingAsUser();
    $workspace = $this->createUserWorkspace($user);
    $form = $this->makeForm($user, $workspace);
    $form = $this->createForm($user, $workspace, [
        'closes_at' => \Carbon\Carbon::now()->addDays(1)->toDateTimeString(),
    ]);
    $formData = $this->generateFormSubmissionData($form, ['text' => 'John']);
    $textFieldId = array_keys($formData)[0];
    $updatedFormData = $formData;
    $updatedFormTextValue = 'Updated text';
    $updatedFormData[$textFieldId] = $updatedFormTextValue;
    $this->postJson(route('forms.answer', $form->slug), $formData)
        ->assertSuccessful()
        ->assertJson([
            'type' => 'success',
            'message' => 'Form submission saved.',
        ]);
    $submission = $form->submissions()->first();
    $updateResponse = $this->putJson(route('open.forms.submissions.update', ['form' => $form, 'submission_id' => $submission->id]), $updatedFormData)
        ->assertSuccessful()
        ->assertJson([
            'type' => 'success',
            'message' => 'Record successfully updated.',
        ]);
    $expectedTextString = $updateResponse->json('data')['data'][$textFieldId];
    expect($expectedTextString)->toBe($updatedFormTextValue);
    $updatedSubmission = $form->submissions()->first();
    expect($updatedSubmission->data[$textFieldId])->toBe($updatedFormTextValue);
});

it('cannot update form submission as non admin', function () {
    $secondUser = $this->createUser();
    $user = $this->actingAsUser();
    $workspace = $this->createUserWorkspace($user);
    $form = $this->makeForm($user, $workspace);
    $form = $this->createForm($user, $workspace, [
        'closes_at' => \Carbon\Carbon::now()->addDays(1)->toDateTimeString(),
    ]);
    $formData = $this->generateFormSubmissionData($form, ['text' => 'John']);
    $textFieldId = array_keys($formData)[0];
    $updatedFormData = $formData;
    $updatedFormTextValue = 'Updated text';
    $updatedFormData[$textFieldId] = $updatedFormTextValue;
    $this->postJson(route('forms.answer', $form->slug), $formData)
        ->assertSuccessful()
        ->assertJson([
            'type' => 'success',
            'message' => 'Form submission saved.',
        ]);
    $submission = $form->submissions()->first();
    $this->actingAs($secondUser);
    $updateResponse = $this->putJson(route('open.forms.submissions.update', ['form' => $form, 'submission_id' => $submission->id]), $updatedFormData)
        ->assertStatus(403);
});

it('keeps an existing upload canonical when an edited submission submits its signed URL', function () {
    Storage::fake('local');

    $user = $this->actingAsUser();
    $workspace = $this->createUserWorkspace($user);
    $form = $this->createForm($user, $workspace);
    $fileFieldId = collect($form->properties)->firstWhere('type', 'files')['id'];

    $fileName = 'receipt_550e8400-e29b-41d4-a716-446655440000.png';
    Storage::put(
        FileUploadPathService::getFileUploadPath($form->id, $fileName),
        base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/w8AAgMBAQEAAP8AAAAASUVORK5CYII=', true)
    );

    $submission = $form->submissions()->create([
        'data' => [$fileFieldId => [$fileName]],
        'status' => FormSubmission::STATUS_COMPLETED,
    ]);

    $signedUploadUrl = URL::publicSignedRoute(
        'open.forms.submissions.file',
        [$form->id, FilenameUrlEncoder::encode($fileName)]
    );

    $this->putJson(route('open.forms.submissions.update', [
        'form' => $form,
        'submission_id' => $submission->id,
    ]), [
        $fileFieldId => [$signedUploadUrl],
    ])->assertSuccessful();

    expect($submission->fresh()->data[$fileFieldId])->toBe([$fileName]);
});
