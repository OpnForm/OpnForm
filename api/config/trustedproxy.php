<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Trusted Reverse Proxies
    |--------------------------------------------------------------------------
    |
    | Comma-separated IP addresses or CIDR ranges for reverse proxies in front
    | of OpnForm. Explicit values enable the configured X-Forwarded-* headers.
    |
    | When omitted on self-hosted instances, only X-Forwarded-Proto is trusted
    | for HTTPS detection. Set an empty value to disable this fallback too.
    |
    */
    'proxies' => env('TRUSTED_PROXIES'),
];
