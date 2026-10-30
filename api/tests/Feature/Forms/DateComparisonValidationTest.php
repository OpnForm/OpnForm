<?php

it('enforces date references on submission even without browser validation', function ($start, $end, bool $valid) {
    $user = $this->actingAsProUser();
    $workspace = $this->createUserWorkspace($user);
    $form = $this->createForm($user, $workspace, ['properties' => [
        ['id' => 'start', 'name' => 'Start', 'type' => 'date', 'required' => true],
        ['id' => 'end', 'name' => 'End', 'type' => 'date', 'required' => false, 'validation' => [
            'error_message' => 'End must be on or after start.',
            'error_conditions' => ['conditions' => ['operatorIdentifier' => 'and', 'children' => [
                ['identifier' => 'end', 'value' => ['property_meta' => ['id' => 'end', 'type' => 'date'],
                    'operator' => 'on_or_after', 'value' => '<span mention-field-id="start">Start</span>']],
            ]]],
        ]],
    ]]);
    $response = $this->postJson(route('forms.answer', $form->slug), ['start' => $start, 'end' => $end]);
    if ($valid) {
        $response->assertSuccessful();
    } else {
        $response->assertUnprocessable()->assertJsonValidationErrors('end');
    }
})->with([
    ['2026-10-30', '2026-10-31', true],
    ['2026-10-30', '2026-10-29', false],
    ['2026-10-30', null, false],
    [null, '2026-10-30', false],
    ['2026-10-30', [], false],
    ['2026-10-30', '2026-02-30', false],
    ['2026-10-30T12:00:00+02:00', '2026-10-30T10:00:00Z', true],
]);

it('rejects invalid date references when saving through the API', function () {
    $user = $this->actingAsProUser();
    $workspace = $this->createUserWorkspace($user);
    $form = $this->createForm($user, $workspace, ['properties' => [
        ['id' => 'start', 'name' => 'Start', 'type' => 'date'],
        ['id' => 'end', 'name' => 'End', 'type' => 'date'],
    ]]);
    $payload = $form->toArray();
    $payload['properties'][1]['validation'] = [
        'error_message' => 'Invalid end date.',
        'error_conditions' => ['conditions' => ['operatorIdentifier' => 'and', 'children' => [
            ['identifier' => 'end', 'value' => ['property_meta' => ['id' => 'end', 'type' => 'date'],
                'operator' => 'on_or_after', 'value' => '<span mention-field-id="deleted">Deleted</span>']],
        ]]],
    ];
    $this->putJson(route('open.forms.update', $form->id), $payload)
        ->assertUnprocessable()->assertJsonValidationErrors('properties.1.validation.error_conditions.conditions.children.0.value.value');
    $payload['properties'][1]['validation']['error_conditions']['conditions']['children'][0]['value']['value'] = '<span mention-field-id="start">Start</span>';
    $this->putJson(route('open.forms.update', $form->id), $payload)->assertSuccessful();
});

it('enforces a single leaf date validation condition', function () {
    $condition = ['value' => ['property_meta' => ['id' => 'end', 'type' => 'date'], 'operator' => 'on_or_after',
        'value' => '<span mention-field-id="start">Start</span>']];
    $rule = new \App\Rules\CustomFieldValidationRule(['error_conditions' => ['conditions' => $condition]], ['start' => '2026-10-30', 'end' => '2026-10-29']);
    expect($rule->passes('end', '2026-10-29'))->toBeFalse();
});

it('does not trust submitted values for computed date references', function ($start, $end, $spoof, bool $valid) {
    $user = $this->actingAsProUser();
    $workspace = $this->createUserWorkspace($user);
    $form = $this->createForm($user, $workspace, [
        'computed_variables' => [['id' => 'cv_start', 'name' => 'Computed start', 'formula' => '{start}', 'result_type' => 'auto']],
        'properties' => [
            ['id' => 'start', 'name' => 'Start', 'type' => 'date', 'required' => false],
            ['id' => 'end', 'name' => 'End', 'type' => 'date', 'validation' => [
                'error_conditions' => ['conditions' => ['operatorIdentifier' => 'and', 'children' => [
                    ['identifier' => 'end', 'value' => ['property_meta' => ['id' => 'end', 'type' => 'date'], 'operator' => 'on_or_after',
                        'value' => '<span mention-field-id="cv_start">Computed start</span>']],
                ]]],
            ]],
        ],
    ]);
    $response = $this->postJson(route('forms.answer', $form->slug), ['start' => $start, 'end' => $end, 'cv_start' => $spoof]);
    if ($valid) {
        $response->assertSuccessful();
    } else {
        $response->assertUnprocessable()->assertJsonValidationErrors('end');
    }
})->with([
    ['2026-10-30', '2026-10-29', '2026-10-28', false],
    [null, '2026-10-29', '2026-10-28', false],
    ['2026-10-30', '2026-10-31', null, true],
]);
