<?php

namespace App\Service\Forms\Analytics;

use App\Service\Forms\Analytics\IpLocation\IpLocationProvider;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class IpLocationService
{
    private const CACHE_PREFIX = 'ip_location:';

    /**
     * @param IpLocationProvider|null $provider null when external lookups are off
     */
    public function __construct(private readonly ?IpLocationProvider $provider = null)
    {
    }

    /**
     * Get location data for an IP address with caching
     */
    public function getLocationData(string $ip): array
    {
        // Don't process local/private IPs, or any IP when external lookups are off
        if (!$this->provider || !filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            return $this->getDefaultLocationData();
        }

        $cacheKey = $this->getCacheKey($ip);

        // Try to get from cache first
        $cachedData = Cache::get($cacheKey);
        if ($cachedData !== null) {
            return $cachedData;
        }

        try {
            $locationData = $this->formatLocationData($this->provider->lookup($ip));
            $expiresAt = now()->addHours((int) config('services.ip_location.cache_ttl_hours', 24));
        } catch (\Throwable $e) {
            Log::warning('IP location lookup failed', [
                'provider' => $this->provider->name(),
                'ip' => $ip,
                'error' => $e->getMessage()
            ]);

            // Cache failures briefly, so an outage doesn't leave visitors "Unknown" for long
            $locationData = $this->getDefaultLocationData();
            $expiresAt = now()->addMinutes((int) config('services.ip_location.failure_cache_ttl_minutes', 5));
        }

        Cache::put($cacheKey, $locationData, $expiresAt);

        return $locationData;
    }

    /**
     * Fill the fields a provider didn't answer
     */
    private function formatLocationData(array $data): array
    {
        return [
            'country' => $data['country'] ?? 'Unknown',
            'region' => $data['region'] ?? null,
            'city' => $data['city'] ?? null,
            'timezone' => $data['timezone'] ?? null,
            'org' => $data['org'] ?? null,
        ];
    }

    /**
     * Get default location data when API is unavailable
     */
    private function getDefaultLocationData(): array
    {
        return [
            'country' => 'Unknown',
            'region' => null,
            'city' => null,
            'timezone' => null,
            'org' => null,
        ];
    }

    /**
     * Generate cache key for IP address, scoped by provider
     */
    private function getCacheKey(string $ip): string
    {
        return self::CACHE_PREFIX . ($this->provider?->name() ?? 'none') . ':' . md5($ip);
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
