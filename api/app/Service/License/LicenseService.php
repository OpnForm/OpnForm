<?php

namespace App\Service\License;

use App\Enums\SettingsKey;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class LicenseService
{
    private const CACHE_KEY = 'self_hosted_license_check';
    private const CACHE_TTL_SECONDS = 24 * 60 * 60;
    private const GRACE_PERIOD_SECONDS = 24 * 60 * 60;
    private const API_TIMEOUT_SECONDS = 5;

    /**
     * Check the installed license with caching and outage grace.
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

        return $this->refreshInstalledLicense($licenseKey);
    }

    /**
     * Validate a candidate key and store it only after a fresh active response.
     */
    public function storeLicenseKey(string $licenseKey): LicenseCheckResult
    {
        Cache::forget(self::CACHE_KEY);
        Cache::forget('feature_flags');

        $result = $this->validateCandidateLicense($licenseKey);
        if ($result->isActive()) {
            $this->storeLicenseState($licenseKey, $result);
            $this->cacheResult($result);
        }

        return $result;
    }

    public function removeLicenseKey(): void
    {
        Setting::forget(SettingsKey::SELF_HOSTED_LICENSE);
        Cache::forget(self::CACHE_KEY);
        Cache::forget('feature_flags');
    }

    /**
     * Get the decrypted installed license key, or null when none is stored.
     */
    public function getLicenseKey(): ?string
    {
        $stored = $this->getStoredLicense();
        $encryptedKey = $stored['license_key'] ?? null;

        if (!$encryptedKey) {
            return null;
        }

        try {
            return Crypt::decryptString($encryptedKey);
        } catch (\Throwable $e) {
            Log::warning('Stored self-hosted license key could not be decrypted', [
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    public function getStatus(): string
    {
        return $this->checkLicense()->status;
    }

    public function getFeatures(): ?array
    {
        return $this->checkLicense()->features;
    }

    public function getOrCreateInstanceId(): string
    {
        $instanceId = Setting::get(SettingsKey::INSTANCE_ID);
        if (is_string($instanceId) && $instanceId !== '') {
            return $instanceId;
        }

        $instanceId = (string) Str::uuid();
        Setting::set(SettingsKey::INSTANCE_ID, $instanceId);

        if (!Setting::get(SettingsKey::INSTANCE_CREATED_AT)) {
            Setting::set(SettingsKey::INSTANCE_CREATED_AT, now()->toIso8601String());
        }

        Cache::forget('telemetry.instance_id');

        return $instanceId;
    }

    public function createBillingPortalUrl(): string
    {
        $licenseKey = $this->getLicenseKey();
        if (!$licenseKey) {
            throw new \RuntimeException('No active license key is installed.');
        }

        $apiEndpoint = rtrim((string) config('services.license.endpoint'), '/');
        $response = Http::timeout(self::API_TIMEOUT_SECONDS)
            ->post("{$apiEndpoint}/licenses/portal", [
                'licenseKey' => $licenseKey,
            ]);

        if (!$response->successful()) {
            throw new \RuntimeException('Unable to create billing portal session.');
        }

        $portalUrl = $response->json('portalUrl');
        if (!is_string($portalUrl) || $portalUrl === '') {
            throw new \RuntimeException('License API returned an invalid billing portal session.');
        }

        return $portalUrl;
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

    public function hasPaidLicense(): bool
    {
        $cached = Cache::get(self::CACHE_KEY);
        if ($cached instanceof LicenseCheckResult) {
            return $cached->isActive();
        }

        $stored = $this->getStoredLicense();
        if (!$stored || !$this->getLicenseKey()) {
            return false;
        }

        return in_array($stored['status'] ?? null, ['active', 'grace'], true);
    }

    private function validateCandidateLicense(string $licenseKey): LicenseCheckResult
    {
        try {
            return $this->callValidationApi($licenseKey);
        } catch (\Throwable $e) {
            Log::warning('Candidate license validation failed', [
                'reason' => $e->getMessage(),
            ]);

            return LicenseCheckResult::invalid();
        }
    }

    private function refreshInstalledLicense(string $licenseKey): LicenseCheckResult
    {
        try {
            $result = $this->callValidationApi($licenseKey);
            $this->cacheResult($result);
            $this->updateStoredLicenseState($result);

            return $result;
        } catch (\Throwable $e) {
            return $this->handleApiFailure($e->getMessage());
        }
    }

    private function callValidationApi(string $licenseKey): LicenseCheckResult
    {
        $apiEndpoint = rtrim((string) config('services.license.endpoint'), '/');
        $response = Http::timeout(self::API_TIMEOUT_SECONDS)
            ->post("{$apiEndpoint}/licenses/validate", [
                'licenseKey' => $licenseKey,
                'instanceId' => $this->getOrCreateInstanceId(),
                'usage' => $this->getUsageStats(),
            ]);

        if ($response->status() === 429) {
            throw new \RuntimeException('Rate limit exceeded');
        }

        if (!$response->successful()) {
            throw new \RuntimeException("API returned status {$response->status()}");
        }

        $data = $response->json();
        $valid = (bool) ($data['valid'] ?? false);
        $status = (string) ($data['status'] ?? ($valid ? 'active' : 'invalid'));

        if (!$valid && $status === 'active') {
            $status = 'invalid';
        }

        return new LicenseCheckResult(
            status: $valid && $status === 'active' ? 'active' : $status,
            features: $valid ? ($data['features'] ?? null) : null,
            lastChecked: now(),
            expiresAt: $this->parseDate($data['expiresAt'] ?? null),
            cloudLicenseId: isset($data['licenseId']) ? (string) $data['licenseId'] : null,
            activationId: isset($data['activationId']) ? (string) $data['activationId'] : null,
        );
    }

    private function handleApiFailure(string $reason): LicenseCheckResult
    {
        Log::warning('License validation API failed', ['reason' => $reason]);

        $stored = $this->getStoredLicense();
        if (!$stored) {
            return LicenseCheckResult::invalid();
        }

        $lastChecked = $this->parseDate($stored['last_checked_at'] ?? null);
        if (!$lastChecked) {
            return LicenseCheckResult::invalid();
        }

        $elapsed = time() - $lastChecked->getTimestamp();
        if ($elapsed < self::GRACE_PERIOD_SECONDS) {
            $result = new LicenseCheckResult(
                status: 'grace',
                features: $stored['features'] ?? null,
                lastChecked: $lastChecked,
                expiresAt: $this->parseDate($stored['expires_at'] ?? null),
                cloudLicenseId: $stored['cloud_license_id'] ?? null,
                activationId: $stored['activation_id'] ?? null,
            );

            $this->cacheResult($result);

            return $result;
        }

        $result = new LicenseCheckResult(
            status: 'expired',
            features: null,
            lastChecked: $lastChecked,
            expiresAt: null,
            cloudLicenseId: $stored['cloud_license_id'] ?? null,
            activationId: $stored['activation_id'] ?? null,
        );

        $this->cacheResult($result);

        return $result;
    }

    private function cacheResult(LicenseCheckResult $result): void
    {
        Cache::put(self::CACHE_KEY, $result, self::CACHE_TTL_SECONDS);
    }

    private function updateStoredLicenseState(LicenseCheckResult $result): void
    {
        $stored = $this->getStoredLicense();
        if (!$stored) {
            return;
        }

        $stored['status'] = $result->status;
        $stored['features'] = $result->isActive() ? $result->features : null;
        $stored['last_checked_at'] = $result->lastChecked?->format('c');
        $stored['expires_at'] = $result->expiresAt?->format('c');
        $stored['cloud_license_id'] = $result->cloudLicenseId ?? ($stored['cloud_license_id'] ?? null);
        $stored['activation_id'] = $result->activationId ?? ($stored['activation_id'] ?? null);

        Setting::set(SettingsKey::SELF_HOSTED_LICENSE, $stored);
        Cache::forget('feature_flags');
    }

    private function storeLicenseState(string $plainLicenseKey, LicenseCheckResult $result): void
    {
        Setting::set(SettingsKey::SELF_HOSTED_LICENSE, [
            'license_key' => Crypt::encryptString($plainLicenseKey),
            'status' => $result->status,
            'features' => $result->features,
            'last_checked_at' => $result->lastChecked?->format('c'),
            'expires_at' => $result->expiresAt?->format('c'),
            'cloud_license_id' => $result->cloudLicenseId,
            'activation_id' => $result->activationId,
        ]);

        Cache::forget('feature_flags');
    }

    private function getStoredLicense(): ?array
    {
        $stored = Setting::get(SettingsKey::SELF_HOSTED_LICENSE);

        return is_array($stored) ? $stored : null;
    }

    private function parseDate(mixed $value): ?\DateTimeImmutable
    {
        if (!$value) {
            return null;
        }

        try {
            return new \DateTimeImmutable((string) $value);
        } catch (\Throwable) {
            return null;
        }
    }

    private function getUsageStats(): array
    {
        return [
            'userCount' => User::count(),
        ];
    }
}
