<?php

use App\Service\Forms\FormLogicConditionChecker;

dataset('PSEC scalar and matrix parity', function () {
    $cases = json_decode(file_get_contents(__DIR__ . '/../../../fixtures/form-logic-parity.json'), true, flags: JSON_THROW_ON_ERROR);
    foreach ($cases as $case) {
        yield $case['name'] => [$case];
    }
});

it('agrees with the browser on scalar and matrix conditions', function (array $case) {
    $condition = isset($case['group']) ? ['operatorIdentifier' => $case['group'], 'children' => $case['children']] : ['value' => [
        'property_meta' => ['id' => 'field', 'nf_id' => 'field', 'type' => $case['type']],
        'operator' => $case['operator'],
        'value' => $case['value'] ?? null,
    ]];

    expect(FormLogicConditionChecker::conditionsMet($condition, ['field' => $case['answer']]))
        ->toBe($case['expected']);
})->with('PSEC scalar and matrix parity');
