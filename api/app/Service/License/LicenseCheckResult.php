<?php

namespace App\Service\License;

class LicenseCheckResult
{
    public function __construct(
        public string $status,
        public ?array $features = null,
        public ?\DateTimeInterface $lastChecked = null,
        public ?\DateTimeInterface $expiresAt = null,
        public ?string $cloudLicenseId = null,
        public ?string $activationId = null,
    ) {
    }

    public function isActive(): bool
    {
        return in_array($this->status, ['active', 'grace']);
    }

    public function toArray(): array
    {
        return [
            'status' => $this->status,
            'features' => $this->features,
            'last_checked' => $this->lastChecked?->format('c'),
            'expires_at' => $this->expiresAt?->format('c'),
            'cloud_license_id' => $this->cloudLicenseId,
            'activation_id' => $this->activationId,
        ];
    }

    public static function invalid(): self
    {
        return new self(
            status: 'invalid',
            features: null,
            lastChecked: now(),
            expiresAt: null,
        );
    }
}
