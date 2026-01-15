<?php

/**
 * Formula Engine Parity Tests
 *
 * These tests ensure that the PHP formula engine produces identical results
 * to the JavaScript implementation. Test cases are loaded from a shared JSON
 * fixture file that both PHP and JS tests read.
 */

use App\Service\Formulas\Evaluator;

// Load the parity test cases from fixtures
$parityTestsPath = __DIR__ . '/../../../fixtures/formula-parity-tests.json';
$parityTests = [];

if (file_exists($parityTestsPath)) {
    $json = json_decode(file_get_contents($parityTestsPath), true);
    $parityTests = $json['tests'] ?? [];
}

describe('Formula Engine Parity Tests', function () use ($parityTests) {
    if (empty($parityTests)) {
        it('has parity test cases loaded', function () {
            $this->markTestSkipped('Parity test cases not found at shared/formula-parity-tests.json');
        });

        return;
    }

    foreach ($parityTests as $testCase) {
        $name = $testCase['name'];
        $formula = $testCase['formula'];
        $context = $testCase['context'] ?? [];
        $expected = $testCase['expected'];

        it("parity: {$name}", function () use ($formula, $context, $expected) {
            $result = Evaluator::evaluate($formula, $context);

            // Handle floating point comparison
            if (is_float($expected) && is_float($result)) {
                expect(round($result, 10))->toBe(round($expected, 10));
            } elseif (is_float($result) && is_int($expected)) {
                // PHP often returns floats where JS returns integers
                expect((int) $result)->toBe($expected);
            } else {
                expect($result)->toBe($expected);
            }
        });
    }
});
