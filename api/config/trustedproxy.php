<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Trusted Reverse Proxies
    |--------------------------------------------------------------------------
    |
    | Comma-separated IP addresses or CIDR ranges for reverse proxies in front
    | of OpnForm. Laravel only accepts X-Forwarded-* headers when the direct
    | caller is in this list, so clients cannot spoof their IP address.
    |
    | Leave this empty when users connect directly to the Docker ingress.
    |
    */
    'proxies' => env('TRUSTED_PROXIES'),
];
