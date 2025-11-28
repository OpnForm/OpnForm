<?php

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
