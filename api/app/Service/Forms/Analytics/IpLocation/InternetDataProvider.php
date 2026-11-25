<?php

namespace App\Service\Forms\Analytics\IpLocation;

use Illuminate\Support\Facades\Http;

/**
 * Needs no token: https://internetdata.io/terms and https://internetdata.io/privacy
 */
class InternetDataProvider implements IpLocationProvider
{
    public function __construct(private readonly int $timeout)
    {
    }

    public function name(): string
    {
        return 'internetdata';
    }

    public function lookup(string $ip): array
    {
        $location = Http::timeout($this->timeout)
            ->get("https://api.internetdata.io/{$ip}")
            ->throw()
            ->json('location') ?? [];

        return [
            'country' => $location['country_name'] ?? null,
            'timezone' => $location['timezone'] ?? null,
        ];
    }
}
