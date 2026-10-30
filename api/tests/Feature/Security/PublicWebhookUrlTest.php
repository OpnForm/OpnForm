<?php

use App\Service\Security\PublicWebhookUrl;

dataset('non-public webhook destinations', [
    'CGNAT start' => ['100.64.0.0'],
    'CGNAT host' => ['100.64.0.1'],
    'CGNAT metadata host' => ['100.100.100.200'],
    'CGNAT end' => ['100.127.255.255'],
    'protocol assignments start' => ['192.0.0.0'],
    'protocol assignments end' => ['192.0.0.255'],
    'benchmark start' => ['198.18.0.0'],
    'benchmark end' => ['198.19.255.255'],
    'NAT64 metadata' => ['64:ff9b::a9fe:a9fe'],
    'NAT64 public IPv4' => ['64:ff9b::808:808'],
    'NAT64 start' => ['64:ff9b::'],
    'NAT64 end' => ['64:ff9b::ffff:ffff'],
    'local NAT64 start' => ['64:ff9b:1::'],
    'local NAT64 end' => ['64:ff9b:1:ffff:ffff:ffff:ffff:ffff'],
    '6to4 metadata' => ['2002:a9fe:a9fe::1'],
    '6to4 start' => ['2002::'],
    '6to4 end' => ['2002:ffff:ffff:ffff:ffff:ffff:ffff:ffff'],
    'mapped metadata' => ['::ffff:169.254.169.254'],
    'mapped private hex' => ['::ffff:a00:1'],
    'mapped public IPv4' => ['::ffff:8.8.8.8'],
    'loopback' => ['127.0.0.1'],
    'private' => ['10.0.0.1'],
    'link local' => ['169.254.169.254'],
    'IPv6 loopback' => ['::1'],
    'IPv6 private' => ['fd00::1'],
]);

it('rejects special-use destinations for webhook validation and delivery', function (string $ip) {
    config(['opnform.webhooks.allow_private_urls' => false]);
    $url = 'https://' . (str_contains($ip, ':') ? '[' . $ip . ']' : $ip) . '/webhook';

    expect(PublicWebhookUrl::validate($url))
        ->toBe('The webhook URL must resolve only to public IP addresses.');
    expect(fn () => PublicWebhookUrl::requestOptions($url))
        ->toThrow(\InvalidArgumentException::class);
})->with('non-public webhook destinations');

it('rejects special-use destinations in public-only paths even with private webhooks enabled', function (string $ip) {
    config(['opnform.webhooks.allow_private_urls' => true]);
    $url = 'https://' . (str_contains($ip, ':') ? '[' . $ip . ']' : $ip) . '/resource';

    expect(fn () => PublicWebhookUrl::assertPublicOnly($url))
        ->toThrow(\InvalidArgumentException::class);
    expect(fn () => PublicWebhookUrl::requestOptionsPublicOnly($url))
        ->toThrow(\InvalidArgumentException::class);
})->with('non-public webhook destinations');

it('accepts public addresses outside the blocked ranges and pins them', function (string $ip) {
    config(['opnform.webhooks.allow_private_urls' => false]);
    $host = str_contains($ip, ':') ? '[' . $ip . ']' : $ip;
    $url = 'https://' . $host . '/webhook';

    expect(PublicWebhookUrl::validate($url))->toBeNull();
    PublicWebhookUrl::assertPublicOnly($url);
    expect(PublicWebhookUrl::requestOptionsPublicOnly($url)['curl'][CURLOPT_RESOLVE])
        ->toBe([$ip . ':443:' . $host]);
})->with([
    '100.63.255.255', '100.128.0.0',
    '191.255.255.255', '192.0.1.0',
    '198.17.255.255', '198.20.0.0',
    '93.184.216.34', '2606:4700:4700::1111', '2003::1',
]);

it('pins the validated destination ip for webhook requests', function () {
    config(['opnform.webhooks.allow_private_urls' => false]);

    $options = PublicWebhookUrl::requestOptions('https://93.184.216.34/webhook');

    expect($options['allow_redirects'])->toBeFalse()
        ->and($options['curl'][CURLOPT_RESOLVE])->toBe(['93.184.216.34:443:93.184.216.34']);
});

it('does not pin destinations when private webhook urls are explicitly allowed', function (string $url) {
    config(['opnform.webhooks.allow_private_urls' => true]);

    $options = PublicWebhookUrl::requestOptions($url);

    expect($options)->toBe(['allow_redirects' => false]);
})->with(['https://127.0.0.1/webhook', 'https://100.100.100.200/webhook', 'https://[64:ff9b::a9fe:a9fe]/webhook']);

it('rejects link local metadata addresses', function () {
    config(['opnform.webhooks.allow_private_urls' => false]);

    expect(PublicWebhookUrl::validate('https://169.254.169.254/latest/meta-data/'))
        ->toBe('The webhook URL must resolve only to public IP addresses.');
});

it('still rejects private urls when public-only validation is requested', function () {
    config(['opnform.webhooks.allow_private_urls' => true]);

    expect(fn () => PublicWebhookUrl::assertPublicOnly('https://127.0.0.1/webhook'))
        ->toThrow(\InvalidArgumentException::class, 'The webhook URL must resolve only to public IP addresses.');
});

it('does not allow private webhook destinations through public-only request options', function () {
    config(['opnform.webhooks.allow_private_urls' => true]);

    expect(fn () => PublicWebhookUrl::requestOptionsPublicOnly('https://127.0.0.1/webhook'))
        ->toThrow(\InvalidArgumentException::class);
});
