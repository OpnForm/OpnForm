<?php

namespace App\Service\Security;

use InvalidArgumentException;
use Symfony\Component\HttpFoundation\IpUtils;

class PublicWebhookUrl
{
    public static function assertSafe(string $url): void
    {
        self::validatedIp($url, self::allowPrivateUrlsFromConfig());
    }

    public static function assertPublicOnly(string $url): void
    {
        self::validatedIp($url, false);
    }

    /**
     * @return array<string, mixed>
     */
    public static function requestOptions(string $url): array
    {
        return self::buildRequestOptions($url, self::allowPrivateUrlsFromConfig());
    }

    /**
     * @return array<string, mixed>
     */
    public static function requestOptionsPublicOnly(string $url): array
    {
        return self::buildRequestOptions($url, false);
    }

    public static function validate(string $url): ?string
    {
        try {
            self::validatedIp($url, self::allowPrivateUrlsFromConfig());
        } catch (InvalidArgumentException $e) {
            return $e->getMessage();
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    private static function buildRequestOptions(string $url, bool $allowPrivateUrls): array
    {
        $options = [
            'allow_redirects' => false,
        ];

        $ip = self::validatedIp($url, $allowPrivateUrls);
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

    private static function allowPrivateUrlsFromConfig(): bool
    {
        return filter_var(config('opnform.webhooks.allow_private_urls', false), FILTER_VALIDATE_BOOLEAN);
    }

    private static function validatedIp(string $url, bool $allowPrivateUrls): ?string
    {
        if ($allowPrivateUrls) {
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

        return self::selectPinnedIp($ips);
    }

    /**
     * @param  array<int, string>  $ips
     */
    private static function selectPinnedIp(array $ips): string
    {
        foreach ($ips as $ip) {
            if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
                return $ip;
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
        ) !== false && !IpUtils::checkIp($ip, [
            // PHP's private/reserved flags do not cover all special-use ranges.
            '100.64.0.0/10',
            '192.0.0.0/24',
            '198.18.0.0/15',
            // Reject translation/tunnel ranges rather than trusting an embedded IPv4.
            '::ffff:0:0/96',
            '64:ff9b::/96',
            '64:ff9b:1::/48',
            '2002::/16',
        ]);
    }

    private static function formatCurlResolveIp(string $ip): string
    {
        return str_contains($ip, ':') ? '[' . $ip . ']' : $ip;
    }
}
