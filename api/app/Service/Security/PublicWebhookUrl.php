<?php

namespace App\Service\Security;

use InvalidArgumentException;

class PublicWebhookUrl
{
    public static function assertSafe(string $url): void
    {
        self::validatedIp($url);
    }

    /**
     * @return array<string, mixed>
     */
    public static function requestOptions(string $url): array
    {
        $options = [
            'allow_redirects' => false,
        ];

        $ip = self::validatedIp($url);
        if ($ip === null) {
            return $options;
        }

        $host = trim((string) parse_url($url, PHP_URL_HOST), '[]');
        $port = parse_url($url, PHP_URL_PORT) ?: 443;

        $options['curl'] = [
            CURLOPT_RESOLVE => [
                sprintf('%s:%d:%s', $host, $port, self::formatCurlResolveIp($ip)),
            ],
        ];

        return $options;
    }

    public static function validate(string $url): ?string
    {
        try {
            self::validatedIp($url);
        } catch (InvalidArgumentException $e) {
            return $e->getMessage();
        }

        return null;
    }

    private static function validatedIp(string $url): ?string
    {
        if (config('opnform.webhooks.allow_private_urls', false)) {
            self::assertUrlShape($url);

            return null;
        }

        $shapeError = self::validateUrlShape($url);
        if ($shapeError !== null) {
            throw new InvalidArgumentException($shapeError);
        }

        $host = parse_url($url, PHP_URL_HOST);
        if (!is_string($host) || $host === '') {
            throw new InvalidArgumentException('The webhook URL host is invalid.');
        }

        $ips = self::resolveHost($host);
        if ($ips === []) {
            throw new InvalidArgumentException('The webhook URL host could not be resolved.');
        }

        foreach ($ips as $ip) {
            if (!self::isPublicIp($ip)) {
                throw new InvalidArgumentException('The webhook URL must resolve only to public IP addresses.');
            }
        }

        return $ips[0];
    }

    private static function validateUrlShape(string $url): ?string
    {
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            return 'The webhook URL must be a valid URL.';
        }

        if (strtolower((string) parse_url($url, PHP_URL_SCHEME)) !== 'https') {
            return 'The webhook URL must use HTTPS.';
        }

        if (parse_url($url, PHP_URL_USER) !== null || parse_url($url, PHP_URL_PASS) !== null) {
            return 'The webhook URL cannot contain embedded credentials.';
        }

        return null;
    }

    private static function assertUrlShape(string $url): void
    {
        $message = self::validateUrlShape($url);

        if ($message !== null) {
            throw new InvalidArgumentException($message);
        }
    }

    /**
     * @return array<int, string>
     */
    private static function resolveHost(string $host): array
    {
        $host = trim($host, '[]');

        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return [$host];
        }

        if (strtolower(rtrim($host, '.')) === 'localhost') {
            return ['127.0.0.1'];
        }

        $records = @dns_get_record($host, DNS_A + DNS_AAAA);
        if (!is_array($records)) {
            return [];
        }

        $ips = [];
        foreach ($records as $record) {
            foreach (['ip', 'ipv6'] as $key) {
                if (isset($record[$key]) && filter_var($record[$key], FILTER_VALIDATE_IP)) {
                    $ips[] = $record[$key];
                }
            }
        }

        return array_values(array_unique($ips));
    }

    private static function isPublicIp(string $ip): bool
    {
        return filter_var(
            $ip,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
        ) !== false;
    }

    private static function formatCurlResolveIp(string $ip): string
    {
        return str_contains($ip, ':') ? '['.$ip.']' : $ip;
    }
}
