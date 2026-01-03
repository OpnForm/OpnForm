<?php

use App\Models\Forms\FormSubmission;
use Illuminate\Support\Str;
use Vinkla\Hashids\Facades\Hashids;

describe('Submission UUID Identifiers', function () {
    beforeEach(function () {
        $this->user = $this->actingAsProUser();
        $this->workspace = $this->createUserWorkspace($this->user);
        $this->form = $this->createForm($this->user, $this->workspace, [
            'editable_submissions' => true,
        ]);
    });

    it('generates UUID for new submissions', function () {
        $submissionData = $this->generateFormSubmissionData($this->form);

        $response = $this->postJson(route('forms.answer', $this->form), $submissionData)
            ->assertSuccessful();

        $submissionId = $response->json('submission_id');
        expect($submissionId)->not->toBeNull();
        expect(Str::isUuid($submissionId))->toBeTrue();

        // Verify UUID is stored in database
        $submission = $this->form->submissions()->first();
        expect($submission->public_id)->not->toBeNull();
        expect(Str::isUuid($submission->public_id))->toBeTrue();
        expect($submission->public_id)->toBe($submissionId);
    });

    it('fetches submission using UUID', function () {
        // Create a submission
        $submissionData = $this->generateFormSubmissionData($this->form);
        $response = $this->postJson(route('forms.answer', $this->form), $submissionData)
            ->assertSuccessful();

        $uuid = $response->json('submission_id');

        // Fetch using UUID
        $this->actingAsGuest();
        $fetchResponse = $this->getJson(route('forms.fetchSubmission', [
            'form' => $this->form->slug,
            'submission_id' => $uuid,
        ]))->assertSuccessful();

        // Verify response contains submission data (submission_id is hidden in public access)
        expect($fetchResponse->json('data'))->not->toBeNull();
    });

    it('returns 404 for invalid UUID', function () {
        $invalidUuid = Str::uuid()->toString();

        $this->actingAsGuest();
        $this->getJson(route('forms.fetchSubmission', [
            'form' => $this->form->slug,
            'submission_id' => $invalidUuid,
        ]))->assertStatus(404);
    });

    it('returns 404 when editing submission with invalid UUID', function () {
        $invalidUuid = Str::uuid()->toString();

        $submissionData = $this->generateFormSubmissionData($this->form);
        $submissionData['submission_id'] = $invalidUuid;

        $this->actingAsGuest();
        $this->postJson(route('forms.answer', $this->form), $submissionData)
            ->assertStatus(404);
    });

    it('allows editing submission using UUID', function () {
        // Create initial submission
        $initialData = $this->generateFormSubmissionData($this->form);
        $response = $this->postJson(route('forms.answer', $this->form), $initialData)
            ->assertSuccessful();

        $uuid = $response->json('submission_id');

        // Edit using UUID
        $editData = $this->generateFormSubmissionData($this->form);
        $editData['submission_id'] = $uuid;

        $this->postJson(route('forms.answer', $this->form), $editData)
            ->assertSuccessful();

        // Verify only one submission exists (was updated, not created new)
        expect($this->form->submissions()->count())->toBe(1);
    });
});

describe('Legacy Hashid Backward Compatibility', function () {
    beforeEach(function () {
        $this->user = $this->actingAsProUser();
        $this->workspace = $this->createUserWorkspace($this->user);
        $this->form = $this->createForm($this->user, $this->workspace, [
            'editable_submissions' => true,
        ]);
    });

    it('allows fetching legacy submission without UUID using hashid', function () {
        // Create a legacy submission (without UUID)
        $submission = new FormSubmission();
        $submission->form_id = $this->form->id;
        $submission->data = ['test' => 'data'];
        $submission->public_id = null; // Legacy submission
        $submission->save();

        $hashid = Hashids::encode($submission->id);

        $this->actingAsGuest();
        $this->getJson(route('forms.fetchSubmission', [
            'form' => $this->form->slug,
            'submission_id' => $hashid,
        ]))->assertSuccessful();
    });

    it('rejects hashid access when submission has UUID', function () {
        // Create a submission with UUID
        $submission = new FormSubmission();
        $submission->form_id = $this->form->id;
        $submission->data = ['test' => 'data'];
        $submission->public_id = Str::uuid()->toString();
        $submission->save();

        $hashid = Hashids::encode($submission->id);

        $this->actingAsGuest();
        $this->getJson(route('forms.fetchSubmission', [
            'form' => $this->form->slug,
            'submission_id' => $hashid,
        ]))->assertStatus(404);
    });

    it('returns 404 for invalid hashid', function () {
        $this->actingAsGuest();
        $this->getJson(route('forms.fetchSubmission', [
            'form' => $this->form->slug,
            'submission_id' => 'invalid-hashid-xyz',
        ]))->assertStatus(404);
    });
});

describe('Submission Fetch Authorization', function () {
    it('returns 404 for non-public forms', function () {
        $user = $this->actingAsProUser();
        $workspace = $this->createUserWorkspace($user);
        $form = $this->createForm($user, $workspace, [
            'editable_submissions' => true,
            'visibility' => 'private',
        ]);

        $submission = new FormSubmission();
        $submission->form_id = $form->id;
        $submission->data = ['test' => 'data'];
        $submission->public_id = Str::uuid()->toString();
        $submission->save();

        $this->actingAsGuest();
        $this->getJson(route('forms.fetchSubmission', [
            'form' => $form->slug,
            'submission_id' => $submission->public_id,
        ]))->assertStatus(404);
    });

    it('returns 403 when editable submissions disabled', function () {
        $user = $this->actingAsProUser();
        $workspace = $this->createUserWorkspace($user);
        $form = $this->createForm($user, $workspace, [
            'editable_submissions' => false,
        ]);

        $submission = new FormSubmission();
        $submission->form_id = $form->id;
        $submission->data = ['test' => 'data'];
        $submission->public_id = Str::uuid()->toString();
        $submission->save();

        $this->actingAsGuest();
        $this->getJson(route('forms.fetchSubmission', [
            'form' => $form->slug,
            'submission_id' => $submission->public_id,
        ]))->assertStatus(403);
    });
});

describe('Partial Submissions with UUID', function () {
    it('generates UUID for partial submissions', function () {
        $user = $this->actingAsProUser();
        $workspace = $this->createUserWorkspace($user);
        $form = $this->createForm($user, $workspace, [
            'enable_partial_submissions' => true,
        ]);

        $submissionData = $this->generateFormSubmissionData($form);
        $submissionData['is_partial'] = true;

        $response = $this->postJson(route('forms.answer', $form), $submissionData)
            ->assertSuccessful();

        $submissionHash = $response->json('submission_hash');
        expect($submissionHash)->not->toBeNull();
        expect(Str::isUuid($submissionHash))->toBeTrue();

        // Verify UUID is stored
        $submission = $form->submissions()->first();
        expect($submission->public_id)->toBe($submissionHash);
    });
});
