<?php

beforeEach(function () {
    config()->set('trustedproxy.proxies', ['127.0.0.1']);
    config()->set('custom-domains.enabled', true);
    config()->set('custom-domains.authorized_ips', ['192.0.2.10']);
    config()->set('custom-domains.caddy_secret', 'fictional-caddy-secret');
    $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.10']);
});

it('authorizes a registered customer domain from the pinned proxy', function () {
    $workspace = $this->createUserWorkspace($this->createUser());
    $workspace->forceFill(['custom_domains' => ['customer.example.test']])->save();

    $this->getJson('/caddy/ask-certificate/fictional-caddy-secret?domain=customer.example.test')
        ->assertSuccessful()
        ->assertJson(['success' => true]);
});

it('rejects an unknown domain even from the pinned proxy', function () {
    $this->getJson('/caddy/ask-certificate/fictional-caddy-secret?domain=other-product.example.test')
        ->assertStatus(400)
        ->assertJson(['message' => 'Unauthorized domain']);
});

it('rejects a wrong secret before authorizing a certificate', function () {
    $this->getJson('/caddy/ask-certificate/wrong-secret?domain=customer.example.test')
        ->assertUnauthorized();
});

it('does not accept a spoofed proxy header from another IP', function () {
    $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.20']);
    $response = $this->withHeader('X-Forwarded-For', '192.0.2.10')
        ->getJson('/caddy/ask-certificate/fictional-caddy-secret?domain=customer.example.test');

    expect(request()->server('REMOTE_ADDR'))->toBe('198.51.100.20');
    expect(request()->ip())->toBe('198.51.100.20');
    $response->assertUnauthorized();
});
