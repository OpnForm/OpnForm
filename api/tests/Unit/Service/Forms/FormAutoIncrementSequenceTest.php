<?php

use App\Service\Forms\FormAutoIncrementSequence;

uses(\Tests\TestCase::class);

it('allocates monotonically increasing ids atomically per form', function () {
    $user = $this->createProUser();
    $workspace = $this->createUserWorkspace($user);
    $form = $this->createForm($user, $workspace);

    expect(FormAutoIncrementSequence::allocateNext($form))->toBe('1');
    expect(FormAutoIncrementSequence::allocateNext($form->fresh()))->toBe('2');
    expect(FormAutoIncrementSequence::allocateNext($form->fresh()))->toBe('3');

    expect($form->fresh()->auto_increment_sequence)->toBe(3);
});

it('uses independent sequences per form', function () {
    $user = $this->createProUser();
    $workspace = $this->createUserWorkspace($user);
    $formA = $this->createForm($user, $workspace);
    $formB = $this->createForm($user, $workspace);

    expect(FormAutoIncrementSequence::allocateNext($formA))->toBe('1');
    expect(FormAutoIncrementSequence::allocateNext($formB))->toBe('1');
    expect(FormAutoIncrementSequence::allocateNext($formA->fresh()))->toBe('2');
});

it('assigns the same generated id to multiple auto-increment fields in one submission', function () {
    $user = $this->actingAsProUser();
    $workspace = $this->createUserWorkspace($user);
    $fieldA = 'ticket_a';
    $fieldB = 'ticket_b';
    $form = $this->createForm($user, $workspace, [
        'properties' => [
            [
                'id' => $fieldA,
                'type' => 'text',
                'name' => 'Ticket A',
                'hidden' => true,
                'required' => false,
                'generates_auto_increment_id' => true,
            ],
            [
                'id' => $fieldB,
                'type' => 'text',
                'name' => 'Ticket B',
                'hidden' => true,
                'required' => false,
                'generates_auto_increment_id' => true,
            ],
        ],
    ]);

    $this->postJson(route('forms.answer', $form->slug), [])
        ->assertSuccessful();

    $submission = $form->submissions()->first();
    expect($submission->data[$fieldA])->toBe('1');
    expect($submission->data[$fieldB])->toBe('1');

    $this->postJson(route('forms.answer', $form->slug), [])
        ->assertSuccessful();

    $secondSubmission = $form->submissions()->orderByDesc('id')->first();
    expect($secondSubmission->data[$fieldA])->toBe('2');
    expect($secondSubmission->data[$fieldB])->toBe('2');
});
