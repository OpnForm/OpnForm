<?php

use App\Models\Forms\Form;
use App\Models\Version;

it('can list form versions', function () {
    $user = $this->actingAsProUser();
    $workspace = $this->createUserWorkspace($user);
    $form = $this->createForm($user, $workspace);

    // Update form to create a version
    $form->title = 'Updated Title';
    $form->save();

    $response = $this->getJson(route('versions.index', ['model_type' => 'form', 'id' => $form->id]))
        ->assertSuccessful();

    // Response is a direct array from VersionResource::collection
    $data = $response->json();
    expect($data)->toBeArray();
});

it('can list submission versions', function () {
    $user = $this->actingAsProUser();
    $workspace = $this->createUserWorkspace($user);
    $form = $this->createForm($user, $workspace);

    // Create and update a submission
    $formData = $this->generateFormSubmissionData($form);
    $this->postJson(route('forms.answer', $form->slug), $formData)
        ->assertSuccessful();

    $submission = $form->submissions()->first();

    // Update submission to create a version
    $submission->data = array_merge($submission->data, ['updated' => 'value']);
    $submission->save();

    $response = $this->getJson(route('versions.index', ['model_type' => 'submission', 'id' => $submission->id]))
        ->assertSuccessful();

    $data = $response->json();
    expect($data)->toBeArray();
});

it('cannot list versions for unauthorized form', function () {
    $user = $this->actingAsUser();
    $otherUser = $this->createUser();
    $workspace = $this->createUserWorkspace($otherUser);
    $form = $this->createForm($otherUser, $workspace);

    $this->getJson(route('versions.index', ['model_type' => 'form', 'id' => $form->id]))
        ->assertStatus(403);
});

it('returns error for invalid model type', function () {
    $user = $this->actingAsUser();

    // Invalid model type returns 400 (abort_unless check)
    $this->getJson(route('versions.index', ['model_type' => 'invalid', 'id' => 1]))
        ->assertStatus(400);
});

it('cannot restore version as non-pro user', function () {
    $user = $this->actingAsUser();
    $workspace = $this->createUserWorkspace($user);
    $form = $this->createForm($user, $workspace, ['title' => 'Original Title']);

    // Update to create version
    $form->title = 'Updated Title';
    $form->save();

    $version = $form->versions()->latest()->first();

    // Non-pro user should get error response
    $response = $this->postJson(route('versions.restore', ['versionId' => $version->version_id]));

    // Should fail with pro user requirement error
    expect($response->json('message'))->toBe('You need to be a Pro user to restore this version');
});

it('cannot restore version for unauthorized form', function () {
    $user = $this->actingAsProUser();
    $otherUser = $this->createUser();
    $workspace = $this->createUserWorkspace($otherUser);
    $form = $this->createForm($otherUser, $workspace);

    // Update to create version
    $form->title = 'Updated Title';
    $form->save();

    $version = $form->versions()->latest()->first();

    // Pro user should not be able to restore another user's form version
    $this->postJson(route('versions.restore', ['versionId' => $version->version_id]))
        ->assertStatus(403);
});

it('cannot restore non-existent version', function () {
    $user = $this->actingAsProUser();

    $this->postJson(route('versions.restore', ['versionId' => 99999]))
        ->assertStatus(404);
});

it('returns 404 for non-existent form versions', function () {
    $user = $this->actingAsUser();

    $this->getJson(route('versions.index', ['model_type' => 'form', 'id' => 99999]))
        ->assertStatus(404);
});

it('version resource includes user data when available', function () {
    $user = $this->actingAsProUser();
    $workspace = $this->createUserWorkspace($user);
    $form = $this->createForm($user, $workspace);

    // Update to create version
    $form->title = 'Updated Title';
    $form->save();

    $response = $this->getJson(route('versions.index', ['model_type' => 'form', 'id' => $form->id]))
        ->assertSuccessful();

    $data = $response->json();
    if (count($data) > 0) {
        // User should be present since we just created the version
        expect($data[0]['user'])->not->toBeNull();
        expect($data[0]['user']['id'])->toBe($user->id);
    }
});

it('limits versions returned to prevent performance issues', function () {
    $user = $this->actingAsProUser();
    $workspace = $this->createUserWorkspace($user);
    $form = $this->createForm($user, $workspace);

    // Create many versions
    for ($i = 0; $i < 60; $i++) {
        $form->title = "Title {$i}";
        $form->save();
    }

    $response = $this->getJson(route('versions.index', ['model_type' => 'form', 'id' => $form->id]))
        ->assertSuccessful();

    $data = $response->json();
    // Should be limited (max 50 before filtering)
    expect(count($data))->toBeLessThanOrEqual(50);
});
