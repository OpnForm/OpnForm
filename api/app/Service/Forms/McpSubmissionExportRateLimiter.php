<?php

namespace App\Service\Forms;

use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

class McpSubmissionExportRateLimiter
{
    public function hit(int $userId, int $formId): void
    {
        $identifier = hash('sha256', $userId.':'.$formId);
        $minuteKey = "mcp:submission-export:minute:{$identifier}";
        $hourKey = "mcp:submission-export:hour:{$identifier}";
        $minuteLimit = max(1, config('opnform.mcp.rate_limit.submission_exports_per_minute', 5));
        $hourLimit = max(1, config('opnform.mcp.rate_limit.submission_exports_per_hour', 30));

        if (RateLimiter::tooManyAttempts($minuteKey, $minuteLimit)
            || RateLimiter::tooManyAttempts($hourKey, $hourLimit)) {
            throw ValidationException::withMessages([
                'form_id' => ['Too many submission exports were queued. Reuse the current export or try again later.'],
            ]);
        }

        RateLimiter::hit($minuteKey, 60);
        RateLimiter::hit($hourKey, 3600);
    }
}
