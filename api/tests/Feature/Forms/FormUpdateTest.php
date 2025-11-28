<?php

use Illuminate\Support\Str;

it('can update form with existing record', function () {
    $user = $this->actingAsProUser();
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
    $user = $this->actingAsProUser();
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
