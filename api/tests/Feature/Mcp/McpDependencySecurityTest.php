<?php

use Illuminate\Support\Facades\Http;
use Laravel\Mcp\Client;
use Laravel\Mcp\Client\Exceptions\OAuthException;

it('rejects unadvertised OAuth discovery before disclosing client credentials (GHSA-m25c-38v7-94p8)', function () {
    Http::preventStrayRequests();
    Http::fake([
        'https://mcp.example.test/mcp' => Http::response('', 401, [
            'WWW-Authenticate' => 'Bearer resource_metadata="https://mcp.example.test/.well-known/oauth-protected-resource"',
        ]),
    ]);

    expect(fn () => Client::web('https://mcp.example.test/mcp')
        ->withOAuth(clientId: 'test-client', clientSecret: 'test-secret', redirectUri: 'https://opnform.test/callback')
        ->oAuthClient('https://attacker.example.test/resource')
        ->redirect())
        ->toThrow(OAuthException::class, 'was not advertised by the resource');

    Http::assertSentCount(1);
    Http::assertNotSent(fn ($request) => str_starts_with($request->url(), 'https://attacker.example.test'));
});

it('rejects deceptive loopback redirects during registration (GHSA-mx2h-h55v-pm44)', function (string $redirect) {
    $this->postJson('/oauth/register', [
        'client_name' => 'Deceptive loopback client',
        'redirect_uris' => [$redirect],
    ])->assertBadRequest()->assertJsonPath('error', 'invalid_redirect_uri');

    $this->assertDatabaseCount('oauth_clients', 0);
})->with([
    'userinfo' => 'http://localhost:123@attacker.example.test/callback',
    'host suffix' => 'http://localhost.attacker.example.test/callback',
    'loopback suffix' => 'http://127.0.0.1.attacker.example.test/callback',
    'untrusted HTTPS' => 'https://attacker.example.test/callback',
]);

it('registers exactly one protected dynamic client registration route', function () {
    $routes = collect(app('router')->getRoutes())->filter(
        fn ($route) => $route->uri() === 'oauth/register' && in_array('POST', $route->methods(), true),
    );

    expect($routes)->toHaveCount(1)
        ->and($routes->first()->getActionName())->toBe(App\Http\Controllers\McpOAuthRegisterController::class)
        ->and($routes->first()->gatherMiddleware())->toContain('mcp.enabled', 'throttle:mcp-oauth-registration');
});
