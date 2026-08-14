<?php

namespace App\Http\Middleware;

use App\Service\Telemetry\TelemetryEvent;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

class RecordMcpUsage
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! config('opnform.mcp.observability_enabled', true)) {
            return $next($request);
        }

        $startedAt = hrtime(true);
        try {
            [$method, $tool] = $this->operation($request);
        } catch (Throwable) {
            [$method, $tool] = ['unknown', null];
        }

        try {
            $response = $next($request);
        } catch (Throwable $exception) {
            $status = $exception instanceof HttpExceptionInterface ? $exception->getStatusCode() : 500;
            $this->recordSafely($request, $method, $tool, $status, 'error', $startedAt);

            throw $exception;
        }

        $this->recordSafely(
            $request,
            $method,
            $tool,
            $response->getStatusCode(),
            $this->outcome($response),
            $startedAt,
        );

        return $response;
    }

    /**
     * @return array{0: string, 1: string|null}
     */
    private function operation(Request $request): array
    {
        $payload = $request->json()->all();
        $method = is_string($payload['method'] ?? null) ? $payload['method'] : 'unknown';
        $tool = $method === 'tools/call' && is_string($payload['params']['name'] ?? null)
            ? $payload['params']['name']
            : null;

        return [$method, $tool];
    }

    private function outcome(Response $response): string
    {
        if ($response->getStatusCode() >= 400) {
            return 'error';
        }

        $content = $response->getContent();
        if (! is_string($content)) {
            return 'success';
        }

        $payload = json_decode($content, true);
        if (! is_array($payload)) {
            return 'success';
        }

        return isset($payload['error']) || ($payload['result']['isError'] ?? false) === true
            ? 'error'
            : 'success';
    }

    private function recordSafely(
        Request $request,
        string $method,
        ?string $tool,
        int $status,
        string $outcome,
        int $startedAt,
    ): void {
        try {
            $properties = array_filter([
                'method' => $method,
                'tool' => $tool,
                'auth_mode' => $request->user() ? 'oauth' : 'guest',
                'outcome' => $outcome,
                'status' => $status,
                'duration_ms' => (int) round((hrtime(true) - $startedAt) / 1_000_000),
            ], fn (mixed $value): bool => $value !== null);

            Log::info('MCP request completed', $properties);
            telemetry(TelemetryEvent::MCP_REQUEST, $properties);
        } catch (Throwable) {
            // Observability must never interrupt an MCP request.
        }
    }
}
