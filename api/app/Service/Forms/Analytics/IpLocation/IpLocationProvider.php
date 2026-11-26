<?php

namespace App\Service\Forms\Analytics\IpLocation;

interface IpLocationProvider
{
    /**
     * Short name that scopes this provider's cached results
     */
    public function name(): string;

    /**
     * Look up a public IP; null when the provider answered without a valid country
     *
     * @throws \Throwable when the request fails
     */
    public function lookup(string $ip): ?IpLocation;
}
