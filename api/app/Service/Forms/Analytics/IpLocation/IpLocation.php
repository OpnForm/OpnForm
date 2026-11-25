<?php

namespace App\Service\Forms\Analytics\IpLocation;

/**
 * A provider's answer for one IP. The label is the English country name, which is what views have
 * always stored (IPinfo Lite's `country`), so analytics history keeps grouping under the same labels.
 */
final class IpLocation
{
    // Keep the IPinfo display names already used in analytics where the shared list differs.
    // https://ipinfo.io/countries
    private const ANALYTICS_COUNTRY_NAMES = [
        'AX' => 'Aland Islands',
        'BN' => 'Brunei',
        'BO' => 'Bolivia',
        'BQ' => 'Bonaire, Saint Eustatius and Saba',
        'CC' => 'Cocos Islands',
        'CD' => 'Democratic Republic of the Congo',
        'CG' => 'Republic of the Congo',
        'CI' => 'Ivory Coast',
        'CV' => 'Cabo Verde',
        'CW' => 'Curacao',
        'CZ' => 'Czechia',
        'EH' => 'Western Sahara',
        'FK' => 'Falkland Islands',
        'FM' => 'Micronesia',
        'HM' => 'Heard Island and McDonald Islands',
        'IR' => 'Iran',
        'KP' => 'North Korea',
        'KR' => 'South Korea',
        'LY' => 'Libya',
        'MK' => 'North Macedonia',
        'NL' => 'The Netherlands',
        'PS' => 'Palestinian Territory',
        'SH' => 'Saint Helena',
        'SX' => 'Sint Maarten',
        'SY' => 'Syria',
        'TL' => 'Timor Leste',
        'TZ' => 'Tanzania',
        'UM' => 'United States Minor Outlying Islands',
        'VA' => 'Vatican',
        'VE' => 'Venezuela',
        'VG' => 'British Virgin Islands',
        'VI' => 'U.S. Virgin Islands',
    ];

    private function __construct(
        public readonly string $countryCode,
        public readonly string $country,
    ) {
    }

    /**
     * Null unless there's a supported country code and a name, so a 200 without a country counts as a failure
     */
    public static function fromCountry(mixed $code, mixed $name): ?self
    {
        $code = is_string($code) ? strtoupper(trim($code)) : '';
        $name = is_string($name) ? trim($name) : '';

        // The shared list still includes the former Netherlands Antilles code.
        if ($code === 'AN' || $name === '' || strcasecmp($name, 'Unknown') === 0) {
            return null;
        }

        static $countryNames;
        $countryNames ??= self::ANALYTICS_COUNTRY_NAMES + array_column(
            json_decode(file_get_contents(resource_path('data/country_codes.json')), true, flags: JSON_THROW_ON_ERROR),
            'name',
            'code',
        );

        return isset($countryNames[$code]) ? new self($code, $countryNames[$code]) : null;
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
