<?php

use App\Rules\IntegrationLogicRule;
use Tests\TestCase;

uses(TestCase::class);

function integrationMentionValue(string $fieldId, string $fieldName): string
{
    return '<span mention mention-field-id="' . $fieldId . '" mention-field-name="' . $fieldName . '" mention-fallback="">@' . $fieldName . '</span>';
}

describe('IntegrationLogicRule mention values', function () {
    it('accepts mention HTML as a numeric condition value', function () {
        $rule = new IntegrationLogicRule();

        $logic = [
            'operatorIdentifier' => 'and',
            'children' => [
                [
                    'identifier' => 'amount',
                    'value' => [
                        'operator' => 'greater_than',
                        'property_meta' => [
                            'id' => 'amount',
                            'type' => 'number',
                        ],
                        'value' => integrationMentionValue('threshold', 'Threshold'),
                    ],
                ],
            ],
        ];

        expect($rule->passes('logic', $logic))->toBeTrue();
    });

    it('still rejects plain non-numeric values for numeric conditions', function () {
        $rule = new IntegrationLogicRule();

        $logic = [
            'operatorIdentifier' => 'and',
            'children' => [
                [
                    'identifier' => 'amount',
                    'value' => [
                        'operator' => 'greater_than',
                        'property_meta' => [
                            'id' => 'amount',
                            'type' => 'number',
                        ],
                        'value' => 'not-a-number',
                    ],
                ],
            ],
        ];

        expect($rule->passes('logic', $logic))->toBeFalse();
        expect($rule->message())->toBe('The logic conditions are not complete. Error detail(s): wrong type of condition value');
    });
});
