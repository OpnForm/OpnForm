<?php

it('rejects destroyMulti submission ids that do not belong to the target form', function () {
    $user = $this->actingAsProUser();
    $workspace = $this->createUserWorkspace($user);
    $formA = $this->createForm($user, $workspace);
    $formB = $this->createForm($user, $workspace);

    $foreignSubmission = $formB->submissions()->create();

    $this->postJson(route('open.forms.submissions.destroy-multi', $formA), [
        'submissionIds' => [$foreignSubmission->id],
    ])->assertStatus(422)
        ->assertJsonValidationErrors(['submissionIds.0']);
});
