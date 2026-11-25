<?php

namespace App\Service\Forms\Analytics;

use App\Service\Forms\Analytics\IpLocation\IpLocation;
use App\Service\Forms\Analytics\IpLocation\IpLocationProvider;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class IpLocationService
{
    private const CACHE_PREFIX = 'ip_location:';

    private const IPV4_MAPPED_PREFIX = "\0\0\0\0\0\0\0\0\0\0\xff\xff";

    /**
     * @param IpLocationProvider|null $provider null when external lookups are off
     */
    public function __construct(private readonly ?IpLocationProvider $provider = null)
    {
    }

    public function enabled(): bool
    {
        return $this->provider !== null;
    }

    /**
     * The address a provider may be sent, or null unless it's public unicast. An IPv4-mapped IPv6
     * address is checked, and returned, as the IPv4 address it carries.
     */
    public static function lookupAddress(?string $ip): ?string
    {
        if ($ip === null || filter_var($ip, FILTER_VALIDATE_IP) === false) {
            return null;
        }

        $packed = inet_pton($ip);
        if (strlen($packed) === 16 && str_starts_with($packed, self::IPV4_MAPPED_PREFIX)) {
            $packed = substr($packed, 12);
        }
        $ip = inet_ntop($packed);

        // FILTER_FLAG_GLOBAL_RANGE lets multicast through: 224.0.0.0/4 and ff00::/8
        $multicast = strlen($packed) === 4 ? (ord($packed[0]) & 0xF0) === 0xE0 : $packed[0] === "\xff";
        if ($multicast || filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_GLOBAL_RANGE) === false) {
            return null;
        }

        return $ip;
    }

    /**
     * Country for a public IP, cached per provider. Null when lookups are off, the IP isn't public, or the
     * provider failed or had no valid country, which is cached briefly so an outage doesn't last long.
     */
    public function lookup(string $ip): ?IpLocation
    {
        $ip = self::lookupAddress($ip);
        if (!$this->provider || $ip === null) {
            return null;
        }

        $cacheKey = $this->getCacheKey($ip);
        $cached = Cache::get($cacheKey);
        if (is_array($cached)) {
            return IpLocation::fromArray($cached);
        }

        $location = $this->fetch($ip);
        $expiresAt = $location
            ? now()->addHours((int) config('services.ip_location.cache_ttl_hours', 24))
            : now()->addMinutes((int) config('services.ip_location.failure_cache_ttl_minutes', 5));
        Cache::put($cacheKey, $location?->toArray() ?? [], $expiresAt);

        return $location;
    }

    /**
     * Logs carry the provider and a status or error category, never the IP or the exception message
     */
    private function fetch(string $ip): ?IpLocation
    {
        $context = ['provider' => $this->provider->name()];

        try {
            $location = $this->provider->lookup($ip);
            if (!$location) {
                Log::warning('IP location lookup returned no country', $context);
            }

            return $location;
        } catch (RequestException $e) {
            $context['status'] = $e->response->status();
        } catch (ConnectionException) {
            $context['error'] = 'connection';
        } catch (\Throwable $e) {
            $context['error'] = class_basename($e);
        }

        Log::warning('IP location lookup failed', $context);

        return null;
    }

    /**
     * Generate cache key for IP address, scoped by provider
     */
    private function getCacheKey(string $ip): string
    {
        return self::CACHE_PREFIX . ($this->provider?->name() ?? 'none') . ':' . md5(self::lookupAddress($ip) ?? $ip);
    }

    /**
     * Clear cache for a specific IP (useful for testing)
     */
    public function clearCache(string $ip): bool
    {
        return Cache::forget($this->getCacheKey($ip));
    }

    /**
     * Get cache statistics (for monitoring)
     */
    public function getCacheStats(array $ips): array
    {
        $stats = [
            'cached' => 0,
            'missing' => 0,
            'total' => count($ips)
        ];

        foreach ($ips as $ip) {
            if (Cache::has($this->getCacheKey($ip))) {
                $stats['cached']++;
            } else {
                $stats['missing']++;
            }
        }

        return $stats;
    }
}
