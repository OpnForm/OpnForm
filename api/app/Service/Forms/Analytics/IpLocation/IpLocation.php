<?php

namespace App\Service\Forms\Analytics\IpLocation;

/**
 * A provider's answer for one IP. The label is the English country name, which is what views have
 * always stored (IPinfo Lite's `country`), so analytics history keeps grouping under the same labels.
 */
final class IpLocation
{
    private function __construct(
        public readonly string $countryCode,
        public readonly string $country,
    ) {
    }

    /**
     * Null unless there's an ISO 3166-1 alpha-2 code and a name, so a 200 without a country counts as a failure
     */
    public static function fromCountry(mixed $code, mixed $name): ?self
    {
        $code = is_string($code) ? strtoupper(trim($code)) : '';
        $name = is_string($name) ? trim($name) : '';

        if (!preg_match('/^[A-Z]{2}$/D', $code) || $name === '') {
            return null;
        }

        return new self($code, $name);
    }

    public static function fromArray(array $data): ?self
    {
        return self::fromCountry($data['country_code'] ?? null, $data['country'] ?? null);
    }

    public function toArray(): array
    {
        return ['country_code' => $this->countryCode, 'country' => $this->country];
    }
}
