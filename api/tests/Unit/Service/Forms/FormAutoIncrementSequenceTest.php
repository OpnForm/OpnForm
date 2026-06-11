<?php

use App\Events\Forms\FormSaved;
use App\Models\Forms\FormSubmission;
use App\Service\Forms\FormAutoIncrementSequence;
use Illuminate\Support\Facades\Event;

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

it('does not dispatch form saved events when allocating an id', function () {
    $user = $this->createProUser();
    $workspace = $this->createUserWorkspace($user);
    $form = $this->createForm($user, $workspace);

    Event::fake([FormSaved::class]);

    expect(FormAutoIncrementSequence::allocateNext($form))->toBe('1');

    Event::assertNotDispatched(FormSaved::class);
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

it('does not allocate auto-increment ids for partial submissions until completion', function () {
    $user = $this->actingAsBusinessUser();
    $workspace = $this->createUserWorkspace($user);
    $nameField = '384172a5-7815-43f1-b865-bb6300a77c48';
    $autoIncrementField = 'f68c5823-588f-43a4-93dd-66553e4b6feb';
    $form = $this->createForm($user, $workspace, [
        'enable_partial_submissions' => true,
        'properties' => [
            [
                'id' => $nameField,
                'type' => 'text',
                'name' => 'Name',
                'required' => false,
            ],
            [
                'id' => $autoIncrementField,
                'type' => 'text',
                'name' => 'Ticket ID',
                'hidden' => true,
                'required' => false,
                'generates_auto_increment_id' => true,
            ],
        ],
    ]);

    $partialResponse = $this->postJson(route('forms.answer', $form->slug), [
        $nameField => 'Draft',
        'is_partial' => true,
    ])->assertSuccessful();

    $submission = $form->submissions()->first();
    expect($submission->status)->toBe(FormSubmission::STATUS_PARTIAL);
    expect($submission->data)->not->toHaveKey($autoIncrementField);
    expect($form->fresh()->auto_increment_sequence)->toBe(0);

    $this->postJson(route('forms.answer', $form->slug), [
        $nameField => 'Completed',
        'submission_hash' => $partialResponse->json('submission_hash'),
    ])->assertSuccessful();

    $submission->refresh();
    expect($submission->status)->toBe(FormSubmission::STATUS_COMPLETED);
    expect($submission->data[$autoIncrementField])->toBe('1');
    expect($form->fresh()->auto_increment_sequence)->toBe(1);
});

it('preserves existing auto-increment ids when editing completed submissions', function () {
    $user = $this->actingAsBusinessUser();
    $workspace = $this->createUserWorkspace($user);
    $nameField = 'dc4b72dc-3812-4ed8-aaba-9ef95b6b6cde';
    $autoIncrementField = 'c30a87b8-bc32-4215-9ec0-5d8f5f823e04';
    $form = $this->createForm($user, $workspace, [
        'editable_submissions' => true,
        'properties' => [
            [
                'id' => $nameField,
                'type' => 'text',
                'name' => 'Name',
                'required' => false,
            ],
            [
                'id' => $autoIncrementField,
                'type' => 'text',
                'name' => 'Ticket ID',
                'hidden' => true,
                'required' => false,
                'generates_auto_increment_id' => true,
            ],
        ],
    ]);

    $response = $this->postJson(route('forms.answer', $form->slug), [
        $nameField => 'Original',
    ])->assertSuccessful();

    $submission = $form->submissions()->first();
    expect($submission->data[$autoIncrementField])->toBe('1');

    $this->postJson(route('forms.answer', $form->slug), [
        $nameField => 'Edited',
        'submission_id' => $response->json('submission_id'),
    ])->assertSuccessful();

    $submission->refresh();
    expect($submission->data[$nameField])->toBe('Edited');
    expect($submission->data[$autoIncrementField])->toBe('1');
    expect($form->fresh()->auto_increment_sequence)->toBe(1);
});
