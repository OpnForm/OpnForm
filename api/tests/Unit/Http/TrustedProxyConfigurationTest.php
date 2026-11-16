<?php

use App\Http\Middleware\TrustProxies;
use Illuminate\Config\Repository;
use Illuminate\Container\Container;
use Illuminate\Http\Request;

require_once __DIR__.'/../../TestHelpers/TrustedProxyTestHelpers.php';

it('recognizes forwarded HTTPS according to the proxy configuration', function ($setting, bool $selfHosted, string $remoteAddress, ?string $protocol, bool $secure, bool $forwardedMetadata) {
    $originalContainer = Container::getInstance();
    $originalProxies = Request::getTrustedProxies();
    $originalHeaders = Request::getTrustedHeaderSet();

    try {
        $configuration = loadTrustedProxyConfiguration($setting);
        $container = new Container();
        $container->instance('config', new Repository(['trustedproxy' => $configuration, 'app' => ['self_hosted' => $selfHosted]]));
        Container::setInstance($container);

        $server = [
            'REMOTE_ADDR' => $remoteAddress,
            'HTTP_X_FORWARDED_FOR' => '203.0.113.99',
            'HTTP_X_FORWARDED_HOST' => 'forwarded.example.test',
            'HTTP_X_FORWARDED_PORT' => '9443',
            'HTTP_FORWARDED' => 'for=203.0.113.100;host=untrusted.example.test;proto=https',
        ];
        if ($protocol !== null) {
            $server['HTTP_X_FORWARDED_PROTO'] = $protocol;
        }

        $request = Request::create('http://forms.example.test:8080/auth/company/redirect', 'POST', [], [], [], $server);
        (new TrustProxies())->handle($request, fn () => null);

        expect($request->secure())->toBe($secure)
            ->and($request->ip())->toBe($forwardedMetadata ? '203.0.113.99' : $remoteAddress)
            ->and($request->getHost())->toBe($forwardedMetadata ? 'forwarded.example.test' : 'forms.example.test')
            ->and($request->getPort())->toBe($forwardedMetadata ? 9443 : 8080);
    } finally {
        Request::setTrustedProxies($originalProxies, $originalHeaders);
        Container::setInstance($originalContainer);
    }
})->with([
    'self-hosted IPv4 protocol fallback' => [null, true, '192.0.2.10', 'https', true, false],
    'self-hosted global IPv6 protocol fallback' => [null, true, '2001:db8::10', 'https', true, false],
    'self-hosted private IPv6 protocol fallback' => [null, true, 'fd00::10', 'https', true, false],
    'null environment value uses protocol fallback' => ['null', true, '192.0.2.10', 'https', true, false],
    'explicit empty disables fallback' => ['', true, '192.0.2.10', 'https', false, false],
    'explicit matching proxy trusts configured headers' => ['192.0.2.10', true, '192.0.2.10', 'https', true, true],
    'explicit CIDR and comma separated proxies' => ['198.51.100.10, 192.0.2.0/24', true, '192.0.2.10', 'https', true, true],
    'explicit unmatched proxy rejects spoofed headers' => ['198.51.100.10', true, '192.0.2.10', 'https', false, false],
    'missing protocol header remains HTTP' => [null, true, '192.0.2.10', null, false, false],
    'forwarded HTTP remains HTTP' => [null, true, '192.0.2.10', 'http', false, false],
    'cloud has no protocol fallback' => [null, false, '192.0.2.10', 'https', false, false],
    'cloud explicit empty disables trust' => ['', false, '192.0.2.10', 'https', false, false],
    'cloud explicit proxy trusts configured headers' => ['192.0.2.10', false, '192.0.2.10', 'https', true, true],
]);
