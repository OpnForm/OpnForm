<?php

use App\Service\Formulas\Functions\MathFunctions;
use App\Service\Formulas\Functions\TextFunctions;
use App\Service\Formulas\Functions\LogicFunctions;

describe('Formula Functions', function () {
    describe('Math Functions', function () {
        describe('SUM', function () {
            it('adds numbers', function () {
                expect(MathFunctions::SUM([1, 2, 3]))->toBe(6.0);
            });

            it('handles empty input', function () {
                expect(MathFunctions::SUM([]))->toBe(0.0);
            });

            it('ignores non-numeric values', function () {
                expect(MathFunctions::SUM([1, 'invalid', 2, null, 3]))->toBe(6.0);
            });

            it('flattens arrays', function () {
                expect(MathFunctions::SUM([[1, 2], [3, 4]]))->toBe(10.0);
            });
        });

        describe('AVERAGE', function () {
            it('calculates mean', function () {
                expect(MathFunctions::AVERAGE([1, 2, 3, 4, 5]))->toBe(3.0);
            });

            it('returns null for empty input', function () {
                expect(MathFunctions::AVERAGE([]))->toBe(null);
            });
        });

        describe('MIN/MAX', function () {
            it('finds minimum', function () {
                expect(MathFunctions::MIN([5, 3, 8, 1]))->toBe(1.0);
            });

            it('finds maximum', function () {
                expect(MathFunctions::MAX([5, 3, 8, 1]))->toBe(8.0);
            });
        });

        describe('ROUND', function () {
            it('rounds to integer by default', function () {
                expect(MathFunctions::ROUND(3.7))->toBe(4.0);
                expect(MathFunctions::ROUND(3.2))->toBe(3.0);
            });

            it('rounds to specified decimals', function () {
                expect(MathFunctions::ROUND(3.14159, 2))->toBe(3.14);
                expect(MathFunctions::ROUND(3.14159, 4))->toBe(3.1416);
            });
        });

        describe('FLOOR/CEIL', function () {
            it('floors down', function () {
                expect(MathFunctions::FLOOR(3.7))->toBe(3.0);
            });

            it('ceils up', function () {
                expect(MathFunctions::CEIL(3.2))->toBe(4.0);
            });
        });

        describe('ABS', function () {
            it('returns absolute value', function () {
                expect(MathFunctions::ABS(-5))->toBe(5.0);
                expect(MathFunctions::ABS(5))->toBe(5.0);
            });
        });

        describe('MOD', function () {
            it('returns remainder', function () {
                expect(MathFunctions::MOD(10, 3))->toBe(1.0);
            });

            it('returns null for division by zero', function () {
                expect(MathFunctions::MOD(10, 0))->toBe(null);
            });
        });

        describe('POWER', function () {
            it('raises to power', function () {
                expect(MathFunctions::POWER(2, 3))->toBe(8.0);
            });
        });

        describe('SQRT', function () {
            it('returns square root', function () {
                expect(MathFunctions::SQRT(16))->toBe(4.0);
            });

            it('returns null for negative numbers', function () {
                expect(MathFunctions::SQRT(-1))->toBe(null);
            });
        });
    });

    describe('Text Functions', function () {
        describe('CONCAT', function () {
            it('joins strings', function () {
                expect(TextFunctions::CONCAT('Hello', ' ', 'World'))->toBe('Hello World');
            });

            it('converts non-strings', function () {
                expect(TextFunctions::CONCAT('Value: ', 42))->toBe('Value: 42');
            });
        });

        describe('UPPER/LOWER', function () {
            it('converts to uppercase', function () {
                expect(TextFunctions::UPPER('hello'))->toBe('HELLO');
            });

            it('converts to lowercase', function () {
                expect(TextFunctions::LOWER('HELLO'))->toBe('hello');
            });
        });

        describe('TRIM', function () {
            it('removes leading/trailing whitespace', function () {
                expect(TextFunctions::TRIM('  hello  '))->toBe('hello');
            });
        });

        describe('LEFT/RIGHT/MID', function () {
            it('gets left characters', function () {
                expect(TextFunctions::LEFT('Hello', 2))->toBe('He');
            });

            it('gets right characters', function () {
                expect(TextFunctions::RIGHT('Hello', 2))->toBe('lo');
            });

            it('gets middle characters (1-indexed)', function () {
                expect(TextFunctions::MID('Hello', 2, 3))->toBe('ell');
            });
        });

        describe('LEN', function () {
            it('returns string length', function () {
                expect(TextFunctions::LEN('Hello'))->toBe(5);
                expect(TextFunctions::LEN(''))->toBe(0);
            });
        });

        describe('SUBSTITUTE', function () {
            it('replaces all occurrences', function () {
                expect(TextFunctions::SUBSTITUTE('hello hello', 'hello', 'hi'))->toBe('hi hi');
            });

            it('replaces specific instance', function () {
                expect(TextFunctions::SUBSTITUTE('hello hello hello', 'hello', 'hi', 2))->toBe('hello hi hello');
            });
        });

        describe('REPT', function () {
            it('repeats text N times', function () {
                expect(TextFunctions::REPT('ab', 3))->toBe('ababab');
            });

            it('returns empty string for negative count', function () {
                expect(TextFunctions::REPT('ab', -1))->toBe('');
            });

            it('limits repetitions to 100 to prevent memory abuse', function () {
                $result = TextFunctions::REPT('x', 200);
                expect(strlen($result))->toBe(100);
            });
        });
    });

    describe('Logic Functions', function () {
        describe('IF', function () {
            it('returns true value when condition is true', function () {
                expect(LogicFunctions::IF(true, 'yes', 'no'))->toBe('yes');
            });

            it('returns false value when condition is false', function () {
                expect(LogicFunctions::IF(false, 'yes', 'no'))->toBe('no');
            });

            it('treats non-empty strings as truthy', function () {
                expect(LogicFunctions::IF('hello', 'yes', 'no'))->toBe('yes');
            });

            it('treats empty string as falsy', function () {
                expect(LogicFunctions::IF('', 'yes', 'no'))->toBe('no');
            });
        });

        describe('AND', function () {
            it('returns true when all conditions are true', function () {
                expect(LogicFunctions::AND(true, true, true))->toBe(true);
            });

            it('returns false when any condition is false', function () {
                expect(LogicFunctions::AND(true, false, true))->toBe(false);
            });
        });

        describe('OR', function () {
            it('returns true when any condition is true', function () {
                expect(LogicFunctions::OR(false, true, false))->toBe(true);
            });

            it('returns false when all conditions are false', function () {
                expect(LogicFunctions::OR(false, false, false))->toBe(false);
            });
        });

        describe('NOT', function () {
            it('negates boolean', function () {
                expect(LogicFunctions::NOT(true))->toBe(false);
                expect(LogicFunctions::NOT(false))->toBe(true);
            });
        });

        describe('XOR', function () {
            it('returns true for odd number of true values', function () {
                expect(LogicFunctions::XOR(true, false))->toBe(true);
            });

            it('returns false for even number of true values', function () {
                expect(LogicFunctions::XOR(true, true))->toBe(false);
            });
        });

        describe('ISBLANK', function () {
            it('returns true for blank values', function () {
                expect(LogicFunctions::ISBLANK(''))->toBe(true);
                expect(LogicFunctions::ISBLANK(null))->toBe(true);
            });

            it('returns false for non-blank values', function () {
                expect(LogicFunctions::ISBLANK('hello'))->toBe(false);
                expect(LogicFunctions::ISBLANK(0))->toBe(false);
            });
        });

        describe('ISNUMBER', function () {
            it('returns true for numbers', function () {
                expect(LogicFunctions::ISNUMBER(42))->toBe(true);
                expect(LogicFunctions::ISNUMBER('42'))->toBe(true);
            });

            it('returns false for non-numbers', function () {
                expect(LogicFunctions::ISNUMBER('hello'))->toBe(false);
            });
        });

        describe('ISTEXT', function () {
            it('returns true for non-empty strings', function () {
                expect(LogicFunctions::ISTEXT('hello'))->toBe(true);
            });

            it('returns false for empty string or non-strings', function () {
                expect(LogicFunctions::ISTEXT(''))->toBe(false);
                expect(LogicFunctions::ISTEXT(42))->toBe(false);
            });
        });

        describe('IFBLANK', function () {
            it('returns fallback for blank values', function () {
                expect(LogicFunctions::IFBLANK('', 'default'))->toBe('default');
                expect(LogicFunctions::IFBLANK(null, 'default'))->toBe('default');
            });

            it('returns value for non-blank values', function () {
                expect(LogicFunctions::IFBLANK('hello', 'default'))->toBe('hello');
            });
        });

        describe('COALESCE', function () {
            it('returns first non-blank value', function () {
                expect(LogicFunctions::COALESCE('', null, 'third', 'fourth'))->toBe('third');
            });

            it('returns null if all blank', function () {
                expect(LogicFunctions::COALESCE('', null, ''))->toBe(null);
            });
        });

        describe('SWITCH', function () {
            it('returns matching case result', function () {
                expect(LogicFunctions::SWITCH('A', 'A', 4, 'B', 3, 'C', 2, 0))->toBe(4);
                expect(LogicFunctions::SWITCH('B', 'A', 4, 'B', 3, 'C', 2, 0))->toBe(3);
            });

            it('returns default when no match', function () {
                expect(LogicFunctions::SWITCH('D', 'A', 4, 'B', 3, 'C', 2, 0))->toBe(0);
            });
        });

        describe('CHOOSE', function () {
            it('returns value at index (1-indexed)', function () {
                expect(LogicFunctions::CHOOSE(2, 'a', 'b', 'c'))->toBe('b');
            });

            it('returns null for invalid index', function () {
                expect(LogicFunctions::CHOOSE(0, 'a', 'b', 'c'))->toBe(null);
                expect(LogicFunctions::CHOOSE(5, 'a', 'b', 'c'))->toBe(null);
            });
        });
    });
});
