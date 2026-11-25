<?php

namespace App\Service\Forms\Analytics\IpLocation;

use Illuminate\Support\Arr;
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

    public function lookup(string $ip): array
    {
        $data = Http::timeout($this->timeout)
            ->get("https://api.ipinfo.io/lite/{$ip}", ['token' => $this->token])
            ->throw()
            ->json();

        return Arr::only($data ?? [], ['country', 'region', 'city', 'timezone', 'org']);
    }
}
