<?php

namespace App\Service\Forms\Analytics;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class IpLocationService
{
    private const CACHE_PREFIX = 'ip_location:';

    /**
     * Get location data for an IP address with caching
     */
    public function getLocationData(string $ip): array
    {
        // Don't process local/private IPs
        if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            return $this->getDefaultLocationData();
        }

        $cacheKey = $this->getCacheKey($ip);

        // Try to get from cache first
        $cachedData = Cache::get($cacheKey);
        if ($cachedData !== null) {
            return $cachedData;
        }

        // Fetch from API
        $locationData = $this->fetchFromApi($ip);

        // Cache the result (even if empty to avoid repeated API calls)
        $cacheTtl = config('services.ipinfo.cache_ttl_hours', 24);
        Cache::put($cacheKey, $locationData, now()->addHours($cacheTtl));

        return $locationData;
    }

    /**
     * Fetch location data from IPINFO API
     */
    private function fetchFromApi(string $ip): array
    {
        $token = config('services.ipinfo.token');

        if (!$token) {
            return $this->getDefaultLocationData();
        }

        try {
            $url = "https://api.ipinfo.io/lite/{$ip}?token={$token}";
            $timeout = config('services.ipinfo.request_timeout', 5);
            $response = Http::timeout($timeout)->get($url);

            if ($response->successful()) {
                $data = $response->json();
                return $this->formatLocationData($data);
            }

            Log::warning('IPINFO API request failed', [
                'ip' => $ip,
                'status' => $response->status(),
                'response' => $response->body()
            ]);
        } catch (\Exception $e) {
            Log::error('IPINFO API request exception', [
                'ip' => $ip,
                'error' => $e->getMessage()
            ]);
        }

        return $this->getDefaultLocationData();
    }

    /**
     * Format and validate location data from API response
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
     * Generate cache key for IP address
     */
    private function getCacheKey(string $ip): string
    {
        return self::CACHE_PREFIX . md5($ip);
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
