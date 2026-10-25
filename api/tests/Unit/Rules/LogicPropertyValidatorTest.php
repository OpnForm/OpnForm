<?php

use App\Rules\PropertyValidators\LogicPropertyValidator;
use App\Service\Forms\FormRegex;
use Tests\TestCase;

uses(TestCase::class);

describe('LogicPropertyValidator action validation', function () {
    it('passes with empty logic', function () {
        $validator = new LogicPropertyValidator();
        $context = ['properties' => []];
        $property = [
            'id' => 'title',
            'name' => 'Name',
            'type' => 'title',
            'hidden' => false,
            'required' => false,
            'logic' => [
                'conditions' => null,
                'actions' => [],
            ],
        ];
        $errors = $validator->validate($property, 0, $context);
        expect($errors)->toBeEmpty();
    });

    it('fails when hidden block has hide-block action', function () {
        $validator = new LogicPropertyValidator();
        $context = ['properties' => []];
        $property = [
            'id' => 'title',
            'name' => 'Name',
            'type' => 'title',
            'hidden' => true,
            'required' => false,
            'logic' => [
                'conditions' => [
                    'operatorIdentifier' => 'and',
                    'children' => [
                        [
                            'identifier' => 'title',
                            'value' => [
                                'operator' => 'equals',
                                'property_meta' => [
                                    'id' => 'title',
                                    'type' => 'text',
                                ],
                                'value' => 'TEST',
                            ],
                        ],
                    ],
                ],
                'actions' => ['hide-block'],
            ],
        ];
        $errors = $validator->validate($property, 0, $context);
        expect($errors)->toHaveKey('logic.actions.0');
        expect($errors['logic.actions.0'])->toContain('not valid for this field');
    });

    it('rejects no-op actions that the editor does not offer for the current field state', function () {
        $validator = new LogicPropertyValidator();
        $source = ['id' => 'source', 'name' => 'Source', 'type' => 'text'];
        $property = [
            'id' => 'target',
            'name' => 'Target',
            'type' => 'text',
            'hidden' => false,
            'required' => false,
            'disabled' => false,
            'logic' => [
                'conditions' => [
                    'identifier' => 'source',
                    'value' => [
                        'operator' => 'equals',
                        'property_meta' => ['id' => 'source', 'type' => 'text'],
                        'value' => 'yes',
                    ],
                ],
                'actions' => ['show-block'],
            ],
        ];

        $errors = $validator->validate($property, 0, ['properties' => [$source, $property]]);

        expect($errors['logic.actions.0'])->toContain('not valid for this field');
    });

    it('fails when nf-text block has require-answer action', function () {
        $validator = new LogicPropertyValidator();
        $context = ['properties' => []];
        $property = [
            'id' => 'text',
            'name' => 'Custom Test',
            'type' => 'nf-text',
            'logic' => [
                'conditions' => [
                    'operatorIdentifier' => 'and',
                    'children' => [
                        [
                            'identifier' => 'title',
                            'value' => [
                                'operator' => 'equals',
                                'property_meta' => [
                                    'id' => 'title',
                                    'type' => 'text',
                                ],
                                'value' => 'TEST',
                            ],
                        ],
                    ],
                ],
                'actions' => ['require-answer'],
            ],
        ];
        $errors = $validator->validate($property, 0, $context);
        expect($errors)->toHaveKey('logic.actions.0');
        expect($errors['logic.actions.0'])->toContain('not valid for this field');
    });
});

