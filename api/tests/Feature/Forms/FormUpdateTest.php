<?php

use Illuminate\Support\Str;

it('can update form with existing record', function () {
    $user = $this->actingAsBusinessUser();
    $workspace = $this->createUserWorkspace($user);
    $form = $this->createForm($user, $workspace, [
        'editable_submissions' => true,
    ]);

    $nameProperty = collect($form->properties)->filter(function ($property) {
        return $property['name'] == 'Name';
    })->first();

    $response = $this->postJson(route('forms.answer', $form->slug), [$nameProperty['id'] => 'Testing'])
        ->assertSuccessful()
        ->assertJson([
            'type' => 'success',
            'message' => 'Form submission saved.',
        ]);
    $submissionId = $response->json('submission_id');
    expect($submissionId)->toBeString();

    if ($submissionId) {
        $formData = $this->generateFormSubmissionData($form, ['submission_id' => $submissionId, $nameProperty['id'] => 'Testing Updated']);
        $response = $this->postJson(route('forms.answer', $form->slug), $formData)
            ->assertSuccessful()
            ->assertJson([
                'type' => 'success',
                'message' => 'Form submission saved.',
            ]);
        $submissionId2 = $response->json('submission_id');
        expect($submissionId2)->toBeString();
        expect($submissionId2)->toBe($submissionId);

        $response = $this->getJson(route('forms.fetchSubmission', [$form->slug, $submissionId]))
            ->assertSuccessful();
        expect($response->json('data.' . $nameProperty['id']))->toBe('Testing Updated');
    }
});

it('can update form with existing record but generates_uuid field is not update', function () {
    $user = $this->actingAsBusinessUser();
    $workspace = $this->createUserWorkspace($user);
    $form = $this->createForm($user, $workspace, [
        'clear_empty_fields_on_update' => true,
        'editable_submissions' => true,
        'properties' => [
            [
                'id' => 'uuid_field',
                'type' => 'text',
                'generates_uuid' => true,
                'name' => 'UUID Field'
            ],
            [
                'id' => 'name',
                'type' => 'text',
                'name' => 'Name'
            ]
        ]
    ]);

    $response = $this->postJson(route('forms.answer', $form->slug), ['name' => 'Testing', 'uuid_field' => null])
        ->assertSuccessful()
        ->assertJson([
            'type' => 'success',
            'message' => 'Form submission saved.',
        ]);
    $submissionId = $response->json('submission_id');
    expect($submissionId)->toBeString();
    $response = $this->getJson(route('forms.fetchSubmission', [$form->slug, $submissionId]))
        ->assertSuccessful();
    $uuid = $response->json('data.uuid_field');
    expect(Str::isUuid($uuid))->toBeTrue();

    if ($submissionId) {
        $formData = $this->generateFormSubmissionData($form, ['submission_id' => $submissionId, 'name' => 'Testing Updated', 'uuid_field' => $uuid]);
        $response = $this->postJson(route('forms.answer', $form->slug), $formData)
            ->assertSuccessful()
            ->assertJson([
                'type' => 'success',
                'message' => 'Form submission saved.',
            ]);
        $submissionId2 = $response->json('submission_id');
        expect($submissionId2)->toBeString();
        expect($submissionId2)->toBe($submissionId);

        $response = $this->getJson(route('forms.fetchSubmission', [$form->slug, $submissionId]))
            ->assertSuccessful();
        expect($response->json('data.name'))->toBe('Testing Updated');
        $uuid2 = $response->json('data.uuid_field');
        expect($uuid2)->toBe($uuid);
    }
});

it('can set a custom form slug when self hosted', function () {
    config(['app.self_hosted' => true]);
    $user = $this->actingAsUser();
    $workspace = $this->createUserWorkspace($user);
    $form = $this->createForm($user, $workspace);

    $this->putJson(route('open.forms.regenerate-link', [$form->id, 'custom']), ['slug' => 'my-custom-form'])
        ->assertSuccessful()
        ->assertJsonPath('form.slug', 'my-custom-form');

    expect($form->fresh()->slug)->toBe('my-custom-form');
});

it('rejects invalid or duplicate custom form slugs', function () {
    config(['app.self_hosted' => true]);
    $user = $this->actingAsUser();
    $workspace = $this->createUserWorkspace($user);
    $form = $this->createForm($user, $workspace);
    $otherForm = $this->createForm($user, $workspace);

    foreach ([$otherForm->slug, '', 'Has Spaces', 'UPPER', '-leading', 'double--hyphen', str_repeat('a', 101)] as $invalid) {
        $this->putJson(route('open.forms.regenerate-link', [$form->id, 'custom']), ['slug' => $invalid])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['slug']);
    }

    $this->putJson(route('open.forms.regenerate-link', [$form->id, 'custom']), ['slug' => $form->slug])
        ->assertSuccessful();
});

it('cannot set a custom form slug when not self hosted', function () {
    config(['app.self_hosted' => false]);
    $user = $this->actingAsUser();
    $workspace = $this->createUserWorkspace($user);
    $form = $this->createForm($user, $workspace);
    $originalSlug = $form->slug;

    $this->putJson(route('open.forms.regenerate-link', [$form->id, 'custom']), ['slug' => 'my-custom-form'])
        ->assertForbidden();

    expect($form->fresh()->slug)->toBe($originalSlug);
});

it('cannot set a custom form slug on a form the user cannot update', function () {
    config(['app.self_hosted' => true]);
    $owner = $this->createUser();
    $workspace = $this->createUserWorkspace($owner);
    $form = $this->createForm($owner, $workspace);

    $this->actingAsUser();
    $this->putJson(route('open.forms.regenerate-link', [$form->id, 'custom']), ['slug' => 'stolen-form'])
        ->assertForbidden();
});
