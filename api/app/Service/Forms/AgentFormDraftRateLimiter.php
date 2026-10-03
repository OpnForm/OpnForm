<?php

namespace App\Service\Forms;

use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

class AgentFormDraftRateLimiter
{
    public function hit(string $identifier): void
    {
        $hash = hash('sha256', $identifier);
        $minuteKey = "mcp:draft-create:minute:{$hash}";
        $hourKey = "mcp:draft-create:hour:{$hash}";
        $minuteLimit = max(1, config('opnform.mcp.rate_limit.draft_creates_per_minute', 20));
        $hourLimit = max(1, config('opnform.mcp.rate_limit.draft_creates_per_hour', 200));

        if (RateLimiter::tooManyAttempts($minuteKey, $minuteLimit)
            || RateLimiter::tooManyAttempts($hourKey, $hourLimit)) {
            throw ValidationException::withMessages([
                'definition' => ['Too many drafts were created. Reuse or update an existing draft before trying again.'],
            ]);
        }

        RateLimiter::hit($minuteKey, 60);
        RateLimiter::hit($hourKey, 3600);
    }
}