describe('LogicPropertyValidator condition validation', function () {
    it('passes with valid conditions', function () {
        $validator = new LogicPropertyValidator();
        $context = ['properties' => []];
        $property = [
            'id' => 'title',
            'name' => 'Name',
            'type' => 'text',
            'hidden' => false,
            'required' => false,
            'logic' => [
                'conditions' => [
                    'operatorIdentifier' => 'and',
                    'children' => [
                        [
                            'identifier' => 'title',
                            'value' => [
                                'operator' => 'equals',
                                'property_meta' => [
                                    'id' => 'title',
                                    'type' => 'text',
                                ],
                                'value' => 'TEST',
                            ],
                        ],
                    ],
                ],
                'actions' => ['hide-block'],
            ],
        ];
        $errors = $validator->validate($property, 0, $context);
        expect($errors)->toBeEmpty();
    });

    it('accepts browser-compatible regex patterns containing slashes and rejects oversized patterns', function () {
        $property = [
            'id' => 'target',
            'name' => 'Target',
            'type' => 'text',
            'logic' => [
                'conditions' => [
                    'identifier' => 'source',
                    'value' => [
                        'operator' => 'matches_regex',
                        'property_meta' => ['id' => 'source', 'type' => 'text'],
                        'value' => '^docs/example$',
                    ],
                ],
                'actions' => ['hide-block'],
            ],
        ];
        $validator = new LogicPropertyValidator();

        expect($validator->validate($property, 0, ['properties' => []]))->toBeEmpty();

        $property['logic']['conditions']['value']['value'] = str_repeat('a', FormRegex::MAX_PATTERN_LENGTH + 1);
        expect($validator->validate($property, 0, ['properties' => []]))
            ->toHaveKey('logic.conditions.value.value');
    });

    it('passes with computed variable conditions', function () {
        $validator = new LogicPropertyValidator();
        $context = [
            'properties' => [],
            'computed_variables' => [
                ['id' => 'cv_total', 'name' => 'Total', 'formula' => '100', 'result_type' => 'number'],
            ],
        ];
        $property = [
            'id' => 'target',
            'name' => 'Target',
            'type' => 'text',
            'hidden' => false,
            'required' => false,
            'logic' => [
                'conditions' => [
                    'operatorIdentifier' => 'and',
                    'children' => [
                        [
                            'identifier' => 'cv_total',
                            'value' => [
                                'operator' => 'greater_than',
                                'property_meta' => [
                                    'id' => 'cv_total',
                                    'type' => 'computed',
                                ],
                                'value' => 100,
                            ],
                        ],
                    ],
                ],
                'actions' => ['hide-block'],
            ],
        ];

        $errors = $validator->validate($property, 0, $context);
        expect($errors)->toBeEmpty();
    });

    it('accepts numeric strings that the runtime evaluates as numbers', function () {
        $property = [
            'id' => 'target',
            'name' => 'Target',
            'type' => 'text',
            'logic' => [
                'conditions' => [
                    'identifier' => 'amount',
                    'value' => [
                        'operator' => 'greater_than',
                        'property_meta' => ['id' => 'amount', 'type' => 'number'],
                        'value' => '10',
                    ],
                ],
                'actions' => ['hide-block'],
            ],
        ];

        $errors = (new LogicPropertyValidator())->validate($property, 0, [
            'properties' => [
                ['id' => 'amount', 'name' => 'Amount', 'type' => 'number'],
                $property,
            ],
        ]);

        expect($errors)->toBeEmpty();
    });

    it('fails when condition value is missing', function () {
        $validator = new LogicPropertyValidator();
        $context = ['properties' => []];
        $property = [
            'id' => 'title',
            'name' => 'Name',
            'type' => 'text',
            'hidden' => false,
            'required' => false,
            'logic' => [
                'conditions' => [
                    'operatorIdentifier' => 'and',
                    'children' => [
                        [
                            'identifier' => 'title',
                            'value' => [
                                'operator' => 'starts_with',
                                'property_meta' => [
                                    'id' => 'title',
                                    'type' => 'text',
                                ],
                            ],
                        ],
                    ],
                ],
                'actions' => ['hide-block'],
            ],
        ];
        $errors = $validator->validate($property, 0, $context);
        expect($errors)->toHaveKey('logic.conditions.children.0.value.value');
        expect($errors['logic.conditions.children.0.value.value'])->toContain('requires a comparison value');
    });

    it('fails when operator is missing', function () {
        $validator = new LogicPropertyValidator();
        $context = ['properties' => []];
        $property = [
            'id' => 'title',
            'name' => 'Name',
            'type' => 'text',
            'hidden' => false,
            'required' => false,
            'logic' => [
                'conditions' => [
                    'operatorIdentifier' => null,
                    'children' => [
                        [
                            'identifier' => 'title',
                            'value' => [
                                'operator' => 'starts_with',
                                'property_meta' => [
                                    'id' => 'title',
                                    'type' => 'text',
                                ],
                            ],
                        ],
                    ],
                ],
                'actions' => ['hide-block'],
            ],
        ];
        $errors = $validator->validate($property, 0, $context);
        expect($errors)->toHaveKey('logic.conditions.operatorIdentifier');
        expect($errors['logic.conditions.operatorIdentifier'])->toContain('must be "and" or "or"');
    });
});

