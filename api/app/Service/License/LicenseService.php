<?php

namespace App\Service\License;

use App\Models\License;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class LicenseService
{
    private const CACHE_KEY = 'self_hosted_license_check';
    private const CACHE_TTL_SECONDS = 24 * 60 * 60;
    private const GRACE_PERIOD_SECONDS = 24 * 60 * 60;
    private const API_TIMEOUT_SECONDS = 5;
    private const LICENSE_PROVIDER = 'self_hosted_enterprise';

    /**
     * Check license status with caching and grace period.
     */
    public function checkLicense(): LicenseCheckResult
    {
        $licenseKey = $this->getLicenseKey();
        if (!$licenseKey) {
            return LicenseCheckResult::invalid();
        }

        $cached = Cache::get(self::CACHE_KEY);
        if ($cached instanceof LicenseCheckResult) {
            return $cached;
        }

        return $this->validateAndCache($licenseKey);
    }

    /**
     * Validate a license key and store it if valid.
     */
    public function storeLicenseKey(string $licenseKey): LicenseCheckResult
    {
        $result = $this->validateAndCache($licenseKey);
        Cache::forget(self::CACHE_KEY);
        Cache::forget('feature_flags');

        if ($result->isActive()) {
            License::updateOrCreate(
                ['license_provider' => self::LICENSE_PROVIDER, 'user_id' => null],
                [
                    'license_key' => $licenseKey,
                    'status' => License::STATUS_ACTIVE,
                    'meta' => [],
                    'features' => $result->features,
                    'last_checked_at' => null,
                    'expires_at' => $result->expiresAt,
                ]
            );
        }

        return $result;
    }

    /**
     * Get the decrypted license key, or null if none stored.
     */
    public function getLicenseKey(): ?string
    {
        $license = $this->getLicenseRecord();
        if (!$license) {
            return null;
        }

        return $license->license_key;
    }

    /**
     * Quick status check without triggering external validation.
     */
    public function getStatus(): string
    {
        return $this->checkLicense()->status;
    }

    /**
     * Get current license features without triggering external validation.
     */
    public function getFeatures(): ?array
    {
        return $this->checkLicense()->features;
    }

    /**
     * Check if the active license grants a specific license-level feature key
     * (e.g. 'sso', 'multiOrg', 'whitelabel').
     */
    public function hasFeature(string $licenseFeatureKey): bool
    {
        $result = $this->checkLicense();
        if (!$result->isActive() || !$result->features) {
            return false;
        }

        return !empty($result->features[$licenseFeatureKey]);
    }

    /**
     * Check if the active license grants a specific application feature
     * using the self_hosted_features config (e.g. 'sso.oidc', 'custom_smtp').
     */
    public function hasAppFeature(string $appFeature): bool
    {
        $result = $this->checkLicense();
        if (!$result->isActive() || !$result->features) {
            return false;
        }

        $mapping = config('plans.self_hosted_features', []);
        foreach ($mapping as $licenseFeature => $appFeatures) {
            if (in_array($appFeature, (array) $appFeatures, true)) {
                if (!empty($result->features[$licenseFeature])) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Validate against external API and cache the result.
     * Falls back to grace period on failure.
     */
    private function validateAndCache(string $licenseKey): LicenseCheckResult
    {
        try {
            $apiEndpoint = config('services.license.endpoint');
            $response = Http::timeout(self::API_TIMEOUT_SECONDS)
                ->post("{$apiEndpoint}/licenses/validate", [
                    'licenseKey' => $licenseKey,
                    'usage' => $this->getUsageStats(),
                ]);

            if ($response->status() === 429) {
                return $this->handleApiFailure($licenseKey, 'Rate limit exceeded');
            }

            if (!$response->successful()) {
                return $this->handleApiFailure($licenseKey, "API returned status {$response->status()}");
            }

            $data = $response->json();
            $result = new LicenseCheckResult(
                status: ($data['valid'] ?? false) && ($data['status'] ?? '') === 'active' ? 'active' : 'expired',
                features: $data['features'] ?? null,
                lastChecked: now(),
                expiresAt: isset($data['expiresAt']) ? new \DateTimeImmutable($data['expiresAt']) : null,
            );

            $this->cacheResult($result);
            $this->updateLicenseRecord($result);

            return $result;
        } catch (\Exception $e) {
            return $this->handleApiFailure($licenseKey, $e->getMessage());
        }
    }

    /**
     * Handle API failure with grace period fallback.
     */
    private function handleApiFailure(string $licenseKey, string $reason): LicenseCheckResult
    {
        Log::warning('License validation API failed', ['reason' => $reason]);

        $license = $this->getLicenseRecord();
        if (!$license) {
            return LicenseCheckResult::invalid();
        }

        if (!$license->last_checked_at) {
            return LicenseCheckResult::invalid();
        }

        $elapsed = time() - $license->last_checked_at->getTimestamp();

        if ($elapsed < self::GRACE_PERIOD_SECONDS) {
            $result = new LicenseCheckResult(
                status: 'grace',
                features: $license->features,
                lastChecked: $license->last_checked_at,
                expiresAt: $license->expires_at,
            );

            $this->cacheResult($result);

            return $result;
        }

        $result = new LicenseCheckResult(
            status: 'expired',
            features: null,
            lastChecked: $license->last_checked_at,
            expiresAt: null,
        );

        $this->cacheResult($result);

        return $result;
    }

    private function cacheResult(LicenseCheckResult $result): void
    {
        Cache::put(self::CACHE_KEY, $result, self::CACHE_TTL_SECONDS);
    }

    private function updateLicenseRecord(LicenseCheckResult $result): void
    {
        $license = $this->getLicenseRecord();
        if (!$license) {
            return;
        }

        $license->update([
            'status' => $result->isActive() ? License::STATUS_ACTIVE : License::STATUS_INACTIVE,
            'features' => $result->features,
            'last_checked_at' => $result->lastChecked,
            'expires_at' => $result->expiresAt,
        ]);
    }

    private function getLicenseRecord(): ?License
    {
        return License::where('license_provider', self::LICENSE_PROVIDER)
            ->whereNull('user_id')
            ->first();
    }

    private function getUsageStats(): array
    {
        return [
            'userCount' => \App\Models\User::count(),
        ];
    }
}
