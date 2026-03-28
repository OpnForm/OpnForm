<?php

namespace App\Exceptions;

use RuntimeException;

class FeatureAccessDeniedException extends RuntimeException
{
    public function __construct(
        public readonly string $feature,
        public readonly string $requiredTier,
        public readonly string $currentTier,
        string $message,
    ) {
        parent::__construct($message);
    }
}
