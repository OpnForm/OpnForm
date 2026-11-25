<?php

namespace App\Service\Forms\Analytics\IpLocation;

interface IpLocationProvider
{
    /**
     * Short name that scopes this provider's cached results
     */
    public function name(): string;

    /**
     * Look up a public IP and map the answer to country, region, city, timezone and org
     *
     * @throws \Throwable when the lookup fails
     */
    public function lookup(string $ip): array;
}
