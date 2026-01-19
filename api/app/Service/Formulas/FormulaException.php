<?php

namespace App\Service\Formulas;

use Exception;

class FormulaException extends Exception
{
    protected ?int $position;
    protected string $errorType;

    public function __construct(string $message, ?int $position = null, string $type = 'error')
    {
        parent::__construct($message);
        $this->position = $position;
        $this->errorType = $type;
    }

    public function getPosition(): ?int
    {
        return $this->position;
    }

    public function getErrorType(): string
    {
        return $this->errorType;
    }

    public function toArray(): array
    {
        return [
            'message' => $this->getMessage(),
            'position' => $this->position,
            'type' => $this->errorType,
        ];
    }
}
