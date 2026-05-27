<?php

use App\Service\Formulas\Lexer;
use App\Service\Formulas\Token;
use App\Service\Formulas\FormulaException;

describe('Formula Lexer', function () {
    describe('number tokens', function () {
        it('tokenizes integers', function () {
            $lexer = new Lexer('42');
            $tokens = $lexer->tokenize();

            expect($tokens)->toHaveCount(2); // NUMBER + EOF
            expect($tokens[0]->type)->toBe(Token::NUMBER);
            expect($tokens[0]->value)->toBe(42.0);
        });

        it('tokenizes decimals', function () {
            $lexer = new Lexer('3.14159');
            $tokens = $lexer->tokenize();

            expect($tokens[0]->type)->toBe(Token::NUMBER);
            expect($tokens[0]->value)->toBe(3.14159);
        });

        it('tokenizes negative numbers with unary minus', function () {
            $lexer = new Lexer('-5');
            $tokens = $lexer->tokenize();

            expect($tokens)->toHaveCount(3); // OPERATOR(-) + NUMBER + EOF
            expect($tokens[0]->type)->toBe(Token::OPERATOR);
            expect($tokens[0]->value)->toBe('-');
            expect($tokens[1]->type)->toBe(Token::NUMBER);
        });
    });

    describe('string tokens', function () {
        it('tokenizes double-quoted strings', function () {
            $lexer = new Lexer('"hello world"');
            $tokens = $lexer->tokenize();

            expect($tokens[0]->type)->toBe(Token::STRING);
            expect($tokens[0]->value)->toBe('hello world');
        });

        it('tokenizes single-quoted strings', function () {
            $lexer = new Lexer("'hello'");
            $tokens = $lexer->tokenize();

            expect($tokens[0]->type)->toBe(Token::STRING);
            expect($tokens[0]->value)->toBe('hello');
        });

        it('handles escaped quotes', function () {
            $lexer = new Lexer('"say \\"hello\\""');
            $tokens = $lexer->tokenize();

            expect($tokens[0]->type)->toBe(Token::STRING);
            expect($tokens[0]->value)->toBe('say "hello"');
        });

        it('throws on unterminated strings', function () {
            $lexer = new Lexer('"unterminated');
            $lexer->tokenize();
        })->throws(FormulaException::class, 'Unterminated string');
    });

    describe('boolean tokens', function () {
        it('tokenizes TRUE', function () {
            $lexer = new Lexer('TRUE');
            $tokens = $lexer->tokenize();

            expect($tokens[0]->type)->toBe(Token::BOOLEAN);
            expect($tokens[0]->value)->toBe(true);
        });

        it('tokenizes FALSE', function () {
            $lexer = new Lexer('FALSE');
            $tokens = $lexer->tokenize();

            expect($tokens[0]->type)->toBe(Token::BOOLEAN);
            expect($tokens[0]->value)->toBe(false);
        });

        it('is case-insensitive for booleans', function () {
            $lexer1 = new Lexer('true');
            $lexer2 = new Lexer('True');

            expect($lexer1->tokenize()[0]->value)->toBe(true);
            expect($lexer2->tokenize()[0]->value)->toBe(true);
        });
    });

    describe('identifier tokens', function () {
        it('tokenizes function names', function () {
            $lexer = new Lexer('SUM');
            $tokens = $lexer->tokenize();

            expect($tokens[0]->type)->toBe(Token::IDENTIFIER);
            expect($tokens[0]->value)->toBe('SUM');
        });

        it('uppercases identifiers', function () {
            $lexer = new Lexer('sum');
            $tokens = $lexer->tokenize();

            expect($tokens[0]->value)->toBe('SUM');
        });
    });

    describe('field reference tokens', function () {
        it('tokenizes field references', function () {
            $lexer = new Lexer('{field_id}');
            $tokens = $lexer->tokenize();

            expect($tokens[0]->type)->toBe(Token::FIELD_REF);
            expect($tokens[0]->value)->toBe('field_id');
        });

        it('trims whitespace in field references', function () {
            $lexer = new Lexer('{ field_id }');
            $tokens = $lexer->tokenize();

            expect($tokens[0]->value)->toBe('field_id');
        });

        it('throws on unterminated field references', function () {
            $lexer = new Lexer('{field_id');
            $lexer->tokenize();
        })->throws(FormulaException::class, 'Unterminated field reference');
    });

    describe('operator tokens', function () {
        it('tokenizes arithmetic operators', function () {
            $lexer = new Lexer('+ - * /');
            $tokens = $lexer->tokenize();

            expect($tokens[0]->type)->toBe(Token::OPERATOR);
            expect($tokens[0]->value)->toBe('+');
            expect($tokens[1]->value)->toBe('-');
            expect($tokens[2]->value)->toBe('*');
            expect($tokens[3]->value)->toBe('/');
        });

        it('tokenizes comparison operators', function () {
            $lexer = new Lexer('= < > <= >= <>');
            $tokens = $lexer->tokenize();

            expect($tokens[0]->type)->toBe(Token::COMPARISON);
            expect($tokens[0]->value)->toBe('=');
            expect($tokens[1]->value)->toBe('<');
            expect($tokens[2]->value)->toBe('>');
            expect($tokens[3]->value)->toBe('<=');
            expect($tokens[4]->value)->toBe('>=');
            expect($tokens[5]->value)->toBe('<>');
        });
    });

    describe('complex expressions', function () {
        it('tokenizes a function call', function () {
            $lexer = new Lexer('SUM(1, 2, 3)');
            $tokens = $lexer->tokenize();

            expect($tokens[0]->type)->toBe(Token::IDENTIFIER);
            expect($tokens[0]->value)->toBe('SUM');
            expect($tokens[1]->type)->toBe(Token::LPAREN);
            expect($tokens[2]->type)->toBe(Token::NUMBER);
            expect($tokens[3]->type)->toBe(Token::COMMA);
        });

        it('tokenizes arithmetic expression with fields', function () {
            $lexer = new Lexer('{price} * {quantity}');
            $tokens = $lexer->tokenize();

            expect($tokens[0]->type)->toBe(Token::FIELD_REF);
            expect($tokens[0]->value)->toBe('price');
            expect($tokens[1]->type)->toBe(Token::OPERATOR);
            expect($tokens[2]->type)->toBe(Token::FIELD_REF);
        });
    });

    describe('whitespace handling', function () {
        it('skips whitespace', function () {
            $lexer = new Lexer('  1  +  2  ');
            $tokens = $lexer->tokenize();

            expect($tokens)->toHaveCount(4); // NUMBER + OPERATOR + NUMBER + EOF
        });
    });

    describe('error handling', function () {
        it('throws on unexpected characters', function () {
            $lexer = new Lexer('1 @ 2');
            $lexer->tokenize();
        })->throws(FormulaException::class, 'Unexpected character');
    });
});
