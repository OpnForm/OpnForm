<?php

use App\Service\Formulas\Evaluator;

describe('Formula Evaluator', function () {
    describe('literals', function () {
        it('evaluates number literals', function () {
            expect(Evaluator::evaluate('42'))->toBe(42.0);
            expect(Evaluator::evaluate('3.14'))->toBe(3.14);
        });

        it('evaluates string literals', function () {
            expect(Evaluator::evaluate('"hello"'))->toBe('hello');
            expect(Evaluator::evaluate("'world'"))->toBe('world');
        });

        it('evaluates boolean literals', function () {
            expect(Evaluator::evaluate('TRUE'))->toBe(true);
            expect(Evaluator::evaluate('FALSE'))->toBe(false);
        });
    });

    describe('field references', function () {
        it('resolves field values from context', function () {
            $context = ['name' => 'John', 'age' => 30];

            expect(Evaluator::evaluate('{name}', $context))->toBe('John');
            expect(Evaluator::evaluate('{age}', $context))->toBe(30);
        });

        it('returns null for missing fields', function () {
            expect(Evaluator::evaluate('{missing}', []))->toBe(null);
        });
    });

    describe('arithmetic operations', function () {
        it('evaluates addition', function () {
            expect(Evaluator::evaluate('1 + 2'))->toBe(3.0);
            expect(Evaluator::evaluate('10 + 20 + 30'))->toBe(60.0);
        });

        it('evaluates subtraction', function () {
            expect(Evaluator::evaluate('10 - 3'))->toBe(7.0);
        });

        it('evaluates multiplication', function () {
            expect(Evaluator::evaluate('4 * 5'))->toBe(20.0);
        });

        it('evaluates division', function () {
            expect(Evaluator::evaluate('20 / 4'))->toBe(5.0);
        });

        it('returns null for division by zero', function () {
            expect(Evaluator::evaluate('10 / 0'))->toBe(null);
        });

        it('respects operator precedence', function () {
            expect(Evaluator::evaluate('2 + 3 * 4'))->toBe(14.0);
            expect(Evaluator::evaluate('(2 + 3) * 4'))->toBe(20.0);
        });

        it('handles string concatenation with +', function () {
            expect(Evaluator::evaluate('"Hello" + " " + "World"'))->toBe('Hello World');
        });
    });

    describe('unary operations', function () {
        it('evaluates unary minus', function () {
            expect(Evaluator::evaluate('-5'))->toBe(-5.0);
        });

        it('evaluates NOT', function () {
            expect(Evaluator::evaluate('NOT TRUE'))->toBe(false);
            expect(Evaluator::evaluate('NOT FALSE'))->toBe(true);
        });
    });

    describe('comparison operations', function () {
        it('evaluates equals', function () {
            expect(Evaluator::evaluate('1 = 1'))->toBe(true);
            expect(Evaluator::evaluate('1 = 2'))->toBe(false);
        });

        it('evaluates equals case-insensitive for strings', function () {
            expect(Evaluator::evaluate('"Hello" = "hello"'))->toBe(true);
        });

        it('evaluates not equals', function () {
            expect(Evaluator::evaluate('1 <> 2'))->toBe(true);
            expect(Evaluator::evaluate('1 <> 1'))->toBe(false);
        });

        it('evaluates less than', function () {
            expect(Evaluator::evaluate('1 < 2'))->toBe(true);
            expect(Evaluator::evaluate('2 < 1'))->toBe(false);
        });

        it('evaluates greater than', function () {
            expect(Evaluator::evaluate('2 > 1'))->toBe(true);
            expect(Evaluator::evaluate('1 > 2'))->toBe(false);
        });

        it('evaluates less than or equal', function () {
            expect(Evaluator::evaluate('1 <= 2'))->toBe(true);
            expect(Evaluator::evaluate('2 <= 2'))->toBe(true);
            expect(Evaluator::evaluate('3 <= 2'))->toBe(false);
        });

        it('evaluates greater than or equal', function () {
            expect(Evaluator::evaluate('2 >= 1'))->toBe(true);
            expect(Evaluator::evaluate('2 >= 2'))->toBe(true);
            expect(Evaluator::evaluate('1 >= 2'))->toBe(false);
        });
    });

    describe('function calls', function () {
        describe('math functions', function () {
            it('evaluates SUM', function () {
                expect(Evaluator::evaluate('SUM(1, 2, 3)'))->toBe(6.0);
            });

            it('evaluates AVERAGE', function () {
                expect(Evaluator::evaluate('AVERAGE(1, 2, 3)'))->toBe(2.0);
            });

            it('evaluates MIN', function () {
                expect(Evaluator::evaluate('MIN(5, 3, 8, 1)'))->toBe(1.0);
            });

            it('evaluates MAX', function () {
                expect(Evaluator::evaluate('MAX(5, 3, 8, 1)'))->toBe(8.0);
            });

            it('evaluates ROUND', function () {
                expect(Evaluator::evaluate('ROUND(3.7)'))->toBe(4.0);
                expect(Evaluator::evaluate('ROUND(3.14159, 2)'))->toBe(3.14);
            });

            it('evaluates FLOOR', function () {
                expect(Evaluator::evaluate('FLOOR(3.7)'))->toBe(3.0);
            });

            it('evaluates CEIL', function () {
                expect(Evaluator::evaluate('CEIL(3.2)'))->toBe(4.0);
            });

            it('evaluates ABS', function () {
                expect(Evaluator::evaluate('ABS(-5)'))->toBe(5.0);
            });

            it('evaluates MOD', function () {
                expect(Evaluator::evaluate('MOD(10, 3)'))->toBe(1.0);
            });

            it('evaluates POWER', function () {
                expect(Evaluator::evaluate('POWER(2, 3)'))->toBe(8.0);
            });

            it('evaluates SQRT', function () {
                expect(Evaluator::evaluate('SQRT(16)'))->toBe(4.0);
            });
        });

        describe('text functions', function () {
            it('evaluates CONCAT', function () {
                expect(Evaluator::evaluate('CONCAT("Hello", " ", "World")'))->toBe('Hello World');
            });

            it('evaluates UPPER', function () {
                expect(Evaluator::evaluate('UPPER("hello")'))->toBe('HELLO');
            });

            it('evaluates LOWER', function () {
                expect(Evaluator::evaluate('LOWER("HELLO")'))->toBe('hello');
            });

            it('evaluates TRIM', function () {
                expect(Evaluator::evaluate('TRIM("  hello  ")'))->toBe('hello');
            });

            it('evaluates LEFT', function () {
                expect(Evaluator::evaluate('LEFT("Hello", 2)'))->toBe('He');
            });

            it('evaluates RIGHT', function () {
                expect(Evaluator::evaluate('RIGHT("Hello", 2)'))->toBe('lo');
            });

            it('evaluates MID', function () {
                expect(Evaluator::evaluate('MID("Hello", 2, 3)'))->toBe('ell');
            });

            it('evaluates LEN', function () {
                expect(Evaluator::evaluate('LEN("Hello")'))->toBe(5);
            });
        });

        describe('logic functions', function () {
            it('evaluates IF', function () {
                expect(Evaluator::evaluate('IF(TRUE, "yes", "no")'))->toBe('yes');
                expect(Evaluator::evaluate('IF(FALSE, "yes", "no")'))->toBe('no');
            });

            it('evaluates AND', function () {
                expect(Evaluator::evaluate('AND(TRUE, TRUE)'))->toBe(true);
                expect(Evaluator::evaluate('AND(TRUE, FALSE)'))->toBe(false);
            });

            it('evaluates OR', function () {
                expect(Evaluator::evaluate('OR(TRUE, FALSE)'))->toBe(true);
                expect(Evaluator::evaluate('OR(FALSE, FALSE)'))->toBe(false);
            });

            it('evaluates ISBLANK', function () {
                expect(Evaluator::evaluate('ISBLANK("")'))->toBe(true);
                expect(Evaluator::evaluate('ISBLANK("hello")'))->toBe(false);
            });

            it('evaluates ISNUMBER', function () {
                expect(Evaluator::evaluate('ISNUMBER(42)'))->toBe(true);
                expect(Evaluator::evaluate('ISNUMBER("hello")'))->toBe(false);
            });

            it('evaluates IFBLANK', function () {
                expect(Evaluator::evaluate('IFBLANK("", "default")'))->toBe('default');
                expect(Evaluator::evaluate('IFBLANK("value", "default")'))->toBe('value');
            });
        });
    });

    describe('complex expressions', function () {
        it('evaluates nested function calls', function () {
            expect(Evaluator::evaluate('ROUND(AVERAGE(1, 2, 3, 4), 1)'))->toBe(2.5);
        });

        it('evaluates expressions with field references', function () {
            $context = ['price' => 100, 'quantity' => 5, 'tax_rate' => 0.1];
            expect(Evaluator::evaluate('{price} * {quantity} * (1 + {tax_rate})', $context))->toBe(550.0);
        });

        it('evaluates IF with comparison on fields', function () {
            expect(Evaluator::evaluate('IF({score} >= 90, "A", "B")', ['score' => 95]))->toBe('A');
            expect(Evaluator::evaluate('IF({score} >= 90, "A", "B")', ['score' => 85]))->toBe('B');
        });
    });

    describe('error handling', function () {
        it('returns null for invalid formulas', function () {
            expect(Evaluator::evaluate('INVALID_FORMULA('))->toBe(null);
        });

        it('returns null when field values cause errors', function () {
            expect(Evaluator::evaluate('10 / {value}', ['value' => 0]))->toBe(null);
        });
    });

    describe('depth limiting', function () {
        it('returns null when nesting exceeds max depth of 10', function () {
            // Create a deeply nested formula (11 levels deep should fail)
            $formula = 'ABS(ABS(ABS(ABS(ABS(ABS(ABS(ABS(ABS(ABS(ABS(1)))))))))))';
            expect(Evaluator::evaluate($formula))->toBe(null);
        });

        it('evaluates formulas within depth limit', function () {
            // 10 levels deep should work
            $formula = 'ABS(ABS(ABS(ABS(ABS(ABS(ABS(ABS(ABS(ABS(1))))))))))';
            expect(Evaluator::evaluate($formula))->toBe(1.0);
        });
    });
});
