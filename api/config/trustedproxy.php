<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Trusted Reverse Proxies
    |--------------------------------------------------------------------------
    |
    | Comma-separated IP addresses or CIDR ranges for reverse proxies in front
    | of OpnForm. Explicit values take precedence over the compatibility default
    | previously supplied by Vapor, which trusts all IPv4 and global IPv6 proxies.
    |
    | Set an empty value to disable proxy trust for a direct deployment. When
    | using the compatibility default, restrict ingress access to your proxy.
    |
    */
    'proxies' => env('TRUSTED_PROXIES') ?? ['0.0.0.0/0', '2000:0:0:0:0:0:0:0/3'],
];
