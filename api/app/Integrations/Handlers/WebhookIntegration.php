<?php

namespace App\Integrations\Handlers;

use App\Models\Forms\Form;
use Illuminate\Support\Facades\Http;

class WebhookIntegration extends AbstractIntegrationHandler
{
    // Headers that should not be customizable for security reasons
    private const BLOCKED_HEADERS = [
        'Authorization',
        'X-Webhook-Signature',
        'Content-Type',
        'Content-Length',
        'Host',
        'Cookie',
        'X-CSRF-Token',
        'X-CSRF-TOKEN',
        'X-Forwarded-For',
        'X-Forwarded-Proto',
        'X-Real-IP',
        'X-Requested-With',
        'Accept-Encoding',
        'Transfer-Encoding',
    ];

    public static function getValidationRules(?Form $form): array
    {
        return [
            'webhook_url' => 'required|url',
            'webhook_secret' => 'nullable|string|min:12',
            'webhook_headers' => [
                'nullable',
                'array',
                function ($attribute, $value, $fail) {
                    if (!is_array($value)) {
                        return;
                    }

                    // Check max 10 headers
                    if (count($value) > 10) {
                        $fail('Maximum 10 custom headers allowed.');
                        return;
                    }

                    // Validate each header
                    foreach ($value as $headerName => $headerValue) {
                        // Validate header name
                        if (!is_string($headerName) || empty($headerName)) {
                            $fail('Header names must be non-empty strings.');
                            return;
                        }

                        // Check header name length
                        if (strlen($headerName) > 255) {
                            $fail("Header name '{$headerName}' exceeds 255 characters.");
                            return;
                        }

                        // Check for blocked headers (case-insensitive)
                        if (in_array(strtolower($headerName), array_map('strtolower', self::BLOCKED_HEADERS))) {
                            $fail("The '{$headerName}' header cannot be customized for security reasons.");
                            return;
                        }

                        // Validate header value
                        if (!is_string($headerValue)) {
                            $fail("Header values must be strings. '{$headerName}' has invalid value type.");
                            return;
                        }

                        if (strlen($headerValue) > 255) {
                            $fail("Header value for '{$headerName}' exceeds 255 characters.");
                            return;
                        }
                    }
                }
            ]
        ];
    }

    protected function getWebhookUrl(): ?string
    {
        return $this->integrationData->webhook_url;
    }

    protected function getWebhookSecret(): ?string
    {
        return $this->integrationData->webhook_secret ?? null;
    }

    protected function getWebhookHeaders(): array
    {
        $headers = isset($this->integrationData->webhook_headers) && is_object($this->integrationData->webhook_headers)
            ? (array) $this->integrationData->webhook_headers
            : [];

        // Ensure Content-Type is set
        if (!isset($headers['Content-Type'])) {
            $headers['Content-Type'] = 'application/json';
        }

        return $headers;
    }

    /**
     * Generate HMAC-SHA256 signature for webhook payload
     */
    protected function generateSignature(string $payload): string
    {
        $secret = $this->getWebhookSecret();
        if (!$secret) {
            return '';
        }

        return 'sha256=' . hash_hmac('sha256', $payload, $secret);
    }

    protected function shouldRun(): bool
    {
        return !is_null($this->getWebhookUrl()) && parent::shouldRun();
    }

    /**
     * Override handle to inject signature and custom headers
     */
    public function handle(): void
    {
        if (!$this->shouldRun()) {
            return;
        }

        $data = $this->getWebhookData();
        $payload = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $headers = $this->buildRequestHeaders($payload);

        Http::throw()
            ->timeout(5)
            ->withHeaders($headers)
            ->withBody($payload, 'application/json')
            ->post($this->getWebhookUrl());
    }

    /**
     * Build request headers with signature and custom headers
     */
    protected function buildRequestHeaders(string $payload): array
    {
        $headers = [];

        // Add signature header if secret is configured
        $signature = $this->generateSignature($payload);
        if ($signature) {
            $headers['X-Webhook-Signature'] = $signature;
        }

        // Add custom headers
        $customHeaders = $this->getWebhookHeaders();
        $headers = array_merge($customHeaders, $headers);

        return $headers;
    }

    public static function formatData(array $data): array
    {
        // Filter and format the integration data
        if (isset($data['data']) && is_array($data['data'])) {
            $filtered = [];

            // Keep only webhook-specific fields
            if (isset($data['data']['webhook_url'])) {
                $filtered['webhook_url'] = $data['data']['webhook_url'];
            }
            if (isset($data['data']['webhook_secret'])) {
                $filtered['webhook_secret'] = $data['data']['webhook_secret'];
            }

            // Convert webhook_headers array to object for consistent storage
            if (isset($data['data']['webhook_headers']) && is_array($data['data']['webhook_headers'])) {
                $filtered['webhook_headers'] = (object) $data['data']['webhook_headers'];
            }

            $data['data'] = $filtered;
        }

        return $data;
    }
}