describe('LogicPropertyValidator reference integrity', function () {
    function propertyWithReference(string $referenceId, string $referenceType = 'text'): array
    {
        return [
            'id' => 'target',
            'name' => 'Target',
            'type' => 'text',
            'logic' => [
                'conditions' => [
                    'operatorIdentifier' => 'and',
                    'children' => [[
                        'identifier' => $referenceId,
                        'value' => [
                            'operator' => 'equals',
                            'property_meta' => [
                                'id' => $referenceId,
                                'type' => $referenceType,
                            ],
                            'value' => 'yes',
                        ],
                    ]],
                ],
                'actions' => ['hide-block'],
            ],
        ];
    }

    it('reports an unknown field reference at its exact path', function () {
        $property = propertyWithReference('deleted_field');
        $errors = (new LogicPropertyValidator())->validate($property, 0, [
            'properties' => [$property],
            'computed_variables' => [],
        ]);

        expect($errors)
            ->toHaveKey('logic.conditions.children.0.value.property_meta.id')
            ->and($errors['logic.conditions.children.0.value.property_meta.id'])
            ->toContain('no longer exists');
    });

    it('reports a stale reference type', function () {
        $property = propertyWithReference('source', 'number');
        $errors = (new LogicPropertyValidator())->validate($property, 0, [
            'properties' => [
                $property,
                ['id' => 'source', 'name' => 'Source', 'type' => 'text'],
            ],
            'computed_variables' => [],
        ]);

        expect($errors['logic.conditions.children.0.value.property_meta.type'])
            ->toContain('must be [text]');
    });

    it('rejects a condition identifier that does not match its reference', function () {
        $property = propertyWithReference('source');
        $property['logic']['conditions']['children'][0]['identifier'] = 'stale_source';
        $errors = (new LogicPropertyValidator())->validate($property, 0, [
            'properties' => [
                $property,
                ['id' => 'source', 'name' => 'Source', 'type' => 'text'],
            ],
            'computed_variables' => [],
        ]);

        expect($errors['logic.conditions.children.0.identifier'])
            ->toContain('must match the referenced item [source]');
    });

    it('rejects a field referencing itself', function () {
        $property = propertyWithReference('target');
        $errors = (new LogicPropertyValidator())->validate($property, 0, [
            'properties' => [$property],
            'computed_variables' => [],
        ]);

        expect($errors['logic.conditions.children.0.value.property_meta.id'])
            ->toContain('cannot reference the same field');
    });

    it('rejects custom-validation-only operators in display logic', function () {
        $property = propertyWithReference('source', 'email');
        $property['logic']['conditions']['children'][0]['value']['operator'] = 'exists_in_submissions';
        $property['logic']['conditions']['children'][0]['value']['value'] = true;
        $errors = (new LogicPropertyValidator())->validate($property, 0, [
            'properties' => [
                $property,
                ['id' => 'source', 'name' => 'Source', 'type' => 'email'],
            ],
            'computed_variables' => [],
        ]);

        expect($errors['logic.conditions.children.0.value.operator'])
            ->toContain('only available for custom validation');
    });

    it('limits nested condition groups', function () {
        $condition = [
            'identifier' => 'source',
            'value' => [
                'operator' => 'equals',
                'property_meta' => ['id' => 'source', 'type' => 'text'],
                'value' => 'yes',
            ],
        ];

        foreach (range(1, LogicPropertyValidator::MAX_CONDITION_DEPTH) as $unused) {
            $condition = ['operatorIdentifier' => 'and', 'children' => [$condition]];
        }

        $property = [
            'id' => 'target',
            'name' => 'Target',
            'type' => 'text',
            'logic' => ['conditions' => $condition, 'actions' => ['hide-block']],
        ];
        $errors = (new LogicPropertyValidator())->validate($property, 0, [
            'properties' => [
                $property,
                ['id' => 'source', 'name' => 'Source', 'type' => 'text'],
            ],
            'computed_variables' => [],
        ]);

        expect(collect($errors)->contains(fn (string $message) => str_contains($message, 'cannot be nested')))->toBeTrue();
    });

    it('limits the total number of conditions', function () {
        $leaf = [
            'identifier' => 'source',
            'value' => [
                'operator' => 'equals',
                'property_meta' => ['id' => 'source', 'type' => 'text'],
                'value' => 'yes',
            ],
        ];
        $property = [
            'id' => 'target',
            'name' => 'Target',
            'type' => 'text',
            'logic' => [
                'conditions' => [
                    'operatorIdentifier' => 'and',
                    'children' => array_fill(0, LogicPropertyValidator::MAX_CONDITION_COUNT, $leaf),
                ],
                'actions' => ['hide-block'],
            ],
        ];
        $errors = (new LogicPropertyValidator())->validate($property, 0, [
            'properties' => [
                $property,
                ['id' => 'source', 'name' => 'Source', 'type' => 'text'],
            ],
            'computed_variables' => [],
        ]);

        expect(collect($errors)->contains(fn (string $message) => str_contains($message, 'cannot contain more than')))->toBeTrue();
    });
});

