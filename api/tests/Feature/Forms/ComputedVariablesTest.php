<?php

use App\Open\MentionParser;
use App\Service\Forms\FormLogicConditionChecker;
use App\Service\Formulas\ComputedVariableEvaluator;

uses(\Tests\TestHelpers::class);

beforeEach(function () {
    $this->user = $this->createUser();
    $this->workspace = $this->createUserWorkspace($this->user);
});

describe('Computed Variables Integration', function () {
    describe('Form storage', function () {
        it('stores computed variables on form', function () {
            $form = $this->createForm($this->user, $this->workspace, [
                'computed_variables' => [
                    [
                        'id' => 'cv_total',
                        'name' => 'Total',
                        'formula' => '{price} * {quantity}'
                    ]
                ]
            ]);

            expect($form->computed_variables)->toHaveCount(1);
            expect($form->computed_variables[0]['id'])->toBe('cv_total');
        });

        it('returns computed variables in API response', function () {
            $form = $this->createForm($this->user, $this->workspace, [
                'computed_variables' => [
                    ['id' => 'cv_test', 'name' => 'Test', 'formula' => '1 + 1']
                ]
            ]);

            $response = $this->actingAs($this->user)
                ->getJson(route('forms.show', $form->slug))
                ->assertSuccessful();

            expect($response->json('computed_variables'))->toHaveCount(1);
        });
    });

    describe('MentionParser with computed variables', function () {
        it('replaces computed variable mentions', function () {
            $content = '<p>Total: <span mention mention-field-id="cv_total">Total</span></p>';
            $data = [
                ['id' => 'price', 'value' => 100],
                ['id' => 'quantity', 'value' => 5]
            ];
            $computedValues = ['cv_total' => 500];

            $parser = new MentionParser($content, $data, $computedValues);
            $result = $parser->parse();

            expect($result)->toBe('<p>Total: 500</p>');
        });

        it('prioritizes regular fields over computed variables', function () {
            $content = '<p>Value: <span mention mention-field-id="field1">Value</span></p>';
            $data = [['id' => 'field1', 'value' => 'regular']];
            $computedValues = ['field1' => 'computed'];

            $parser = new MentionParser($content, $data, $computedValues);
            $result = $parser->parse();

            expect($result)->toBe('<p>Value: regular</p>');
        });

        it('uses fallback when computed variable has no value', function () {
            $content = '<p>Result: <span mention mention-field-id="cv_missing" mention-fallback="N/A">Result</span></p>';
            $computedValues = [];

            $parser = new MentionParser($content, [], $computedValues);
            $result = $parser->parse();

            expect($result)->toBe('<p>Result: N/A</p>');
        });

        it('renders boolean true as Yes', function () {
            $content = '<p>Active: <span mention mention-field-id="cv_active">Active</span></p>';
            $computedValues = ['cv_active' => true];

            $parser = new MentionParser($content, [], $computedValues);
            $result = $parser->parse();

            expect($result)->toBe('<p>Active: Yes</p>');
        });

        it('renders boolean false as No', function () {
            $content = '<p>Active: <span mention mention-field-id="cv_active">Active</span></p>';
            $computedValues = ['cv_active' => false];

            $parser = new MentionParser($content, [], $computedValues);
            $result = $parser->parse();

            expect($result)->toBe('<p>Active: No</p>');
        });
    });

    describe('FormLogicConditionChecker with computed variables', function () {
        it('evaluates conditions with numeric computed variables', function () {
            $form = $this->createForm($this->user, $this->workspace, [
                'computed_variables' => [
                    ['id' => 'cv_total', 'name' => 'Total', 'formula' => '{price} * {quantity}']
                ]
            ]);

            $condition = [
                'value' => [
                    'property_meta' => [
                        'id' => 'cv_total',
                        'type' => 'computed'
                    ],
                    'operator' => 'greater_than',
                    'value' => 100
                ]
            ];

            $formData = ['price' => 50, 'quantity' => 3];

            // Total = 150, which is > 100
            expect(FormLogicConditionChecker::conditionsMetWithForm($condition, $formData, $form))->toBeTrue();

            $formData2 = ['price' => 10, 'quantity' => 5];
            // Total = 50, which is not > 100
            expect(FormLogicConditionChecker::conditionsMetWithForm($condition, $formData2, $form))->toBeFalse();
        });

        it('evaluates conditions with text computed variables', function () {
            $form = $this->createForm($this->user, $this->workspace, [
                'computed_variables' => [
                    ['id' => 'cv_greeting', 'name' => 'Greeting', 'formula' => 'CONCAT("Hello, ", {name})']
                ]
            ]);

            $condition = [
                'value' => [
                    'property_meta' => [
                        'id' => 'cv_greeting',
                        'type' => 'computed'
                    ],
                    'operator' => 'contains',
                    'value' => 'John'
                ]
            ];

            $formData = ['name' => 'John'];
            expect(FormLogicConditionChecker::conditionsMetWithForm($condition, $formData, $form))->toBeTrue();

            $formData2 = ['name' => 'Jane'];
            expect(FormLogicConditionChecker::conditionsMetWithForm($condition, $formData2, $form))->toBeFalse();
        });

        it('handles is_empty operator for computed variables', function () {
            $form = $this->createForm($this->user, $this->workspace, [
                'computed_variables' => [
                    ['id' => 'cv_result', 'name' => 'Result', 'formula' => 'IFBLANK({optional}, "")']
                ]
            ]);

            $condition = [
                'value' => [
                    'property_meta' => [
                        'id' => 'cv_result',
                        'type' => 'computed'
                    ],
                    'operator' => 'is_empty',
                    'value' => true
                ]
            ];

            $formData = [];
            expect(FormLogicConditionChecker::conditionsMetWithForm($condition, $formData, $form))->toBeTrue();

            $formData2 = ['optional' => 'has value'];
            expect(FormLogicConditionChecker::conditionsMetWithForm($condition, $formData2, $form))->toBeFalse();
        });
    });

    describe('Computed variable evaluation', function () {
        it('evaluates chained computed variables', function () {
            $variables = [
                ['id' => 'cv_subtotal', 'name' => 'Subtotal', 'formula' => '{price} * {qty}'],
                ['id' => 'cv_tax', 'name' => 'Tax', 'formula' => '{cv_subtotal} * {tax_rate}'],
                ['id' => 'cv_total', 'name' => 'Total', 'formula' => '{cv_subtotal} + {cv_tax}']
            ];
            $submissionData = ['price' => 100, 'qty' => 2, 'tax_rate' => 0.1];

            $evaluator = new ComputedVariableEvaluator($variables, $submissionData);
            $results = $evaluator->evaluateAll();

            expect($results['cv_subtotal'])->toBe(200.0);
            expect($results['cv_tax'])->toBe(20.0);
            expect($results['cv_total'])->toBe(220.0);
        });
    });
});
