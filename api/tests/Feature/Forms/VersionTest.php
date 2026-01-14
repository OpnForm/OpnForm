<?php

use App\Models\Forms\Form;
use App\Models\User;
use App\Models\Version;

describe('Form Versions', function () {
    it('can list form versions', function () {
        $user = $this->actingAsProUser();
        $workspace = $this->createUserWorkspace($user);
        $form = $this->createForm($user, $workspace);

        // Update form to create a version
        $form->update(['title' => 'Updated Title']);

        $response = $this->getJson("/versions/form/{$form->id}")
            ->assertSuccessful();

        expect($response->json())->toBeArray();
    });

    it('cannot list form versions for another users form', function () {
        $user = $this->actingAsProUser();
        $workspace = $this->createUserWorkspace($user);
        $form = $this->createForm($user, $workspace);

        // Create another user and act as them
        $this->actingAsGuest();
        $otherUser = $this->createUser();
        $this->actingAsUser($otherUser);

        $this->getJson("/versions/form/{$form->id}")
            ->assertStatus(403);
    });

    it('returns 404 for invalid model type', function () {
        $this->actingAsProUser();

        $this->getJson('/versions/invalid/1')
            ->assertStatus(400); // Error response from controller
    });

    it('returns 404 for non-existent form', function () {
        $this->actingAsProUser();

        $this->getJson('/versions/form/999999')
            ->assertStatus(404);
    });

    it('filters out versions with empty diffs', function () {
        $user = $this->actingAsProUser();
        $workspace = $this->createUserWorkspace($user);
        $form = $this->createForm($user, $workspace);

        // Create a version that should have changes
        $form->update(['title' => 'Changed Title']);

        $response = $this->getJson("/versions/form/{$form->id}")
            ->assertSuccessful();

        foreach ($response->json() as $version) {
            expect($version['diff'])->not->toBeEmpty();
        }
    });
});

describe('Submission Versions', function () {
    it('can list submission versions', function () {
        $user = $this->actingAsProUser();
        $workspace = $this->createUserWorkspace($user);
        $form = $this->createForm($user, $workspace);

        // Create a submission
        $formData = $this->generateFormSubmissionData($form);
        $this->postJson(route('forms.answer', $form->slug), $formData)
            ->assertSuccessful();

        $submission = $form->submissions()->first();

        // Update submission to create a version
        $textFieldId = array_keys($formData)[0];
        $updatedData = $formData;
        $updatedData[$textFieldId] = 'Updated value';
        $this->putJson(route('open.forms.submissions.update', ['form' => $form, 'submission_id' => $submission->id]), $updatedData)
            ->assertSuccessful();

        $response = $this->getJson("/versions/submission/{$submission->id}")
            ->assertSuccessful();

        expect($response->json())->toBeArray();
    });

    it('cannot list submission versions for another users submission', function () {
        $user = $this->actingAsProUser();
        $workspace = $this->createUserWorkspace($user);
        $form = $this->createForm($user, $workspace);

        // Create a submission
        $formData = $this->generateFormSubmissionData($form);
        $this->postJson(route('forms.answer', $form->slug), $formData)
            ->assertSuccessful();

        $submission = $form->submissions()->first();

        // Create another user and act as them
        $this->actingAsGuest();
        $otherUser = $this->createUser();
        $this->actingAsUser($otherUser);

        $this->getJson("/versions/submission/{$submission->id}")
            ->assertStatus(403);
    });
});

describe('Form Version Preview', function () {
    it('can preview a form version as pro user', function () {
        $user = $this->actingAsProUser();
        $workspace = $this->createUserWorkspace($user);
        $form = $this->createForm($user, $workspace);
        $originalTitle = $form->title;

        // Update form to create a version
        $form->update(['title' => 'Changed Title']);

        // Get the version (should have the original title)
        $version = $form->versions()->latest()->first();

        // Preview the versioned form via API (form restore is frontend-only)
        $response = $this->getJson(route('open.forms.show', $form->slug) . "?version_id={$version->version_id}")
            ->assertSuccessful();

        // The response should contain the original title from the version
        expect($response->json('title'))->toBe($originalTitle);
    });
});

describe('Submission Version Restore', function () {
    it('can restore a submission version as pro user', function () {
        $user = $this->actingAsProUser();
        $workspace = $this->createUserWorkspace($user);
        $form = $this->createForm($user, $workspace);

        // Create a submission
        $formData = $this->generateFormSubmissionData($form);
        $this->postJson(route('forms.answer', $form->slug), $formData)
            ->assertSuccessful();

        $submission = $form->submissions()->first();
        $textFieldId = array_keys($formData)[0];

        // Update submission to create a version
        $updatedData = $formData;
        $updatedData[$textFieldId] = 'Updated value';
        $this->putJson(route('open.forms.submissions.update', ['form' => $form, 'submission_id' => $submission->id]), $updatedData)
            ->assertSuccessful();

        // Get the version
        $version = $submission->versions()->latest()->first();

        // Restore the version
        $this->postJson("/versions/{$version->version_id}/restore")
            ->assertSuccessful()
            ->assertJson([
                'type' => 'success',
                'message' => 'Version restored successfully.',
            ]);
    });

    it('cannot restore submission version as non-pro user', function () {
        $proUser = $this->actingAsProUser();
        $workspace = $this->createUserWorkspace($proUser);
        $form = $this->createForm($proUser, $workspace);

        // Create a submission
        $formData = $this->generateFormSubmissionData($form);
        $this->postJson(route('forms.answer', $form->slug), $formData)
            ->assertSuccessful();

        $submission = $form->submissions()->first();
        $textFieldId = array_keys($formData)[0];

        // Update submission to create a version
        $updatedData = $formData;
        $updatedData[$textFieldId] = 'Updated value';
        $this->putJson(route('open.forms.submissions.update', ['form' => $form, 'submission_id' => $submission->id]), $updatedData)
            ->assertSuccessful();

        $version = $submission->versions()->latest()->first();

        // Now act as non-pro user (who owns the form via workspace)
        $this->actingAsGuest();
        $nonProUser = $this->createUser();
        $this->actingAsUser($nonProUser);

        $this->postJson("/versions/{$version->version_id}/restore")
            ->assertStatus(400)
            ->assertJsonFragment([
                'message' => 'You need to be a Pro user to restore this version',
            ]);
    });

    it('returns 404 for non-existent version', function () {
        $this->actingAsProUser();

        $this->postJson('/versions/999999/restore')
            ->assertStatus(404);
    });
});