describe('LogicPropertyValidator mention values', function () {
    function mentionValue(string $fieldId, string $fieldName): string
    {
        return '<span mention mention-field-id="' . $fieldId . '" mention-field-name="' . $fieldName . '" mention-fallback="">@' . $fieldName . '</span>';
    }

    it('accepts mention HTML as valid string condition value', function () {
        $validator = new LogicPropertyValidator();
        $context = ['properties' => []];
        $property = [
            'id' => 'title',
            'name' => 'Name',
            'type' => 'text',
            'hidden' => false,
            'required' => false,
            'logic' => [
                'conditions' => [
                    'operatorIdentifier' => 'and',
                    'children' => [
                        [
                            'identifier' => 'title',
                            'value' => [
                                'operator' => 'equals',
                                'property_meta' => [
                                    'id' => 'title',
                                    'type' => 'text',
                                ],
                                'value' => mentionValue('other_field', 'Other Field'),
                            ],
                        ],
                    ],
                ],
                'actions' => ['hide-block'],
            ],
        ];
        $errors = $validator->validate($property, 0, $context);
        expect($errors)->toBeEmpty();
    });

    it('accepts mention HTML as valid number condition value', function () {
        $validator = new LogicPropertyValidator();
        $context = ['properties' => []];
        $property = [
            'id' => 'num',
            'name' => 'Number',
            'type' => 'number',
            'hidden' => false,
            'required' => false,
            'logic' => [
                'conditions' => [
                    'operatorIdentifier' => 'and',
                    'children' => [
                        [
                            'identifier' => 'num',
                            'value' => [
                                'operator' => 'greater_than',
                                'property_meta' => [
                                    'id' => 'num',
                                    'type' => 'number',
                                ],
                                'value' => mentionValue('threshold', 'Threshold'),
                            ],
                        ],
                    ],
                ],
                'actions' => ['hide-block'],
            ],
        ];
        $errors = $validator->validate($property, 0, $context);
        expect($errors)->toBeEmpty();
    });

    it('accepts mention HTML for starts_with operator', function () {
        $validator = new LogicPropertyValidator();
        $context = ['properties' => []];
        $property = [
            'id' => 'title',
            'name' => 'Name',
            'type' => 'text',
            'hidden' => false,
            'required' => false,
            'logic' => [
                'conditions' => [
                    'operatorIdentifier' => 'and',
                    'children' => [
                        [
                            'identifier' => 'title',
                            'value' => [
                                'operator' => 'starts_with',
                                'property_meta' => [
                                    'id' => 'title',
                                    'type' => 'text',
                                ],
                                'value' => mentionValue('prefix_field', 'Prefix'),
                            ],
                        ],
                    ],
                ],
                'actions' => ['hide-block'],
            ],
        ];
        $errors = $validator->validate($property, 0, $context);
        expect($errors)->toBeEmpty();
    });
});

