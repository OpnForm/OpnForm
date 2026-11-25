<?php

namespace App\Service\Forms\Analytics\IpLocation;

use Illuminate\Support\Facades\Http;

class IpinfoProvider implements IpLocationProvider
{
    public function __construct(
        private readonly string $token,
        private readonly int $timeout,
    ) {
    }

    public function name(): string
    {
        return 'ipinfo';
    }

    public function lookup(string $ip): ?IpLocation
    {
        $response = Http::timeout($this->timeout)
            ->get("https://api.ipinfo.io/lite/{$ip}", ['token' => $this->token])
            ->throw();

        return IpLocation::fromCountry($response->json('country_code'), $response->json('country'));
    }
}
