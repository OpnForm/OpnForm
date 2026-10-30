<?php

use App\Service\Forms\FormLogicConditionChecker;

it('compares strict ISO dates consistently with the frontend', function (array $case) {
    $condition = ['value' => [
        'property_meta' => ['id' => 'end', 'type' => 'date', 'with_time' => $case['with_time'] ?? true],
        'operator' => $case['operator'],
        'value' => '<span mention="true" mention-field-id="start">Start</span>',
    ]];
    expect(FormLogicConditionChecker::conditionsMet($condition, ['end' => $case['left'], 'start' => $case['right']]))->toBe($case['expected']);
})->with(array_map(fn ($case) => [$case], json_decode(file_get_contents(dirname(__DIR__, 5).'/client/test/fixtures/date-comparisons.json'), true)));

it('resolves and validates computed date references and missing variables', function () {
    $form = new \App\Models\Forms\Form();
    $form->computed_variables = [['id' => 'cv_start', 'formula' => '{start}', 'result_type' => 'text']];
    $condition = ['value' => ['property_meta' => ['id' => 'end', 'type' => 'date'], 'operator' => 'on_or_after',
        'value' => '<span mention-field-id="cv_start">Computed start</span>']];
    expect(FormLogicConditionChecker::conditionsMetWithForm($condition, ['start' => '2026-10-30', 'end' => '2026-10-31'], $form))->toBeTrue();
    expect(FormLogicConditionChecker::conditionsMetWithForm($condition, ['start' => 'invalid', 'end' => '2026-10-31'], $form))->toBeFalse();
    expect(FormLogicConditionChecker::conditionsMetWithForm($condition, ['end' => '2026-10-31'], $form))->toBeFalse();
});


it('uses owning form date metadata instead of stale condition metadata', function () {
    $form = new \App\Models\Forms\Form();
    $form->properties = [['id' => 'end', 'type' => 'date', 'with_time' => false]];
    $condition = ['value' => ['property_meta' => ['id' => 'end', 'type' => 'date', 'with_time' => true], 'operator' => 'equals', 'value' => '2026-10-30T00:00:00-04:00']];
    expect(FormLogicConditionChecker::conditionsMetWithForm($condition, ['end' => '2026-10-30T00:00:00+01:00'], $form))->toBeTrue();
});
