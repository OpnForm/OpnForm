<?php

use App\Service\Forms\FormLogicConditionChecker;

it('does not trigger either regex condition when the pattern is invalid', function (string $operator) {
    $condition = ['value' => ['property_meta' => ['id' => 'field', 'type' => 'rich_text'], 'operator' => $operator, 'value' => '[']];
    expect(FormLogicConditionChecker::conditionsMet($condition, ['field' => 'answer']))->toBeFalse();
})->with(['matches_regex', 'does_not_match_regex']);
