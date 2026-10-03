<?php

use App\Rules\ComputedVariablesRule;
use Tests\TestCase;

uses(TestCase::class);

it('returns every actionable formula error on its variable path', function () {
    $validator = validator([
        'properties' => [
            ['id' => 'amount', 'name' => 'Amount', 'type' => 'number'],
        ],
        'computed_variables' => [
            [
                'id' => 'cv_total',
                'name' => 'Total',
                'formula' => '{missing_one} + {missing_two}',
                'result_type' => 'number',
            ],
        ],
    ], [
        'computed_variables' => ['array', new ComputedVariablesRule()],
    ]);

    expect($validator->passes())->toBeFalse()
        ->and($validator->errors()->get('computed_variables.0.formula'))->toHaveCount(2)
        ->and($validator->errors()->first('computed_variables.0.formula'))->toContain('missing_one');
});

it('rejects an ID shared by a field and computed variable', function () {
    $validator = validator([
        'properties' => [
            ['id' => 'cv_total', 'name' => 'Amount', 'type' => 'number'],
        ],
        'computed_variables' => [
            [
                'id' => 'cv_total',
                'name' => 'Total',
                'formula' => '10',
                'result_type' => 'number',
            ],
        ],
    ], [
        'computed_variables' => ['array', new ComputedVariablesRule()],
    ]);

    expect($validator->passes())->toBeFalse()
        ->and($validator->errors()->first('computed_variables.0.id'))->toContain('already used by a form field');
});

it('returns circular dependency and unknown reference errors together', function () {
    $validator = validator([
        'properties' => [],
        'computed_variables' => [
            ['id' => 'cv_a', 'name' => 'A', 'formula' => '{cv_b}', 'result_type' => 'number'],
            ['id' => 'cv_b', 'name' => 'B', 'formula' => '{cv_a}', 'result_type' => 'number'],
            ['id' => 'cv_c', 'name' => 'C', 'formula' => '{missing}', 'result_type' => 'number'],
        ],
    ], [
        'computed_variables' => ['array', new ComputedVariablesRule()],
    ]);

    expect($validator->passes())->toBeFalse()
        ->and($validator->errors()->first('computed_variables.0.formula'))->toContain('Circular dependency')
        ->and($validator->errors()->first('computed_variables.1.formula'))->toContain('Circular dependency')
        ->and($validator->errors()->first('computed_variables.2.formula'))->toContain('missing');
});

it('reports malformed variables without throwing while calculating dependency depth', function () {
    $validator = validator([
        'properties' => [],
        'computed_variables' => ['not-an-object'],
    ], [
        'computed_variables' => ['array', new ComputedVariablesRule()],
    ]);

    expect($validator->passes())->toBeFalse()
        ->and($validator->errors()->first('computed_variables.0._'))->toContain('must be an array');
});

it('localizes a malformed variable id even when its formula must also be checked', function () {
    $validator = validator([
        'properties' => [],
        'computed_variables' => [[
            'id' => ['cv_invalid'],
            'name' => 'Invalid',
            'formula' => '{missing}',
        ]],
    ], [
        'computed_variables' => ['array', new ComputedVariablesRule()],
    ]);

    expect($validator->passes())->toBeFalse()
        ->and($validator->errors()->first('computed_variables.0.id'))->toContain('required')
        ->and($validator->errors()->first('computed_variables.0.formula'))->toContain('missing');
});

it('does not throw when the sibling properties collection is malformed', function () {
    $validator = validator([
        'properties' => 'not-an-array',
        'computed_variables' => [[
            'id' => 'cv_total',
            'name' => 'Total',
            'formula' => '{missing}',
        ]],
    ], [
        'properties' => ['array'],
        'computed_variables' => ['array', new ComputedVariablesRule()],
    ]);

    expect($validator->passes())->toBeFalse()
        ->and($validator->errors()->first('properties'))->not->toBeEmpty()
        ->and($validator->errors()->first('computed_variables.0.formula'))->toContain('missing');
});

it('attaches excessive dependency depth to each affected variable formula', function () {
    $variables = [];
    foreach (range(1, 21) as $index) {
        $variables[] = [
            'id' => "cv_{$index}",
            'name' => "Variable {$index}",
            'formula' => $index === 21 ? '1' : '{cv_'.($index + 1).'}',
            'result_type' => 'number',
        ];
    }

    $validator = validator([
        'properties' => [],
        'computed_variables' => $variables,
    ], [
        'computed_variables' => ['array', new ComputedVariablesRule()],
    ]);

    expect($validator->passes())->toBeFalse()
        ->and($validator->errors()->first('computed_variables.0.formula'))->toContain('21 levels')
        ->and($validator->errors()->has('computed_variables.1.formula'))->toBeFalse();
});

it('counts multibyte variable names as characters and rejects whitespace-only names', function () {
    $valid = validator([
        'properties' => [],
        'computed_variables' => [[
            'id' => 'cv_multibyte',
            'name' => str_repeat('é', 100),
            'formula' => '1',
        ]],
    ], [
        'computed_variables' => ['array', new ComputedVariablesRule()],
    ]);
    $invalid = validator([
        'properties' => [],
        'computed_variables' => [[
            'id' => 'cv_blank',
            'name' => '   ',
            'formula' => '1',
        ]],
    ], [
        'computed_variables' => ['array', new ComputedVariablesRule()],
    ]);

    expect($valid->passes())->toBeTrue()
        ->and($invalid->passes())->toBeFalse()
        ->and($invalid->errors()->first('computed_variables.0.name'))->toContain('cannot be empty');
});

it('rejects an unbounded computed variable collection before graph traversal', function () {
    $variables = array_fill(0, ComputedVariablesRule::MAX_VARIABLE_COUNT + 1, [
        'id' => 'cv_duplicate',
        'name' => 'Duplicate',
        'formula' => '1',
    ]);
    $validator = validator([
        'properties' => [],
        'computed_variables' => $variables,
    ], [
        'computed_variables' => ['array', new ComputedVariablesRule()],
    ]);

    expect($validator->passes())->toBeFalse()
        ->and($validator->errors()->first('computed_variables'))->toContain('cannot contain more than 500');
});
