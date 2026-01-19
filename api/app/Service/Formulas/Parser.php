<?php

namespace App\Service\Formulas;

class Parser
{
    private array $tokens;
    private int $current = 0;

    public function __construct(array $tokens)
    {
        $this->tokens = $tokens;
    }

    public static function parse(string $formula): array
    {
        $lexer = new Lexer($formula);
        $tokens = $lexer->tokenize();
        $parser = new Parser($tokens);
        return $parser->parseExpression();
    }

    public function parseExpression(): array
    {
        $ast = $this->expression();

        if (!$this->isAtEnd()) {
            $token = $this->peek();
            throw new FormulaException(
                "Unexpected token '{$token->value}' at position {$token->position}",
                $token->position
            );
        }

        return $ast;
    }

    private function isAtEnd(): bool
    {
        return $this->peek()->type === Token::EOF;
    }

    private function peek(): Token
    {
        return $this->tokens[$this->current];
    }

    private function previous(): Token
    {
        return $this->tokens[$this->current - 1];
    }

    private function advance(): Token
    {
        if (!$this->isAtEnd()) {
            $this->current++;
        }
        return $this->previous();
    }

    private function check(string $type): bool
    {
        if ($this->isAtEnd()) {
            return false;
        }
        return $this->peek()->type === $type;
    }

    private function checkValue(string $type, mixed $value): bool
    {
        if ($this->isAtEnd()) {
            return false;
        }
        $token = $this->peek();
        return $token->type === $type && $token->value === $value;
    }

    private function consume(string $type, string $message): Token
    {
        if ($this->check($type)) {
            return $this->advance();
        }
        $token = $this->peek();
        throw new FormulaException(
            "{$message} at position {$token->position}",
            $token->position
        );
    }

    private function match(string $type, array $values = []): bool
    {
        if ($this->check($type)) {
            if (empty($values) || in_array($this->peek()->value, $values)) {
                $this->advance();
                return true;
            }
        }
        return false;
    }

    private function expression(): array
    {
        return $this->comparison();
    }

    private function comparison(): array
    {
        $left = $this->addition();

        if ($this->match(Token::COMPARISON)) {
            $operator = $this->previous()->value;
            $right = $this->addition();
            return [
                'type' => 'binary',
                'operator' => $operator,
                'left' => $left,
                'right' => $right,
            ];
        }

        return $left;
    }

    private function addition(): array
    {
        $left = $this->multiplication();

        while ($this->match(Token::OPERATOR, ['+', '-'])) {
            $operator = $this->previous()->value;
            $right = $this->multiplication();
            $left = [
                'type' => 'binary',
                'operator' => $operator,
                'left' => $left,
                'right' => $right,
            ];
        }

        return $left;
    }

    private function multiplication(): array
    {
        $left = $this->unary();

        while ($this->match(Token::OPERATOR, ['*', '/'])) {
            $operator = $this->previous()->value;
            $right = $this->unary();
            $left = [
                'type' => 'binary',
                'operator' => $operator,
                'left' => $left,
                'right' => $right,
            ];
        }

        return $left;
    }

    private function unary(): array
    {
        // Unary minus
        if ($this->match(Token::OPERATOR, ['-'])) {
            $operand = $this->unary();
            return [
                'type' => 'unary',
                'operator' => '-',
                'operand' => $operand,
            ];
        }

        // NOT operator
        if ($this->check(Token::IDENTIFIER) && $this->peek()->value === 'NOT') {
            $this->advance();
            $operand = $this->unary();
            return [
                'type' => 'unary',
                'operator' => 'NOT',
                'operand' => $operand,
            ];
        }

        return $this->primary();
    }

    private function primary(): array
    {
        // Number literal
        if ($this->match(Token::NUMBER)) {
            return [
                'type' => 'number',
                'value' => $this->previous()->value,
            ];
        }

        // String literal
        if ($this->match(Token::STRING)) {
            return [
                'type' => 'string',
                'value' => $this->previous()->value,
            ];
        }

        // Boolean literal
        if ($this->match(Token::BOOLEAN)) {
            return [
                'type' => 'boolean',
                'value' => $this->previous()->value,
            ];
        }

        // Field reference
        if ($this->match(Token::FIELD_REF)) {
            return [
                'type' => 'field',
                'id' => $this->previous()->value,
            ];
        }

        // Function call or identifier
        if ($this->match(Token::IDENTIFIER)) {
            $name = $this->previous()->value;

            // Check if it's a function call
            if ($this->check(Token::LPAREN)) {
                return $this->functionCall($name);
            }

            // Otherwise it's an unknown identifier
            throw new FormulaException(
                "Unknown identifier '{$name}' at position {$this->previous()->position}",
                $this->previous()->position
            );
        }

        // Parenthesized expression
        if ($this->match(Token::LPAREN)) {
            $expr = $this->expression();
            $this->consume(Token::RPAREN, "Expected ')' after expression");
            return $expr;
        }

        $token = $this->peek();
        throw new FormulaException(
            "Unexpected token at position {$token->position}",
            $token->position
        );
    }

    private function functionCall(string $name): array
    {
        $this->consume(Token::LPAREN, "Expected '(' after function name '{$name}'");

        $args = [];

        // Parse arguments
        if (!$this->check(Token::RPAREN)) {
            do {
                $args[] = $this->expression();
            } while ($this->match(Token::COMMA));
        }

        $this->consume(Token::RPAREN, "Expected ')' after function arguments");

        return [
            'type' => 'function',
            'name' => $name,
            'args' => $args,
        ];
    }
}
