<?php

use App\Service\Formulas\Evaluator;
use App\Service\Formulas\Validator;

it('evaluates only the selected IF branch', function () {
    expect(Evaluator::evaluate('IF(TRUE, 7, UNKNOWN())'))->toBe(7.0);
    expect(Evaluator::evaluate('IF(FALSE, UNKNOWN(), 9)'))->toBe(9.0);
});

it('ignores braces inside string literals and handles empty lists consistently', function () {
    expect(Validator::extractFieldReferences('CONCAT("{literal}", {actual})'))->toBe(['actual']);
    expect(Evaluator::evaluate('ISBLANK({value})', ['value' => []]))->toBeTrue();
    expect(Evaluator::evaluate('IF({value}, 1, 0)', ['value' => []]))->toBe(0.0);
});
