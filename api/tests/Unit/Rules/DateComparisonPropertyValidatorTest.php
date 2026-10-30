<?php

use App\Rules\PropertyValidators\DateComparisonPropertyValidator;

function dateComparisonProperty($value, string $location): array
{
    $conditions = ['operatorIdentifier' => 'and', 'children' => [['identifier' => 'end', 'value' => [
        'property_meta' => ['id' => 'end', 'type' => 'date'], 'operator' => 'on_or_after', 'value' => $value,
    ]]]];
    return $location === 'logic' ? ['logic' => ['conditions' => $conditions]]
        : ['validation' => ['error_conditions' => ['conditions' => $conditions]]];
}

it('validates date operands in display and validation rules', function ($value, bool $valid, string $location) {
    $context = ['properties' => [
        ['id' => 'start', 'type' => 'date'], ['id' => 'end', 'type' => 'date'],
        ['id' => 'range', 'type' => 'date', 'date_range' => true], ['id' => 'text', 'type' => 'text'],
    ], 'computed_variables' => [['id' => 'cv_date', 'result_type' => 'text'], ['id' => 'cv_num', 'result_type' => 'number']]];
    $errors = (new DateComparisonPropertyValidator())->validate(dateComparisonProperty($value, $location), 0, $context);
    expect($errors === [])->toBe($valid);
})->with([
    ['2026-10-30', true], ['2026-02-30', false], [null, false], [[], false],
    ['<span mention-field-id="start">Start</span>', true],
    ['<span mention-field-id="cv_date">Variable</span>', true],
    ['<span mention-field-id="cv_num">Variable</span>', false],
    ['<span mention-field-id="missing">Missing</span>', false],
    ['<span mention-field-id="end">Self</span>', false],
    ['<span mention-field-id="range">Range</span>', false],
    ['<span mention-field-id="text">Text</span>', false],
    ['prefix <span mention-field-id="start">Start</span>', false],
    ['<span mention-field-id="start">Start</span><span mention-field-id="end">End</span>', false],
])->with(['logic', 'validation']);

it('rejects mixed date-only and date-time field references', function () {
    $context = ['properties' => [['id' => 'start', 'type' => 'date', 'with_time' => true], ['id' => 'end', 'type' => 'date']]];
    $errors = (new DateComparisonPropertyValidator())->validate(dateComparisonProperty('<span mention-field-id="start">Start</span>', 'validation'), 0, $context);
    expect($errors)->not->toBeEmpty();
});
