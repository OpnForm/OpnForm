<?php

namespace App\Http\Middleware;

use App\Service\Forms\FormSummaryRateLimiter;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ThrottleFormSummary
{
    public function __construct(private readonly FormSummaryRateLimiter $rateLimiter)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $userId = (int) $request->user()->getAuthIdentifier();
        if (! $this->rateLimiter->attempt($userId)) {
            $retryAfter = max(1, $this->rateLimiter->availableIn($userId));

            return new JsonResponse([
                'message' => 'Too many submission statistics requests. Try again later.',
                'retry_after' => $retryAfter,
            ], 429, $this->headers($userId, $retryAfter));
        }

        $response = $next($request);
        foreach ($this->headers($userId) as $name => $value) {
            $response->headers->set($name, (string) $value);
        }

        return $response;
    }

    /** @return array<string, int> */
    private function headers(int $userId, ?int $retryAfter = null): array
    {
        return array_filter([
            'X-RateLimit-Limit' => $this->rateLimiter->limit(),
            'X-RateLimit-Remaining' => $this->rateLimiter->remaining($userId),
            'Retry-After' => $retryAfter,
        ], fn (?int $value) => $value !== null);
    }
}
