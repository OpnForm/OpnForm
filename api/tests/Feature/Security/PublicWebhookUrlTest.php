<?php

use App\Service\Security\PublicWebhookUrl;

it('pins the validated destination ip for webhook requests', function () {
    config(['opnform.webhooks.allow_private_urls' => false]);

    $options = PublicWebhookUrl::requestOptions('https://93.184.216.34/webhook');

    expect($options['allow_redirects'])->toBeFalse()
        ->and($options['curl'][CURLOPT_RESOLVE])->toBe(['93.184.216.34:443:93.184.216.34']);
});

it('does not pin destinations when private webhook urls are explicitly allowed', function () {
    config(['opnform.webhooks.allow_private_urls' => true]);

    $options = PublicWebhookUrl::requestOptions('https://127.0.0.1/webhook');

    expect($options)->toBe(['allow_redirects' => false]);
});

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
