<?php

use App\Models\Forms\FormSubmission;
use App\Service\Forms\SubmissionUrlService;
use Illuminate\Support\Str;
use Vinkla\Hashids\Facades\Hashids;

describe('Submission UUID Identifiers', function () {
    beforeEach(function () {
        $this->user = $this->actingAsBusinessUser();
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

    it('does not allow editing submission using raw numeric id', function () {
        $nameField = collect($this->form->properties)->where('name', 'Name')->first();

        $initialData = $this->generateFormSubmissionData($this->form, [
            $nameField['id'] => 'ORIGINAL_DATA',
        ]);
        $this->postJson(route('forms.answer', $this->form), $initialData)
            ->assertSuccessful();

        $submission = $this->form->submissions()->first();

        $editData = $this->generateFormSubmissionData($this->form, [
            $nameField['id'] => 'POISONED_DATA',
        ]);
        $editData['submission_id'] = (string) $submission->id;

        $this->actingAsGuest();
        $this->postJson(route('forms.answer', $this->form), $editData)
            ->assertStatus(404);

        expect($this->form->submissions()->count())->toBe(1);

        $submission->refresh();
        expect($submission->data[$nameField['id']])->toBe('ORIGINAL_DATA');
    });
});

describe('Legacy Hashid Rejection', function () {
    beforeEach(function () {
        $this->user = $this->actingAsBusinessUser();
        $this->workspace = $this->createUserWorkspace($this->user);
        $this->form = $this->createForm($this->user, $this->workspace, [
            'editable_submissions' => true,
        ]);
    });

    it('rejects fetching legacy submission without UUID using hashid', function () {
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
        ]))->assertStatus(404);
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

    it('rejects hashid update when submission has UUID', function () {
        $submission = new FormSubmission();
        $submission->form_id = $this->form->id;
        $submission->data = ['test' => 'data'];
        $submission->public_id = Str::uuid()->toString();
        $submission->save();

        $hashid = Hashids::encode($submission->id);

        $editData = $this->generateFormSubmissionData($this->form);
        $editData['submission_id'] = $hashid;

        $this->actingAsGuest();
        $this->postJson(route('forms.answer', $this->form), $editData)
            ->assertStatus(404);
    });

    it('rejects hashid update for legacy submission without UUID', function () {
        $submission = new FormSubmission();
        $submission->form_id = $this->form->id;
        $submission->data = ['test' => 'data'];
        $submission->public_id = null;
        $submission->save();

        $hashid = Hashids::encode($submission->id);

        $editData = $this->generateFormSubmissionData($this->form);
        $editData['submission_id'] = $hashid;

        $this->postJson(route('forms.answer', $this->form), $editData)
            ->assertStatus(404);
    });

    it('generates a UUID identifier for legacy submissions when building edit links', function () {
        $submission = new FormSubmission();
        $submission->form_id = $this->form->id;
        $submission->data = ['test' => 'data'];
        $submission->public_id = null;
        $submission->save();

        $identifier = SubmissionUrlService::getSubmissionIdentifier($submission);

        expect(Str::isUuid($identifier))->toBeTrue();
        expect($submission->refresh()->public_id)->toBe($identifier);
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
        $user = $this->actingAsBusinessUser();
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
        $user = $this->actingAsBusinessUser();
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

describe('Submission update blocked when editable submissions disabled', function () {
    it('does not update legacy submission via hashid when editable_submissions is false', function () {
        $user = $this->actingAsBusinessUser();
        $workspace = $this->createUserWorkspace($user);
        $form = $this->createForm($user, $workspace, [
            'editable_submissions' => false,
        ]);

        $submission = new FormSubmission();
        $submission->form_id = $form->id;
        $submission->data = ['original' => 'data'];
        $submission->public_id = null;
        $submission->save();

        $originalData = $submission->data;
        $hashid = Hashids::encode($submission->id);

        $editData = $this->generateFormSubmissionData($form);
        $editData['submission_id'] = $hashid;

        $this->postJson(route('forms.answer', $form), $editData)
            ->assertSuccessful();

        // Should have created a new submission, not updated the existing one
        expect($form->submissions()->count())->toBe(2);
        $submission->refresh();
        expect($submission->data)->toBe($originalData);
    });

    it('does not update submission via UUID when editable_submissions is false', function () {
        $user = $this->actingAsBusinessUser();
        $workspace = $this->createUserWorkspace($user);
        $form = $this->createForm($user, $workspace, [
            'editable_submissions' => false,
        ]);

        $submission = new FormSubmission();
        $submission->form_id = $form->id;
        $submission->data = ['original' => 'data'];
        $submission->public_id = Str::uuid()->toString();
        $submission->save();

        $originalData = $submission->data;

        $editData = $this->generateFormSubmissionData($form);
        $editData['submission_id'] = $submission->public_id;

        $this->postJson(route('forms.answer', $form), $editData)
            ->assertSuccessful();

        // Should have created a new submission, not updated the existing one
        expect($form->submissions()->count())->toBe(2);
        $submission->refresh();
        expect($submission->data)->toBe($originalData);
    });

    it('rejects non-UUID submission identifier when only partial submissions are enabled', function () {
        $user = $this->actingAsBusinessUser();
        $workspace = $this->createUserWorkspace($user);
        $form = $this->createForm($user, $workspace, [
            'editable_submissions' => false,
            'enable_partial_submissions' => true,
        ]);

        $submission = new FormSubmission();
        $submission->form_id = $form->id;
        $submission->data = ['original' => 'data'];
        $submission->status = FormSubmission::STATUS_COMPLETED;
        $submission->public_id = null;
        $submission->save();

        $originalData = $submission->data;
        $hashid = Hashids::encode($submission->id);

        $editData = $this->generateFormSubmissionData($form);
        $editData['submission_id'] = $hashid;

        $this->postJson(route('forms.answer', $form), $editData)
            ->assertStatus(404);

        expect($form->submissions()->count())->toBe(1);
        $submission->refresh();
        expect($submission->status)->toBe(FormSubmission::STATUS_COMPLETED);
        expect($submission->data)->toBe($originalData);
    });
});

describe('Partial Submissions with UUID', function () {
    it('generates UUID for partial submissions', function () {
        $user = $this->actingAsBusinessUser();
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