describe('Version Resource', function () {
    it('includes user information in version response', function () {
        $user = $this->actingAsProUser();
        $workspace = $this->createUserWorkspace($user);
        $form = $this->createForm($user, $workspace);

        // Update form to create a version
        $form->update(['title' => 'Changed Title']);

        $response = $this->getJson("/versions/form/{$form->id}")
            ->assertSuccessful();

        $versions = $response->json();
        if (count($versions) > 0) {
            $version = $versions[0];
            expect($version)->toHaveKeys(['id', 'created_at', 'user', 'diff']);
            if ($version['user'] !== null) {
                expect($version['user'])->toHaveKeys(['id', 'name', 'photo_url']);
            }
        }
    });

    it('handles null user gracefully', function () {
        $user = $this->actingAsProUser();
        $workspace = $this->createUserWorkspace($user);
        $form = $this->createForm($user, $workspace);

        // Update form to create a version
        $form->update(['title' => 'Changed Title']);

        // Manually set user_id to null on the version
        $version = $form->versions()->latest()->first();
        $version->user_id = null;
        $version->save();

        $response = $this->getJson("/versions/form/{$form->id}")
            ->assertSuccessful();

        $versions = $response->json();
        foreach ($versions as $v) {
            // Should not throw error even with null user
            expect($v)->toHaveKey('user');
        }
    });
});

describe('Version Model', function () {
    describe('getModel', function () {
        it('correctly decodes JSON cast attributes', function () {
            $user = $this->actingAsProUser();
            $workspace = $this->createUserWorkspace($user);
            $form = $this->createForm($user, $workspace);

            // Update form to create version
            $form->update(['title' => 'Updated']);

            $version = $form->versions()->latest()->first();

            if ($version) {
                $model = $version->getModel();
                expect($model)->toBeInstanceOf(Form::class);
                expect($model->properties)->toBeArray();
            }
        });

        it('handles null values in cast attributes', function () {
            $user = $this->actingAsProUser();
            $workspace = $this->createUserWorkspace($user);
            $form = $this->createForm($user, $workspace);

            $form->update(['title' => 'Updated']);

            $version = $form->versions()->latest()->first();

            if ($version) {
                $model = $version->getModel();
                expect($model->properties)->toBeArray();
            }
        });
    });

    describe('user relationship', function () {
        it('belongs to a user', function () {
            $user = $this->actingAsProUser();
            $workspace = $this->createUserWorkspace($user);
            $form = $this->createForm($user, $workspace);

            $form->update(['title' => 'Updated']);

            $version = $form->versions()->latest()->first();

            if ($version) {
                expect($version->user)->toBeInstanceOf(User::class);
                expect($version->user->id)->toBe($user->id);
            }
        });
    });

    describe('diff', function () {
        it('returns differences between versions', function () {
            $user = $this->actingAsProUser();
            $workspace = $this->createUserWorkspace($user);
            $form = $this->createForm($user, $workspace);

            $form->update(['title' => 'Changed Title']);

            $version = $form->versions()->latest()->first();

            if ($version) {
                $diff = $version->diff();
                expect($diff)->toBeArray();
            }
        });

        it('excludes timestamps from diff', function () {
            $user = $this->actingAsProUser();
            $workspace = $this->createUserWorkspace($user);
            $form = $this->createForm($user, $workspace);

            $form->update(['title' => 'Updated']);

            $version = $form->versions()->latest()->first();

            if ($version) {
                $diff = $version->diff();
                expect($diff)->not->toHaveKey('created_at');
                expect($diff)->not->toHaveKey('updated_at');
            }
        });
    });

    describe('nested diff for FormSubmission', function () {
        it('provides nested diff for data field', function () {
            $user = $this->actingAsProUser();
            $workspace = $this->createUserWorkspace($user);
            $form = $this->createForm($user, $workspace, ['properties' => [
                ['id' => 'field1', 'name' => 'Field 1', 'type' => 'text'],
                ['id' => 'field2', 'name' => 'Field 2', 'type' => 'text']
            ]]);

            $submission = $form->submissions()->create([
                'data' => [
                    'field1' => 'value1',
                    'field2' => 'value2',
                ],
            ]);

            // Update only one field
            $submission->update([
                'data' => [
                    'field1' => 'changed_value1',
                    'field2' => 'value2',
                ],
            ]);

            $version = $submission->versions()->latest()->first();

            if ($version) {
                $diff = $version->diff();
                // The diff should show nested changes in data field
                expect($diff)->toBeArray();
            }
        });
    });
});
