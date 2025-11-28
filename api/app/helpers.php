<?php

if (!function_exists('front_url')) {
    function front_url($path = '')
    {
        $baseUrl = config('app.front_url') ?? config('app.url');
        if (! $baseUrl) {
            return $path;
        }

        // Ensure baseUrl has a protocol (defaults to https for security)
        if (! preg_match('~^https?://~i', $baseUrl)) {
            $baseUrl = 'https://' . $baseUrl;
        }

        // Validate URL format
        if (filter_var($baseUrl, FILTER_VALIDATE_URL) === false) {
            return $path;
        }

        // Remove trailing slash from base URL
        $cleanBaseUrl = rtrim($baseUrl, '/');

        // Return base URL if no path provided
        if (! $path) {
            return $cleanBaseUrl;
        }

        // Combine base URL with path, ensuring single forward slash
        return $cleanBaseUrl . '/' . ltrim($path, '/');
    }
}

if (!function_exists('pricing_enabled')) {
    function pricing_enabled(): bool
    {
        return !is_null(config('cashier.key')) && !config('app.self_hosted');
    }
}

if (!function_exists('telemetry')) {
    /**
     * Send a telemetry event asynchronously.
     *
     * @param \App\Service\Telemetry\TelemetryEvent $event
     * @param array $properties
     * @return void
     */
    function telemetry(\App\Service\Telemetry\TelemetryEvent $event, array $properties = []): void
    {
        \App\Service\Telemetry\SendTelemetryJob::dispatch(
            $event->value(),
            $properties
        );
    }
}
