<?php

use App\Mcp\Servers\OpnFormServer;
use App\Mcp\Tools\Submissions\GetSubmissionTool;
use App\Mcp\Tools\Submissions\ListSubmissionsTool;
use App\Models\Forms\FormSubmission;

function createTestSubmission($testCase, $form)
{
    $submission = new FormSubmission();
    $submission->form_id = $form->id;
    $submission->data = $testCase->generateFormSubmissionData($form, [], true);
    $submission->status = FormSubmission::STATUS_COMPLETED;
    $submission->save();

    return $submission;
}

describe('list-submissions tool', function () {
    it('returns submissions for a form', function () {
        $user = $this->actingAsUser();
        $workspace = $this->createUserWorkspace($user);
        $form = $this->createForm($user, $workspace);

        createTestSubmission($this, $form);

        OpnFormServer::actingAs($user)
            ->tool(ListSubmissionsTool::class, [
                'form_id' => (string) $form->id,
            ])
            ->assertOk()
            ->assertSee('submissions');
    });

    it('returns empty list for form with no submissions', function () {
        $user = $this->actingAsUser();
        $workspace = $this->createUserWorkspace($user);
        $form = $this->createForm($user, $workspace);

        OpnFormServer::actingAs($user)
            ->tool(ListSubmissionsTool::class, [
                'form_id' => (string) $form->id,
            ])
            ->assertOk();
    });

    it('requires form_id', function () {
        $user = $this->actingAsUser();

        OpnFormServer::actingAs($user)
            ->tool(ListSubmissionsTool::class)
            ->assertHasErrors();
    });

    it('rejects access to submissions of another user form', function () {
        $user = $this->actingAsUser();
        $otherUser = $this->createUser();
        $otherWorkspace = $this->createUserWorkspace($otherUser);
        $form = $this->createForm($otherUser, $otherWorkspace);

        OpnFormServer::actingAs($user)
            ->tool(ListSubmissionsTool::class, [
                'form_id' => (string) $form->id,
            ])
            ->assertHasErrors();
    });
});

describe('get-submission tool', function () {
    it('returns a specific submission', function () {
        $user = $this->actingAsUser();
        $workspace = $this->createUserWorkspace($user);
        $form = $this->createForm($user, $workspace);

        $submission = createTestSubmission($this, $form);

        OpnFormServer::actingAs($user)
            ->tool(GetSubmissionTool::class, [
                'form_id' => (string) $form->id,
                'submission_id' => $submission->id,
            ])
            ->assertOk();
    });

    it('requires form_id and submission_id', function () {
        $user = $this->actingAsUser();

        OpnFormServer::actingAs($user)
            ->tool(GetSubmissionTool::class)
            ->assertHasErrors();
    });

    it('rejects access to another user submission', function () {
        $user = $this->actingAsUser();
        $otherUser = $this->createUser();
        $otherWorkspace = $this->createUserWorkspace($otherUser);
        $form = $this->createForm($otherUser, $otherWorkspace);

        $submission = createTestSubmission($this, $form);

        OpnFormServer::actingAs($user)
            ->tool(GetSubmissionTool::class, [
                'form_id' => (string) $form->id,
                'submission_id' => $submission->id,
            ])
            ->assertHasErrors();
    });
});
