<?php

namespace App\Service\Formulas;

class Token
{
    public const NUMBER = 'NUMBER';
    public const STRING = 'STRING';
    public const BOOLEAN = 'BOOLEAN';
    public const IDENTIFIER = 'IDENTIFIER';
    public const FIELD_REF = 'FIELD_REF';
    public const OPERATOR = 'OPERATOR';
    public const COMPARISON = 'COMPARISON';
    public const LPAREN = 'LPAREN';
    public const RPAREN = 'RPAREN';
    public const COMMA = 'COMMA';
    public const EOF = 'EOF';

    public string $type;
    public mixed $value;
    public int $position;

    public function __construct(string $type, mixed $value, int $position)
    {
        $this->type = $type;
        $this->value = $value;
        $this->position = $position;
    }
}