describe('LogicPropertyValidator operators without values', function () {
    it('passes for checkbox is_checked without value', function () {
        $validator = new LogicPropertyValidator();
        $context = ['properties' => []];
        $property = [
            'id' => 'checkbox1',
            'name' => 'Checkbox Field',
            'type' => 'checkbox',
            'hidden' => true,
            'logic' => [
                'conditions' => [
                    'operatorIdentifier' => 'and',
                    'children' => [
                        [
                            'identifier' => 'test-id',
                            'value' => [
                                'operator' => 'is_checked',
                                'property_meta' => [
                                    'id' => 'test-id',
                                    'type' => 'checkbox'
                                ]
                            ]
                        ]
                    ]
                ],
                'actions' => ['show-block']
            ]
        ];
        $errors = $validator->validate($property, 0, $context);
        expect($errors)->toBeEmpty();
    });

    it('passes for checkbox is_checked with value for backward compatibility', function () {
        $validator = new LogicPropertyValidator();
        $context = ['properties' => []];
        $property = [
            'id' => 'checkbox1',
            'name' => 'Checkbox Field',
            'type' => 'checkbox',
            'hidden' => true,
            'logic' => [
                'conditions' => [
                    'operatorIdentifier' => 'and',
                    'children' => [
                        [
                            'identifier' => 'test-id',
                            'value' => [
                                'operator' => 'is_checked',
                                'property_meta' => [
                                    'id' => 'test-id',
                                    'type' => 'checkbox'
                                ],
                                'value' => true
                            ]
                        ]
                    ]
                ],
                'actions' => ['show-block']
            ]
        ];
        $errors = $validator->validate($property, 0, $context);
        expect($errors)->toBeEmpty();
    });

    it('passes for checkbox is_not_checked without value', function () {
        $validator = new LogicPropertyValidator();
        $context = ['properties' => []];
        $property = [
            'id' => 'checkbox1',
            'name' => 'Checkbox Field',
            'type' => 'checkbox',
            'hidden' => true,
            'logic' => [
                'conditions' => [
                    'operatorIdentifier' => 'and',
                    'children' => [
                        [
                            'identifier' => 'test-id',
                            'value' => [
                                'operator' => 'is_not_checked',
                                'property_meta' => [
                                    'id' => 'test-id',
                                    'type' => 'checkbox'
                                ]
                            ]
                        ]
                    ]
                ],
                'actions' => ['show-block']
            ]
        ];
        $errors = $validator->validate($property, 0, $context);
        expect($errors)->toBeEmpty();
    });

    it('fails for invalid operator', function () {
        $validator = new LogicPropertyValidator();
        $context = ['properties' => []];
        $property = [
            'id' => 'checkbox1',
            'name' => 'Checkbox Field',
            'type' => 'checkbox',
            'logic' => [
                'conditions' => [
                    'operatorIdentifier' => 'and',
                    'children' => [
                        [
                            'identifier' => 'test-id',
                            'value' => [
                                'operator' => 'invalid_operator',
                                'property_meta' => [
                                    'id' => 'test-id',
                                    'type' => 'checkbox'
                                ]
                            ]
                        ]
                    ]
                ],
                'actions' => ['show-block']
            ]
        ];
        $errors = $validator->validate($property, 0, $context);
        expect($errors)->toHaveKey('logic.conditions.children.0.value.operator');
        expect($errors['logic.conditions.children.0.value.operator'])->toContain('is not available');
    });
});
