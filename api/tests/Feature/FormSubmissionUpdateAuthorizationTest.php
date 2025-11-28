<?php

use App\Models\Forms\FormSubmission;
use Laravel\Sanctum\Sanctum;

it('allows owner to update a submission', function () {
    $user = $this->actingAsUser();
    $workspace = $this->createUserWorkspace($user);
    $form = $this->createForm($user, $workspace);

    $submission = new FormSubmission([
        'data' => [],
        'completion_time' => null,
        'status' => FormSubmission::STATUS_COMPLETED,
    ]);
    $submission->form_id = $form->id;
    $submission->save();

    $this->putJson(route('open.forms.submissions.update', [
        'form' => $form->id,
        'submission_id' => $submission->id,
    ]), [
        // No field values required as defaults are non-required
    ])->assertOk();
});

it('forbids readonly member from updating a submission', function () {
    $owner = $this->actingAsUser();
    $workspace = $this->createUserWorkspace($owner);
    $form = $this->createForm($owner, $workspace);

    $submission = new FormSubmission([
        'data' => [],
        'completion_time' => null,
        'status' => FormSubmission::STATUS_COMPLETED,
    ]);
    $submission->form_id = $form->id;
    $submission->save();

    $readonly = $this->createUser();
    $workspace->users()->attach($readonly, ['role' => 'readonly']);

    $this->actingAsGuest();
    $this->actingAsUser($readonly);

    $this->putJson(route('open.forms.submissions.update', [
        'form' => $form->id,
        'submission_id' => $submission->id,
    ]), [])->assertForbidden();
});

it('forbids token without forms-write from updating a submission', function () {
    $user = $this->createProUser();
    $workspace = $this->createUserWorkspace($user);
    $form = $this->createForm($user, $workspace);

    $submission = new FormSubmission([
        'data' => [],
        'completion_time' => null,
        'status' => FormSubmission::STATUS_COMPLETED,
    ]);
    $submission->form_id = $form->id;
    $submission->save();

    Sanctum::actingAs($user, ['forms-read']);

    $this->putJson(route('open.forms.submissions.update', [
        'form' => $form->id,
        'submission_id' => $submission->id,
    ]), [])->assertForbidden();
});

it('allows token with forms-write to update a submission', function () {
    $user = $this->createProUser();
    $workspace = $this->createUserWorkspace($user);
    $form = $this->createForm($user, $workspace);

    $submission = new FormSubmission([
        'data' => [],
        'completion_time' => null,
        'status' => FormSubmission::STATUS_COMPLETED,
    ]);
    $submission->form_id = $form->id;
    $submission->save();

    Sanctum::actingAs($user, ['forms-write']);

    $this->putJson(route('open.forms.submissions.update', [
        'form' => $form->id,
        'submission_id' => $submission->id,
    ]), [])->assertOk();
});

it('404s when trying to update a submission that does not belong to the form', function () {
    $user = $this->actingAsUser();
    $workspace = $this->createUserWorkspace($user);
    $formA = $this->createForm($user, $workspace);
    $formB = $this->createForm($user, $workspace);

    $submission = new FormSubmission([
        'data' => [],
        'completion_time' => null,
        'status' => FormSubmission::STATUS_COMPLETED,
    ]);
    $submission->form_id = $formA->id;
    $submission->save();

    $this->putJson(route('open.forms.submissions.update', [
        'form' => $formB->id,
        'submission_id' => $submission->id,
    ]), [])->assertNotFound();
});
