<?php

namespace App\Service\Forms;

use Illuminate\Support\Facades\RateLimiter;

class FormSummaryRateLimiter
{
    public function attempt(int $userId): bool
    {
        $key = $this->key($userId);
        if (RateLimiter::tooManyAttempts($key, $this->limit())) {
            return false;
        }

        RateLimiter::hit($key, 60);

        return true;
    }

    public function availableIn(int $userId): int
    {
        return RateLimiter::availableIn($this->key($userId));
    }

    public function remaining(int $userId): int
    {
        return RateLimiter::remaining($this->key($userId), $this->limit());
    }

    public function limit(): int
    {
        return max(1, config('opnform.form_summary_rate_limit_per_minute', 30));
    }

    private function key(int $userId): string
    {
        return 'form-summary:user:'.hash('sha256', (string) $userId);
    }
}
