<?php

namespace App\Service\AI\Mcp;

use App\Service\AI\Prompts\Form\FormStateSchemaFactory;
use App\Service\AI\Prompts\Form\PresentationRules;
use RuntimeException;

class GuideTokenService
{
    private const TOKEN_TTL_SECONDS = 900;

    public function issue(string $presentationStyle): string
    {
        $payload = [
            'presentation_style' => $presentationStyle,
            'schema_version' => FormStateSchemaFactory::SCHEMA_VERSION,
            'exp' => now()->timestamp + self::TOKEN_TTL_SECONDS,
        ];

        return $this->encode($payload);
    }

    public function assertValid(string $token): array
    {
        if ($token === '') {
            throw new RuntimeException('guide_token is required. Call get_form_generation_guide first.');
        }

        $payload = $this->decode($token);
        if (($payload['exp'] ?? 0) < now()->timestamp) {
            throw new RuntimeException('guide_token expired. Call get_form_generation_guide again.');
        }

        if (($payload['schema_version'] ?? null) !== FormStateSchemaFactory::SCHEMA_VERSION) {
            throw new RuntimeException('guide_token schema mismatch. Call get_form_generation_guide again.');
        }

        $style = $payload['presentation_style'] ?? null;
        if (! \in_array($style, [PresentationRules::MODE_CLASSIC, PresentationRules::MODE_FOCUSED], true)) {
            throw new RuntimeException('guide_token is invalid. Call get_form_generation_guide again.');
        }

        return $payload;
    }

    private function encode(array $payload): string
    {
        $jsonPayload = json_encode($payload, JSON_UNESCAPED_SLASHES);
        if ($jsonPayload === false) {
            throw new RuntimeException('Unable to encode guide token payload.');
        }

        $payloadEncoded = $this->base64UrlEncode($jsonPayload);
        $signature = hash_hmac('sha256', $payloadEncoded, $this->signingKey(), true);

        return $payloadEncoded . '.' . $this->base64UrlEncode($signature);
    }

    private function decode(string $token): array
    {
        $parts = explode('.', $token);
        if (count($parts) !== 2) {
            throw new RuntimeException('guide_token is malformed. Call get_form_generation_guide again.');
        }

        [$payloadEncoded, $signatureEncoded] = $parts;
        $expectedSignature = hash_hmac('sha256', $payloadEncoded, $this->signingKey(), true);
        $providedSignature = $this->base64UrlDecode($signatureEncoded);
        if (! is_string($providedSignature) || ! hash_equals($expectedSignature, $providedSignature)) {
            throw new RuntimeException('guide_token signature is invalid. Call get_form_generation_guide again.');
        }

        $payloadJson = $this->base64UrlDecode($payloadEncoded);
        if (! is_string($payloadJson)) {
            throw new RuntimeException('guide_token payload is invalid. Call get_form_generation_guide again.');
        }

        $payload = json_decode($payloadJson, true);
        if (! is_array($payload)) {
            throw new RuntimeException('guide_token payload is invalid. Call get_form_generation_guide again.');
        }

        return $payload;
    }

    private function signingKey(): string
    {
        $appKey = (string) config('app.key', '');
        if ($appKey === '') {
            throw new RuntimeException('App key is missing.');
        }

        if (str_starts_with($appKey, 'base64:')) {
            $decoded = base64_decode(substr($appKey, 7), true);
            if ($decoded !== false) {
                return $decoded;
            }
        }

        return $appKey;
    }

    private function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private function base64UrlDecode(string $value): string|false
    {
        $padding = strlen($value) % 4;
        if ($padding > 0) {
            $value .= str_repeat('=', 4 - $padding);
        }

        return base64_decode(strtr($value, '-_', '+/'), true);
    }
}
