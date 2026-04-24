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

    describe('mention value resolution', function () {
        function mentionHtml(string $fieldId, string $fieldName, string $fallback = ''): string
        {
            return '<span mention mention-field-id="' . $fieldId . '" mention-field-name="' . $fieldName . '" mention-fallback="' . $fallback . '">@' . $fieldName . '</span>';
        }

        it('resolves single bare mention to raw scalar value for text equals', function () {
            $condition = [
                'value' => [
                    'property_meta' => [
                        'id' => 'text_field',
                        'type' => 'text',
                    ],
                    'operator' => 'equals',
                    'value' => mentionHtml('other_field', 'Other Field'),
                ],
            ];

            $formData = ['text_field' => 'hello', 'other_field' => 'hello'];
            expect(FormLogicConditionChecker::conditionsMet($condition, $formData))->toBeTrue();

            $formData = ['text_field' => 'hello', 'other_field' => 'world'];
            expect(FormLogicConditionChecker::conditionsMet($condition, $formData))->toBeFalse();
        });

        it('resolves single bare mention to raw numeric value for number comparison', function () {
            $condition = [
                'value' => [
                    'property_meta' => [
                        'id' => 'number_field',
                        'type' => 'number',
                    ],
                    'operator' => 'greater_than',
                    'value' => mentionHtml('threshold_field', 'Threshold'),
                ],
            ];

            $formData = ['number_field' => 50, 'threshold_field' => 40];
            expect(FormLogicConditionChecker::conditionsMet($condition, $formData))->toBeTrue();

            $formData = ['number_field' => 30, 'threshold_field' => 40];
            expect(FormLogicConditionChecker::conditionsMet($condition, $formData))->toBeFalse();
        });

        it('resolves single bare mention with fallback when field is missing', function () {
            $condition = [
                'value' => [
                    'property_meta' => [
                        'id' => 'text_field',
                        'type' => 'text',
                    ],
                    'operator' => 'equals',
                    'value' => mentionHtml('missing_field', 'Missing', 'default_val'),
                ],
            ];

            $formData = ['text_field' => 'default_val'];
            expect(FormLogicConditionChecker::conditionsMet($condition, $formData))->toBeTrue();
        });

        it('resolves single bare mention to null when field missing and no fallback', function () {
            $condition = [
                'value' => [
                    'property_meta' => [
                        'id' => 'text_field',
                        'type' => 'text',
                    ],
                    'operator' => 'equals',
                    'value' => mentionHtml('missing_field', 'Missing'),
                ],
            ];

            $formData = ['text_field' => 'anything'];
            expect(FormLogicConditionChecker::conditionsMet($condition, $formData))->toBeFalse();
        });

        it('resolves mixed content with text and mention to plain string', function () {
            $condition = [
                'value' => [
                    'property_meta' => [
                        'id' => 'text_field',
                        'type' => 'text',
                    ],
                    'operator' => 'equals',
                    'value' => 'Hello ' . mentionHtml('name_field', 'Name'),
                ],
            ];

            $formData = ['text_field' => 'Hello Alice', 'name_field' => 'Alice'];
            expect(FormLogicConditionChecker::conditionsMet($condition, $formData))->toBeTrue();

            $formData = ['text_field' => 'Hello Bob', 'name_field' => 'Alice'];
            expect(FormLogicConditionChecker::conditionsMet($condition, $formData))->toBeFalse();
        });

        it('resolves multiple mentions to combined plain string', function () {
            $condition = [
                'value' => [
                    'property_meta' => [
                        'id' => 'text_field',
                        'type' => 'text',
                    ],
                    'operator' => 'equals',
                    'value' => mentionHtml('first', 'First') . ' ' . mentionHtml('last', 'Last'),
                ],
            ];

            $formData = [
                'text_field' => 'John Doe',
                'first' => 'John',
                'last' => 'Doe',
            ];
            expect(FormLogicConditionChecker::conditionsMet($condition, $formData))->toBeTrue();
        });

        it('does not crash text contains when mention resolves to array', function () {
            $condition = [
                'value' => [
                    'property_meta' => [
                        'id' => 'text_field',
                        'type' => 'text',
                    ],
                    'operator' => 'contains',
                    'value' => mentionHtml('multi_field', 'Multi'),
                ],
            ];

            $formData = ['text_field' => 'option1 option2', 'multi_field' => ['option1', 'option2']];
            expect(FormLogicConditionChecker::conditionsMet($condition, $formData))->toBeFalse();
        });

        it('does not crash starts_with when mention resolves to array', function () {
            $condition = [
                'value' => [
                    'property_meta' => [
                        'id' => 'text_field',
                        'type' => 'text',
                    ],
                    'operator' => 'starts_with',
                    'value' => mentionHtml('multi_field', 'Multi'),
                ],
            ];

            $formData = ['text_field' => 'hello', 'multi_field' => ['opt1', 'opt2']];
            expect(FormLogicConditionChecker::conditionsMet($condition, $formData))->toBeFalse();
        });

        it('does not crash ends_with when mention resolves to array', function () {
            $condition = [
                'value' => [
                    'property_meta' => [
                        'id' => 'text_field',
                        'type' => 'text',
                    ],
                    'operator' => 'ends_with',
                    'value' => mentionHtml('multi_field', 'Multi'),
                ],
            ];

            $formData = ['text_field' => 'hello', 'multi_field' => ['opt1', 'opt2']];
            expect(FormLogicConditionChecker::conditionsMet($condition, $formData))->toBeFalse();
        });

        it('does not crash matches_regex when mention resolves to array', function () {
            $condition = [
                'value' => [
                    'property_meta' => [
                        'id' => 'text_field',
                        'type' => 'text',
                    ],
                    'operator' => 'matches_regex',
                    'value' => mentionHtml('multi_field', 'Multi'),
                ],
            ];

            $formData = ['text_field' => 'hello', 'multi_field' => ['opt1', 'opt2']];
            expect(FormLogicConditionChecker::conditionsMet($condition, $formData))->toBeFalse();
        });

        it('does not crash does_not_match_regex when mention resolves to array', function () {
            $condition = [
                'value' => [
                    'property_meta' => [
                        'id' => 'text_field',
                        'type' => 'text',
                    ],
                    'operator' => 'does_not_match_regex',
                    'value' => mentionHtml('multi_field', 'Multi'),
                ],
            ];

            $formData = ['text_field' => 'hello', 'multi_field' => ['opt1', 'opt2']];
            expect(FormLogicConditionChecker::conditionsMet($condition, $formData))->toBeTrue();
        });

        it('does not crash when mention resolves to associative array (matrix)', function () {
            $condition = [
                'value' => [
                    'property_meta' => [
                        'id' => 'text_field',
                        'type' => 'text',
                    ],
                    'operator' => 'equals',
                    'value' => mentionHtml('matrix_field', 'Matrix'),
                ],
            ];

            $formData = ['text_field' => 'hello', 'matrix_field' => ['row1' => 'col1']];
            expect(FormLogicConditionChecker::conditionsMet($condition, $formData))->toBeFalse();
        });

        it('handles mention in number equals with matching numeric values', function () {
            $condition = [
                'value' => [
                    'property_meta' => [
                        'id' => 'number_field',
                        'type' => 'number',
                    ],
                    'operator' => 'equals',
                    'value' => mentionHtml('other_number', 'Other Number'),
                ],
            ];

            $formData = ['number_field' => 42, 'other_number' => 42];
            expect(FormLogicConditionChecker::conditionsMet($condition, $formData))->toBeTrue();

            $formData = ['number_field' => 42, 'other_number' => 43];
            expect(FormLogicConditionChecker::conditionsMet($condition, $formData))->toBeFalse();
        });

        it('passes through non-mention values unchanged', function () {
            $condition = [
                'value' => [
                    'property_meta' => [
                        'id' => 'text_field',
                        'type' => 'text',
                    ],
                    'operator' => 'equals',
                    'value' => 'plain text value',
                ],
            ];

            $formData = ['text_field' => 'plain text value'];
            expect(FormLogicConditionChecker::conditionsMet($condition, $formData))->toBeTrue();
        });
    });

    describe('computed variable mention resolution', function () {
        function cvMentionHtml(string $cvId, string $cvName, string $fallback = ''): string
        {
            return '<span mention mention-field-id="' . $cvId . '" mention-field-name="' . $cvName . '" mention-fallback="' . $fallback . '">@' . $cvName . '</span>';
        }

        it('resolves mention referencing computed variable via conditionsMetWithForm', function () {
            $form = new \App\Models\Forms\Form();
            $form->computed_variables = [
                [
                    'id' => 'cv_total',
                    'name' => 'Total',
                    'formula' => '10',
                    'type' => 'number',
                ],
            ];

            $condition = [
                'value' => [
                    'property_meta' => [
                        'id' => 'number_field',
                        'type' => 'number',
                    ],
                    'operator' => 'equals',
                    'value' => cvMentionHtml('cv_total', 'Total'),
                ],
            ];

            $formData = ['number_field' => 10];
            expect(FormLogicConditionChecker::conditionsMetWithForm($condition, $formData, $form))->toBeTrue();

            $formData = ['number_field' => 5];
            expect(FormLogicConditionChecker::conditionsMetWithForm($condition, $formData, $form))->toBeFalse();
        });

        it('falls back when computed variable not resolved via conditionsMet', function () {
            $condition = [
                'value' => [
                    'property_meta' => [
                        'id' => 'number_field',
                        'type' => 'number',
                    ],
                    'operator' => 'equals',
                    'value' => cvMentionHtml('cv_missing', 'Missing CV', '99'),
                ],
            ];

            $formData = ['number_field' => '99'];
            expect(FormLogicConditionChecker::conditionsMet($condition, $formData))->toBeTrue();
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

        it('preserves computed variable support inside nested groups', function () {
            $form = new \App\Models\Forms\Form();
            $form->computed_variables = [
                [
                    'id' => 'cv_total',
                    'name' => 'Total',
                    'formula' => '{price} * {quantity}',
                    'type' => 'number',
                ],
            ];

            $condition = [
                'operatorIdentifier' => 'and',
                'children' => [
                    [
                        'operatorIdentifier' => 'or',
                        'children' => [
                            [
                                'value' => [
                                    'property_meta' => [
                                        'id' => 'cv_total',
                                        'type' => 'computed',
                                    ],
                                    'operator' => 'greater_than',
                                    'value' => 100,
                                ],
                            ],
                        ],
                    ],
                    [
                        'value' => [
                            'property_meta' => [
                                'id' => 'price',
                                'type' => 'number',
                            ],
                            'operator' => 'greater_than',
                            'value' => 0,
                        ],
                    ],
                ],
            ];

            expect(FormLogicConditionChecker::conditionsMetWithForm($condition, [
                'price' => 50,
                'quantity' => 3,
            ], $form))->toBeTrue();

            expect(FormLogicConditionChecker::conditionsMetWithForm($condition, [
                'price' => 10,
                'quantity' => 5,
            ], $form))->toBeFalse();
        });
    });
});
