<?php

use App\Service\Forms\FormLogicConditionChecker;

describe('FormLogicConditionChecker', function () {
    describe('checkbox conditions', function () {
        it('handles is_checked operator correctly', function () {
            $condition = [
                'value' => [
                    'property_meta' => [
                        'id' => 'checkbox_field',
                        'type' => 'checkbox'
                    ],
                    'operator' => 'is_checked',
                    'value' => true
                ]
            ];

            $formData = ['checkbox_field' => true];
            expect(FormLogicConditionChecker::conditionsMet($condition, $formData))->toBeTrue();

            $formData = ['checkbox_field' => false];
            expect(FormLogicConditionChecker::conditionsMet($condition, $formData))->toBeFalse();
        });

        it('handles is_not_checked operator correctly', function () {
            $condition = [
                'value' => [
                    'property_meta' => [
                        'id' => 'checkbox_field',
                        'type' => 'checkbox'
                    ],
                    'operator' => 'is_not_checked',
                    'value' => true
                ]
            ];

            $formData = ['checkbox_field' => false];
            expect(FormLogicConditionChecker::conditionsMet($condition, $formData))->toBeTrue();

            $formData = ['checkbox_field' => true];
            expect(FormLogicConditionChecker::conditionsMet($condition, $formData))->toBeFalse();
        });

        it('handles legacy equals operator correctly', function () {
            $condition = [
                'value' => [
                    'property_meta' => [
                        'id' => 'checkbox_field',
                        'type' => 'checkbox'
                    ],
                    'operator' => 'equals',
                    'value' => true
                ]
            ];

            $formData = ['checkbox_field' => true];
            expect(FormLogicConditionChecker::conditionsMet($condition, $formData))->toBeTrue();

            $formData = ['checkbox_field' => false];
            expect(FormLogicConditionChecker::conditionsMet($condition, $formData))->toBeFalse();
        });

        it('handles legacy does_not_equal operator correctly', function () {
            $condition = [
                'value' => [
                    'property_meta' => [
                        'id' => 'checkbox_field',
                        'type' => 'checkbox'
                    ],
                    'operator' => 'does_not_equal',
                    'value' => true
                ]
            ];

            $formData = ['checkbox_field' => false];
            expect(FormLogicConditionChecker::conditionsMet($condition, $formData))->toBeTrue();

            $formData = ['checkbox_field' => true];
            expect(FormLogicConditionChecker::conditionsMet($condition, $formData))->toBeFalse();
        });

        it('handles null values correctly', function () {
            $condition = [
                'value' => [
                    'property_meta' => [
                        'id' => 'checkbox_field',
                        'type' => 'checkbox'
                    ],
                    'operator' => 'is_checked',
                    'value' => true
                ]
            ];

            // Null should be treated as unchecked (false)
            $formData = ['checkbox_field' => null];
            expect(FormLogicConditionChecker::conditionsMet($condition, $formData))->toBeFalse();

            $condition['value']['operator'] = 'is_not_checked';
            expect(FormLogicConditionChecker::conditionsMet($condition, $formData))->toBeTrue();
        });

        it('handles missing values correctly', function () {
            $condition = [
                'value' => [
                    'property_meta' => [
                        'id' => 'checkbox_field',
                        'type' => 'checkbox'
                    ],
                    'operator' => 'is_checked',
                    'value' => true
                ]
            ];

            // Missing value should be treated as unchecked (false)
            $formData = [];
            expect(FormLogicConditionChecker::conditionsMet($condition, $formData))->toBeFalse();

            $condition['value']['operator'] = 'is_not_checked';
            expect(FormLogicConditionChecker::conditionsMet($condition, $formData))->toBeTrue();
        });
    });

    describe('number conditions', function () {
        it('handles comparison operators correctly', function () {
            $condition = [
                'value' => [
                    'property_meta' => [
                        'id' => 'number_field',
                        'type' => 'number'
                    ],
                    'operator' => 'equals',
                    'value' => 42
                ]
            ];

            $formData = ['number_field' => 42];
            expect(FormLogicConditionChecker::conditionsMet($condition, $formData))->toBeTrue();

            $formData = ['number_field' => 41];
            expect(FormLogicConditionChecker::conditionsMet($condition, $formData))->toBeFalse();

            $condition['value']['operator'] = 'greater_than';
            $condition['value']['value'] = 40;
            $formData = ['number_field' => 41];
            expect(FormLogicConditionChecker::conditionsMet($condition, $formData))->toBeTrue();

            $condition['value']['operator'] = 'less_than';
            $condition['value']['value'] = 42;
            expect(FormLogicConditionChecker::conditionsMet($condition, $formData))->toBeTrue();
        });

        it('handles zero values correctly', function () {
            $condition = [
                'value' => [
                    'property_meta' => [
                        'id' => 'number_field',
                        'type' => 'number'
                    ],
                    'operator' => 'equals',
                    'value' => 0
                ]
            ];

            // Test zero equality
            $formData = ['number_field' => 0];
            expect(FormLogicConditionChecker::conditionsMet($condition, $formData))->toBeTrue();

            $formData = ['number_field' => 1];
            expect(FormLogicConditionChecker::conditionsMet($condition, $formData))->toBeFalse();

            // Test less than with zero
            $condition['value']['operator'] = 'less_than';
            $condition['value']['value'] = 0;
            $formData = ['number_field' => -1];
            expect(FormLogicConditionChecker::conditionsMet($condition, $formData))->toBeTrue();

            $formData = ['number_field' => 0];
            expect(FormLogicConditionChecker::conditionsMet($condition, $formData))->toBeFalse();

            // Test greater than with zero
            $condition['value']['operator'] = 'greater_than';
            $condition['value']['value'] = 0;
            $formData = ['number_field' => 1];
            expect(FormLogicConditionChecker::conditionsMet($condition, $formData))->toBeTrue();

            $formData = ['number_field' => 0];
            expect(FormLogicConditionChecker::conditionsMet($condition, $formData))->toBeFalse();
        });

        it('handles negative numbers correctly', function () {
            $condition = [
                'value' => [
                    'property_meta' => [
                        'id' => 'number_field',
                        'type' => 'number'
                    ],
                    'operator' => 'equals',
                    'value' => -5
                ]
            ];

            // Test negative number equality
            $formData = ['number_field' => -5];
            expect(FormLogicConditionChecker::conditionsMet($condition, $formData))->toBeTrue();

            // Test less than with negative numbers
            $condition['value']['operator'] = 'less_than';
            $condition['value']['value'] = -5;
            $formData = ['number_field' => -10];
            expect(FormLogicConditionChecker::conditionsMet($condition, $formData))->toBeTrue();

            $formData = ['number_field' => -5];
            expect(FormLogicConditionChecker::conditionsMet($condition, $formData))->toBeFalse();

            $formData = ['number_field' => 0];
            expect(FormLogicConditionChecker::conditionsMet($condition, $formData))->toBeFalse();

            // Test greater than with negative numbers
            $condition['value']['operator'] = 'greater_than';
            $condition['value']['value'] = -10;
            $formData = ['number_field' => -5];
            expect(FormLogicConditionChecker::conditionsMet($condition, $formData))->toBeTrue();

            $formData = ['number_field' => -10];
            expect(FormLogicConditionChecker::conditionsMet($condition, $formData))->toBeFalse();
        });

        it('handles empty checks correctly', function () {
            $condition = [
                'value' => [
                    'property_meta' => [
                        'id' => 'number_field',
                        'type' => 'number'
                    ],
                    'operator' => 'is_empty',
                    'value' => true
                ]
            ];

            $formData = ['number_field' => null];
            expect(FormLogicConditionChecker::conditionsMet($condition, $formData))->toBeTrue();

            $formData = ['number_field' => 42];
            expect(FormLogicConditionChecker::conditionsMet($condition, $formData))->toBeFalse();

            $condition['value']['operator'] = 'is_not_empty';
            expect(FormLogicConditionChecker::conditionsMet($condition, $formData))->toBeTrue();
        });
    });

    describe('text conditions', function () {
        it('handles string comparison operators correctly', function () {
            $condition = [
                'value' => [
                    'property_meta' => [
                        'id' => 'text_field',
                        'type' => 'text'
                    ],
                    'operator' => 'equals',
                    'value' => 'test'
                ]
            ];

            $formData = ['text_field' => 'test'];
            expect(FormLogicConditionChecker::conditionsMet($condition, $formData))->toBeTrue();

            $formData = ['text_field' => 'other'];
            expect(FormLogicConditionChecker::conditionsMet($condition, $formData))->toBeFalse();

            $condition['value']['operator'] = 'contains';
            $condition['value']['value'] = 'es';
            $formData = ['text_field' => 'test'];
            expect(FormLogicConditionChecker::conditionsMet($condition, $formData))->toBeTrue();

            $condition['value']['operator'] = 'starts_with';
            $condition['value']['value'] = 'te';
            expect(FormLogicConditionChecker::conditionsMet($condition, $formData))->toBeTrue();

            $condition['value']['operator'] = 'ends_with';
            $condition['value']['value'] = 'st';
            expect(FormLogicConditionChecker::conditionsMet($condition, $formData))->toBeTrue();

            // Test does_not_contain
            $condition['value']['operator'] = 'does_not_contain';
            $condition['value']['value'] = 'xyz';
            expect(FormLogicConditionChecker::conditionsMet($condition, $formData))->toBeTrue();
        });

        it('handles content length operators correctly', function () {
            $condition = [
                'value' => [
                    'property_meta' => [
                        'id' => 'text_field',
                        'type' => 'text'
                    ],
                    'operator' => 'content_length_equals',
                    'value' => 4
                ]
            ];

            $formData = ['text_field' => 'test'];
            expect(FormLogicConditionChecker::conditionsMet($condition, $formData))->toBeTrue();

            $condition['value']['operator'] = 'content_length_greater_than';
            $condition['value']['value'] = 3;
            expect(FormLogicConditionChecker::conditionsMet($condition, $formData))->toBeTrue();

            $condition['value']['operator'] = 'content_length_less_than';
            $condition['value']['value'] = 5;
            expect(FormLogicConditionChecker::conditionsMet($condition, $formData))->toBeTrue();
        });

        it('handles regex operators correctly', function () {
            $condition = [
                'value' => [
                    'property_meta' => [
                        'id' => 'text_field',
                        'type' => 'text'
                    ],
                    'operator' => 'matches_regex',
                    'value' => '^test[0-9]+$'
                ]
            ];

            $formData = ['text_field' => 'test123'];
            expect(FormLogicConditionChecker::conditionsMet($condition, $formData))->toBeTrue();

            $formData = ['text_field' => 'invalid'];
            expect(FormLogicConditionChecker::conditionsMet($condition, $formData))->toBeFalse();

            // Test invalid regex pattern
            $condition['value']['value'] = '['; // Invalid regex
            expect(FormLogicConditionChecker::conditionsMet($condition, $formData))->toBeFalse();
        });
    });

    describe('date conditions', function () {
        it('handles date comparison operators correctly', function () {
            $condition = [
                'value' => [
                    'property_meta' => [
                        'id' => 'date_field',
                        'type' => 'date'
                    ],
                    'operator' => 'equals',
                    'value' => '2024-01-01'
                ]
            ];

            $formData = ['date_field' => '2024-01-01'];
            expect(FormLogicConditionChecker::conditionsMet($condition, $formData))->toBeTrue();

            $condition['value']['operator'] = 'before';
            $condition['value']['value'] = '2024-01-02';
            expect(FormLogicConditionChecker::conditionsMet($condition, $formData))->toBeTrue();

            $condition['value']['operator'] = 'after';
            $condition['value']['value'] = '2023-12-31';
            expect(FormLogicConditionChecker::conditionsMet($condition, $formData))->toBeTrue();
        });

        it('handles relative date operators correctly', function () {
            $condition = [
                'value' => [
                    'property_meta' => [
                        'id' => 'date_field',
                        'type' => 'date'
                    ],
                    'operator' => 'past_week',
                    'value' => '{}'
                ]
            ];

            $formData = ['date_field' => now()->subDays(3)->toDateString()];
            expect(FormLogicConditionChecker::conditionsMet($condition, $formData))->toBeTrue();

            $formData = ['date_field' => now()->subDays(10)->toDateString()];
            expect(FormLogicConditionChecker::conditionsMet($condition, $formData))->toBeFalse();

            $condition['value']['operator'] = 'next_week';
            $formData = ['date_field' => now()->addDays(3)->toDateString()];
            expect(FormLogicConditionChecker::conditionsMet($condition, $formData))->toBeTrue();
        });
    });

    describe('multi_select conditions', function () {
        it('handles contains operators correctly', function () {
            $condition = [
                'value' => [
                    'property_meta' => [
                        'id' => 'multi_select_field',
                        'type' => 'multi_select'
                    ],
                    'operator' => 'contains',
                    'value' => 'option1'
                ]
            ];

            $formData = ['multi_select_field' => ['option1', 'option2']];
            expect(FormLogicConditionChecker::conditionsMet($condition, $formData))->toBeTrue();

            $formData = ['multi_select_field' => ['option2', 'option3']];
            expect(FormLogicConditionChecker::conditionsMet($condition, $formData))->toBeFalse();

            // Test with array of values
            $condition['value']['value'] = ['option1', 'option2'];
            $formData = ['multi_select_field' => ['option1', 'option2', 'option3']];
            expect(FormLogicConditionChecker::conditionsMet($condition, $formData))->toBeTrue();
        });
    });

    describe('matrix conditions', function () {
        it('handles matrix comparison operators correctly', function () {
            $condition = [
                'value' => [
                    'property_meta' => [
                        'id' => 'matrix_field',
                        'type' => 'matrix'
                    ],
                    'operator' => 'equals',
                    'value' => ['row1' => 'col1', 'row2' => 'col2']
                ]
            ];

            $formData = ['matrix_field' => ['row1' => 'col1', 'row2' => 'col2']];
            expect(FormLogicConditionChecker::conditionsMet($condition, $formData))->toBeTrue();

            $formData = ['matrix_field' => ['row1' => 'col2', 'row2' => 'col2']];
            expect(FormLogicConditionChecker::conditionsMet($condition, $formData))->toBeFalse();

            $condition['value']['operator'] = 'contains';
            expect(FormLogicConditionChecker::conditionsMet($condition, $formData))->toBeTrue();
        });

        it('handles missing rows in field value for equals operator', function () {
            // Reproduces issue #1026: when a matrix condition checks for a specific row
            // but the user submission doesn't have that row yet, it should return false
            // instead of throwing "Undefined array key" error
            $condition = [
                'value' => [
                    'property_meta' => [
                        'id' => 'matrix_field',
                        'type' => 'matrix'
                    ],
                    'operator' => 'equals',
                    'value' => ['15+ Jahre' => 'Option A', 'row2' => 'col2']
                ]
            ];

            // User has only filled in one row, condition expects two rows
            $formData = ['matrix_field' => ['row2' => 'col2']];
            expect(FormLogicConditionChecker::conditionsMet($condition, $formData))->toBeFalse();

            // User has no data for matrix field
            $formData = ['matrix_field' => []];
            expect(FormLogicConditionChecker::conditionsMet($condition, $formData))->toBeFalse();

            // User has null value for matrix field
            $formData = ['matrix_field' => null];
            expect(FormLogicConditionChecker::conditionsMet($condition, $formData))->toBeFalse();
        });

        it('handles missing rows in field value for does_not_equal operator', function () {
            $condition = [
                'value' => [
                    'property_meta' => [
                        'id' => 'matrix_field',
                        'type' => 'matrix'
                    ],
                    'operator' => 'does_not_equal',
                    'value' => ['15+ Jahre' => 'Option A', 'row2' => 'col2']
                ]
            ];

            // User has only filled in one row - they "do not equal" the condition
            $formData = ['matrix_field' => ['row2' => 'col2']];
            expect(FormLogicConditionChecker::conditionsMet($condition, $formData))->toBeTrue();

            // User has no data for matrix field
            $formData = ['matrix_field' => []];
            expect(FormLogicConditionChecker::conditionsMet($condition, $formData))->toBeTrue();
        });

        it('handles missing rows in field value for contains operator', function () {
            $condition = [
                'value' => [
                    'property_meta' => [
                        'id' => 'matrix_field',
                        'type' => 'matrix'
                    ],
                    'operator' => 'contains',
                    'value' => ['15+ Jahre' => 'Option A', 'row2' => 'col2']
                ]
            ];

            // User has one matching row - contains should return true
            $formData = ['matrix_field' => ['row2' => 'col2']];
            expect(FormLogicConditionChecker::conditionsMet($condition, $formData))->toBeTrue();

            // User has no matching rows
            $formData = ['matrix_field' => ['row2' => 'col1']];
            expect(FormLogicConditionChecker::conditionsMet($condition, $formData))->toBeFalse();

            // User has empty matrix
            $formData = ['matrix_field' => []];
            expect(FormLogicConditionChecker::conditionsMet($condition, $formData))->toBeFalse();
        });
    });

    describe('group conditions', function () {
        it('handles nested AND/OR conditions correctly', function () {
            $condition = [
                'operatorIdentifier' => 'and',
                'children' => [
                    [
                        'operatorIdentifier' => 'or',
                        'children' => [
                            [
                                'value' => [
                                    'property_meta' => [
                                        'id' => 'checkbox_field',
                                        'type' => 'checkbox'
                                    ],
                                    'operator' => 'is_checked',
                                    'value' => true
                                ]
                            ],
                            [
                                'value' => [
                                    'property_meta' => [
                                        'id' => 'number_field',
                                        'type' => 'number'
                                    ],
                                    'operator' => 'greater_than',
                                    'value' => 40
                                ]
                            ]
                        ]
                    ],
                    [
                        'value' => [
                            'property_meta' => [
                                'id' => 'text_field',
                                'type' => 'text'
                            ],
                            'operator' => 'contains',
                            'value' => 'test'
                        ]
                    ]
                ]
            ];

            // Test case where OR condition is true (checkbox) and text contains 'test'
            $formData = [
                'checkbox_field' => true,
                'number_field' => 30,
                'text_field' => 'test123'
            ];
            expect(FormLogicConditionChecker::conditionsMet($condition, $formData))->toBeTrue();

            // Test case where OR condition is true (number) and text contains 'test'
            $formData = [
                'checkbox_field' => false,
                'number_field' => 41,
                'text_field' => 'test123'
            ];
            expect(FormLogicConditionChecker::conditionsMet($condition, $formData))->toBeTrue();

            // Test case where OR condition is false and text contains 'test'
            $formData = [
                'checkbox_field' => false,
                'number_field' => 30,
                'text_field' => 'test123'
            ];
            expect(FormLogicConditionChecker::conditionsMet($condition, $formData))->toBeFalse();

            // Test case where OR condition is true but text doesn't contain 'test'
            $formData = [
                'checkbox_field' => true,
                'number_field' => 30,
                'text_field' => 'other'
            ];
            expect(FormLogicConditionChecker::conditionsMet($condition, $formData))->toBeFalse();
        });

        it('handles invalid conditions gracefully', function () {
            // Test with null conditions
            expect(FormLogicConditionChecker::conditionsMet(null, []))->toBeFalse();

            // Test with empty conditions
            expect(FormLogicConditionChecker::conditionsMet([], []))->toBeFalse();

            // Test with invalid operator
            $condition = [
                'operatorIdentifier' => 'invalid',
                'children' => []
            ];
            expect(fn () => FormLogicConditionChecker::conditionsMet($condition, []))->toThrow(\Exception::class);
        });
    });
});
