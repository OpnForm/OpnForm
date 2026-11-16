<?php

function loadTrustedProxyConfiguration(?string $setting): array
{
    $originalEnv = $_ENV;
    $originalServer = $_SERVER;
    $originalProcessValue = getenv('TRUSTED_PROXIES');

    try {
        // Laravel's immutable environment repository cannot override external values.
        // Set its underlying sources so these tests also work with a configured shell.
        if ($setting === null) {
            unset($_ENV['TRUSTED_PROXIES'], $_SERVER['TRUSTED_PROXIES']);
            putenv('TRUSTED_PROXIES');
        } else {
            $_ENV['TRUSTED_PROXIES'] = $_SERVER['TRUSTED_PROXIES'] = $setting;
            putenv('TRUSTED_PROXIES='.$setting);
        }

        return require __DIR__.'/../../config/trustedproxy.php';
    } finally {
        $_ENV = $originalEnv;
        $_SERVER = $originalServer;
        putenv($originalProcessValue === false ? 'TRUSTED_PROXIES' : 'TRUSTED_PROXIES='.$originalProcessValue);
    }
}
