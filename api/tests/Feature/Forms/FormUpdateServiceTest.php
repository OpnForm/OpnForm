<?php

use App\Http\Resources\FormResource;
use App\Service\Forms\FormUpdateService;

it('centralizes persistence and tracks only removed input properties', function () {
    $user = $this->actingAsUser();
    $workspace = $this->createUserWorkspace($user);
    $previouslyRemoved = ['id' => 'old-email', 'name' => 'Old email', 'type' => 'email'];
    $form = $this->createForm($user, $workspace, [
        'properties' => [
            ['id' => 'kept-name', 'name' => 'Name', 'type' => 'text'],
            ['id' => 'removed-email', 'name' => 'Email', 'type' => 'email'],
            ['id' => 'removed-copy', 'name' => 'Introduction', 'type' => 'nf-text', 'content' => '<p>Hello</p>'],
        ],
        'removed_properties' => [$previouslyRemoved],
    ]);
    $data = (new FormResource($form))->toArray(request());
    $data['title'] = 'Updated through the canonical service';
    $data['clear_empty_fields_on_update'] = false;
    $data['properties'] = [
        ['id' => 'kept-name', 'name' => 'Name', 'type' => 'text'],
    ];

    $updated = app(FormUpdateService::class)->update($form, $data);
    $removedIds = collect($form->refresh()->removed_properties)->pluck('id')->all();

    expect($updated)->toHaveKeys(['form', 'cleanings', 'cleaning_keys', 'has_cleaned'])
        ->and($form->title)->toBe('Updated through the canonical service')
        ->and($removedIds)->toContain('old-email', 'removed-email')
        ->and($removedIds)->not->toContain('removed-copy');
});
