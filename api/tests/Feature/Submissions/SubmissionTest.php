<?php

it('can delete form submission', function () {
    $user = $this->actingAsUser();
    $workspace = $this->createUserWorkspace($user);
    $form = $this->createForm($user, $workspace);
    $formData = $this->generateFormSubmissionData($form, ['text' => 'John']);
    $this->postJson(route('forms.answer', $form->slug), $formData)
        ->assertSuccessful()
        ->assertJson([
            'type' => 'success',
            'message' => 'Form submission saved.',
        ]);
    $submission = $form->submissions()->first();
    $this->deleteJson(route('open.forms.submissions.destroy', ['form' => $form, 'submission_id' => $submission->id]))
        ->assertSuccessful()
        ->assertJson([
            'type' => 'success',
            'message' => 'Record successfully removed.',
        ]);
    expect($form->submissions()->count())->toBe(0);
});

it('can delete multiple form submissions', function () {
    $user = $this->actingAsUser();
    $workspace = $this->createUserWorkspace($user);
    $form = $this->createForm($user, $workspace);

    // Create 5 submissions
    for ($i = 1; $i <= 5; $i++) {
        $form->submissions()->create();
    }

    // Get 2 random submission ids
    $submissionIds = $form->submissions()->pluck('id')->random(2)->toArray();

    // Delete 2 submissions
    $this->postJson(route('open.forms.submissions.destroy-multi', ['form' => $form]), ['submissionIds' => $submissionIds])
        ->assertSuccessful()
        ->assertJson([
            'type' => 'success',
            'message' => 'Records successfully removed.',
        ]);

    // Check if the remaining submissions are still there
    expect($form->submissions()->count())->toBe(3);
});
