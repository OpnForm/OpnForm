<?php

use App\Http\Middleware\TrustProxies;
use Illuminate\Config\Repository;
use Illuminate\Container\Container;
use Illuminate\Http\Request;

require_once __DIR__.'/../../TestHelpers/TrustedProxyTestHelpers.php';

it('recognizes forwarded HTTPS according to the proxy configuration', function ($setting, string $remoteAddress, ?string $protocol, bool $secure) {
    $originalContainer = Container::getInstance();
    $originalProxies = Request::getTrustedProxies();
    $originalHeaders = Request::getTrustedHeaderSet();

    try {
        $configuration = loadTrustedProxyConfiguration($setting);
        $container = new Container();
        $container->instance('config', new Repository(['trustedproxy' => $configuration]));
        Container::setInstance($container);

        $server = ['REMOTE_ADDR' => $remoteAddress];
        if ($protocol !== null) {
            $server['HTTP_X_FORWARDED_PROTO'] = $protocol;
        }

        $request = Request::create('http://forms.example.test/auth/company/redirect', 'POST', [], [], [], $server);
        (new TrustProxies())->handle($request, fn () => null);

        expect($request->secure())->toBe($secure);
    } finally {
        Request::setTrustedProxies($originalProxies, $originalHeaders);
        Container::setInstance($originalContainer);
    }
})->with([
    'legacy IPv4 fallback' => [null, '192.0.2.10', 'https', true],
    'legacy global IPv6 fallback' => [null, '2001:db8::10', 'https', true],
    'legacy fallback excludes private IPv6' => [null, 'fd00::10', 'https', false],
    'null environment value uses fallback' => ['null', '192.0.2.10', 'https', true],
    'explicit empty disables trust' => ['', '192.0.2.10', 'https', false],
    'explicit matching proxy' => ['192.0.2.10', '192.0.2.10', 'https', true],
    'explicit CIDR and comma separated proxies' => ['198.51.100.10, 192.0.2.0/24', '192.0.2.10', 'https', true],
    'explicit unmatched proxy rejects spoofed HTTPS' => ['198.51.100.10', '192.0.2.10', 'https', false],
    'missing header remains HTTP' => [null, '192.0.2.10', null, false],
    'forwarded HTTP remains HTTP' => [null, '192.0.2.10', 'http', false],
]);
