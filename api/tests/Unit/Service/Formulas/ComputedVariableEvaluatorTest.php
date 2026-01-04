<?php

use App\Service\Formulas\ComputedVariableEvaluator;

describe('ComputedVariableEvaluator', function () {
    it('evaluates a single computed variable', function () {
        $variables = [
            ['id' => 'cv_double', 'name' => 'Double', 'formula' => '{value} * 2']
        ];
        $submissionData = ['value' => 10];

        $evaluator = new ComputedVariableEvaluator($variables, $submissionData);
        $results = $evaluator->evaluateAll();

        expect($results)->toHaveKey('cv_double');
        expect($results['cv_double'])->toBe(20.0);
    });

    it('evaluates computed variables in dependency order', function () {
        $variables = [
            ['id' => 'cv_total', 'name' => 'Total', 'formula' => '{cv_subtotal} + {cv_tax}'],
            ['id' => 'cv_subtotal', 'name' => 'Subtotal', 'formula' => '{price} * {quantity}'],
            ['id' => 'cv_tax', 'name' => 'Tax', 'formula' => '{cv_subtotal} * 0.1']
        ];
        $submissionData = ['price' => 100, 'quantity' => 5];

        $evaluator = new ComputedVariableEvaluator($variables, $submissionData);
        $results = $evaluator->evaluateAll();

        expect($results['cv_subtotal'])->toBe(500.0);
        expect($results['cv_tax'])->toBe(50.0);
        expect($results['cv_total'])->toBe(550.0);
    });

    it('evaluates a single variable by ID', function () {
        $variables = [
            ['id' => 'cv_sum', 'name' => 'Sum', 'formula' => '{a} + {b}']
        ];
        $submissionData = ['a' => 3, 'b' => 7];

        $evaluator = new ComputedVariableEvaluator($variables, $submissionData);
        $result = $evaluator->evaluate('cv_sum');

        expect($result)->toBe(10.0);
    });

    it('handles string formulas', function () {
        $variables = [
            ['id' => 'cv_greeting', 'name' => 'Greeting', 'formula' => 'CONCAT("Hello, ", {name}, "!")']
        ];
        $submissionData = ['name' => 'John'];

        $evaluator = new ComputedVariableEvaluator($variables, $submissionData);
        $results = $evaluator->evaluateAll();

        expect($results['cv_greeting'])->toBe('Hello, John!');
    });

    it('handles conditional formulas', function () {
        $variables = [
            ['id' => 'cv_grade', 'name' => 'Grade', 'formula' => 'IF({score} >= 90, "A", IF({score} >= 80, "B", "C"))']
        ];

        $evaluator1 = new ComputedVariableEvaluator($variables, ['score' => 95]);
        expect($evaluator1->evaluate('cv_grade'))->toBe('A');

        $evaluator2 = new ComputedVariableEvaluator($variables, ['score' => 85]);
        expect($evaluator2->evaluate('cv_grade'))->toBe('B');

        $evaluator3 = new ComputedVariableEvaluator($variables, ['score' => 70]);
        expect($evaluator3->evaluate('cv_grade'))->toBe('C');
    });

    it('handles missing field values gracefully', function () {
        $variables = [
            ['id' => 'cv_result', 'name' => 'Result', 'formula' => 'IFBLANK({optional}, "default")']
        ];
        $submissionData = [];

        $evaluator = new ComputedVariableEvaluator($variables, $submissionData);
        $result = $evaluator->evaluate('cv_result');

        expect($result)->toBe('default');
    });

    it('returns null for invalid variable IDs', function () {
        $variables = [
            ['id' => 'cv_test', 'name' => 'Test', 'formula' => '1 + 1']
        ];

        $evaluator = new ComputedVariableEvaluator($variables, []);
        $result = $evaluator->evaluate('cv_nonexistent');

        expect($result)->toBe(null);
    });

    it('handles empty variable list', function () {
        $evaluator = new ComputedVariableEvaluator([], ['field' => 'value']);
        $results = $evaluator->evaluateAll();

        expect($results)->toBe([]);
    });

    it('handles complex math expressions', function () {
        $variables = [
            ['id' => 'cv_complex', 'name' => 'Complex', 'formula' => 'ROUND(SQRT(POWER({a}, 2) + POWER({b}, 2)), 2)']
        ];
        $submissionData = ['a' => 3, 'b' => 4];

        $evaluator = new ComputedVariableEvaluator($variables, $submissionData);
        $result = $evaluator->evaluate('cv_complex');

        expect($result)->toBe(5.0);
    });

    it('evaluates variables with array field values', function () {
        $variables = [
            ['id' => 'cv_sum', 'name' => 'Sum', 'formula' => 'SUM({values})']
        ];
        $submissionData = ['values' => [1, 2, 3, 4, 5]];

        $evaluator = new ComputedVariableEvaluator($variables, $submissionData);
        $result = $evaluator->evaluate('cv_sum');

        expect($result)->toBe(15.0);
    });
});
