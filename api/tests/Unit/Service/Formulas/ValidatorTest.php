<?php

use App\Service\Formulas\Validator;

describe('Formula Validator', function () {
    describe('syntax validation', function () {
        it('validates correct syntax', function () {
            $result = Validator::validate('1 + 2');
            expect($result['valid'])->toBe(true);
            expect($result['errors'])->toHaveCount(0);
        });

        it('rejects empty formulas', function () {
            $result = Validator::validate('');
            expect($result['valid'])->toBe(false);
            expect($result['errors'][0]['message'])->toContain('empty');
        });

        it('rejects whitespace-only formulas', function () {
            $result = Validator::validate('   ');
            expect($result['valid'])->toBe(false);
        });

        it('rejects invalid syntax', function () {
            $result = Validator::validate('1 + + 2');
            expect($result['valid'])->toBe(false);
        });

        it('rejects unterminated strings', function () {
            $result = Validator::validate('"unterminated');
            expect($result['valid'])->toBe(false);
        });
    });

    describe('field reference validation', function () {
        it('validates known field references', function () {
            $fields = [
                ['id' => 'field1', 'name' => 'Field 1'],
                ['id' => 'field2', 'name' => 'Field 2']
            ];
            $result = Validator::validate('{field1} + {field2}', [
                'availableFields' => $fields
            ]);
            expect($result['valid'])->toBe(true);
        });

        it('rejects unknown field references', function () {
            $fields = [
                ['id' => 'field1', 'name' => 'Field 1']
            ];
            $result = Validator::validate('{unknown_field}', [
                'availableFields' => $fields
            ]);
            expect($result['valid'])->toBe(false);
            expect($result['errors'][0]['message'])->toContain('Unknown field');
        });

        it('suggests similar field names', function () {
            $fields = [
                ['id' => 'field1', 'name' => 'Field 1'],
                ['id' => 'field2', 'name' => 'Field 2']
            ];
            $result = Validator::validate('{field}', [
                'availableFields' => $fields
            ]);
            expect($result['valid'])->toBe(false);
            expect($result['errors'][0]['message'])->toContain('Did you mean');
        });
    });

    describe('function validation', function () {
        it('validates known functions', function () {
            $result = Validator::validate('SUM(1, 2, 3)');
            expect($result['valid'])->toBe(true);
        });

        it('rejects unknown functions', function () {
            $result = Validator::validate('UNKNOWN_FUNC()');
            expect($result['valid'])->toBe(false);
            expect($result['errors'][0]['message'])->toContain('Unknown function');
        });

        it('suggests similar function names', function () {
            $result = Validator::validate('SUMM(1, 2)');
            expect($result['valid'])->toBe(false);
            expect($result['errors'][0]['message'])->toContain('Did you mean');
        });
    });

    describe('computed variable validation', function () {
        it('validates references to computed variables', function () {
            $fields = [['id' => 'field1', 'name' => 'Field 1']];
            $variables = [
                ['id' => 'cv_var1', 'name' => 'Variable 1'],
                ['id' => 'cv_var2', 'name' => 'Variable 2']
            ];

            $result = Validator::validate('{cv_var1} + {field1}', [
                'availableFields' => $fields,
                'availableVariables' => $variables
            ]);
            expect($result['valid'])->toBe(true);
        });

        it('detects self-reference', function () {
            $fields = [['id' => 'field1', 'name' => 'Field 1']];
            $variables = [['id' => 'cv_var1', 'name' => 'Variable 1']];

            $result = Validator::validate('{cv_var1} + 1', [
                'availableFields' => $fields,
                'availableVariables' => $variables,
                'currentVariableId' => 'cv_var1'
            ]);
            expect($result['valid'])->toBe(false);
            expect($result['errors'][0]['message'])->toContain('reference itself');
        });
    });

    describe('extractFieldReferences', function () {
        it('extracts field IDs from formula', function () {
            $refs = Validator::extractFieldReferences('{field1} + {field2} * {field3}');
            expect($refs)->toContain('field1');
            expect($refs)->toContain('field2');
            expect($refs)->toContain('field3');
        });

        it('handles formulas without field references', function () {
            $refs = Validator::extractFieldReferences('1 + 2 + 3');
            expect($refs)->toHaveCount(0);
        });

        it('handles duplicate field references', function () {
            $refs = Validator::extractFieldReferences('{field1} + {field1}');
            expect(count($refs))->toBe(2);
        });
    });
});
