<?php

namespace App\Service\Formulas;

class Lexer
{
    private string $input;
    private int $position = 0;
    private array $tokens = [];

    public function __construct(string $input)
    {
        $this->input = $input;
    }

    private function isAtEnd(): bool
    {
        return $this->position >= strlen($this->input);
    }

    private function peek(): ?string
    {
        return $this->isAtEnd() ? null : $this->input[$this->position];
    }

    private function peekNext(): ?string
    {
        return ($this->position + 1 >= strlen($this->input)) ? null : $this->input[$this->position + 1];
    }

    private function advance(): string
    {
        return $this->input[$this->position++];
    }

    private function isDigit(?string $char): bool
    {
        return $char !== null && $char >= '0' && $char <= '9';
    }

    private function isAlpha(?string $char): bool
    {
        return $char !== null && (
            ($char >= 'a' && $char <= 'z') ||
            ($char >= 'A' && $char <= 'Z') ||
            $char === '_'
        );
    }

    private function isAlphaNumeric(?string $char): bool
    {
        return $this->isAlpha($char) || $this->isDigit($char);
    }

    private function isWhitespace(?string $char): bool
    {
        return $char === ' ' || $char === "\t" || $char === "\n" || $char === "\r";
    }

    private function skipWhitespace(): void
    {
        while (!$this->isAtEnd() && $this->isWhitespace($this->peek())) {
            $this->advance();
        }
    }

    private function readNumber(): Token
    {
        $start = $this->position;

        while (!$this->isAtEnd() && $this->isDigit($this->peek())) {
            $this->advance();
        }

        // Check for decimal part
        if (!$this->isAtEnd() && $this->peek() === '.' && $this->isDigit($this->peekNext())) {
            $this->advance(); // consume '.'
            while (!$this->isAtEnd() && $this->isDigit($this->peek())) {
                $this->advance();
            }
        }

        $value = (float) substr($this->input, $start, $this->position - $start);
        return new Token(Token::NUMBER, $value, $start);
    }

    private function readString(string $quote): Token
    {
        $start = $this->position;
        $this->advance(); // consume opening quote

        $value = '';
        while (!$this->isAtEnd() && $this->peek() !== $quote) {
            if ($this->peek() === '\\' && $this->peekNext() === $quote) {
                $this->advance(); // skip escape character
            }
            $value .= $this->advance();
        }

        if ($this->isAtEnd()) {
            throw new FormulaException("Unterminated string starting at position {$start}", $start);
        }

        $this->advance(); // consume closing quote
        return new Token(Token::STRING, $value, $start);
    }

    private function readIdentifier(): Token
    {
        $start = $this->position;

        while (!$this->isAtEnd() && $this->isAlphaNumeric($this->peek())) {
            $this->advance();
        }

        $value = substr($this->input, $start, $this->position - $start);
        $upperValue = strtoupper($value);

        // Check for boolean literals
        if ($upperValue === 'TRUE') {
            return new Token(Token::BOOLEAN, true, $start);
        }
        if ($upperValue === 'FALSE') {
            return new Token(Token::BOOLEAN, false, $start);
        }

        return new Token(Token::IDENTIFIER, $upperValue, $start);
    }

    private function readFieldRef(): Token
    {
        $start = $this->position;
        $this->advance(); // consume '{'

        $fieldId = '';
        while (!$this->isAtEnd() && $this->peek() !== '}') {
            $fieldId .= $this->advance();
        }

        if ($this->isAtEnd()) {
            throw new FormulaException("Unterminated field reference starting at position {$start}", $start);
        }

        $this->advance(); // consume '}'
        return new Token(Token::FIELD_REF, trim($fieldId), $start);
    }

    public function tokenize(): array
    {
        $this->tokens = [];
        $this->position = 0;

        while (!$this->isAtEnd()) {
            $this->skipWhitespace();

            if ($this->isAtEnd()) {
                break;
            }

            $char = $this->peek();
            $start = $this->position;

            // Numbers
            if ($this->isDigit($char)) {
                $this->tokens[] = $this->readNumber();
                continue;
            }

            // Strings
            if ($char === '"' || $char === "'") {
                $this->tokens[] = $this->readString($char);
                continue;
            }

            // Identifiers and booleans
            if ($this->isAlpha($char)) {
                $this->tokens[] = $this->readIdentifier();
                continue;
            }

            // Field references
            if ($char === '{') {
                $this->tokens[] = $this->readFieldRef();
                continue;
            }

            // Two-character operators
            if ($char === '<' && $this->peekNext() === '>') {
                $this->advance();
                $this->advance();
                $this->tokens[] = new Token(Token::COMPARISON, '<>', $start);
                continue;
            }
            if ($char === '<' && $this->peekNext() === '=') {
                $this->advance();
                $this->advance();
                $this->tokens[] = new Token(Token::COMPARISON, '<=', $start);
                continue;
            }
            if ($char === '>' && $this->peekNext() === '=') {
                $this->advance();
                $this->advance();
                $this->tokens[] = new Token(Token::COMPARISON, '>=', $start);
                continue;
            }

            // Single-character tokens
            switch ($char) {
                case '(':
                    $this->tokens[] = new Token(Token::LPAREN, '(', $start);
                    $this->advance();
                    break;
                case ')':
                    $this->tokens[] = new Token(Token::RPAREN, ')', $start);
                    $this->advance();
                    break;
                case ',':
                    $this->tokens[] = new Token(Token::COMMA, ',', $start);
                    $this->advance();
                    break;
                case '+':
                case '-':
                case '*':
                case '/':
                    $this->tokens[] = new Token(Token::OPERATOR, $char, $start);
                    $this->advance();
                    break;
                case '=':
                case '<':
                case '>':
                    $this->tokens[] = new Token(Token::COMPARISON, $char, $start);
                    $this->advance();
                    break;
                default:
                    throw new FormulaException("Unexpected character '{$char}' at position {$start}", $start);
            }
        }

        $this->tokens[] = new Token(Token::EOF, null, $this->position);
        return $this->tokens;
    }
}
