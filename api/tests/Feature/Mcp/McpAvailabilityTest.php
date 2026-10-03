<?php

use App\Support\Mcp\McpAvailability;
use App\Enums\SettingsKey;
use App\Models\Setting;
use Symfony\Component\Process\Process;

function configureOperationalSelfHostedMcp(): void
{
    $key = openssl_pkey_new([
        'private_key_bits' => 2048,
        'private_key_type' => OPENSSL_KEYTYPE_RSA,
    ]);
    openssl_pkey_export($key, $privateKey);
    $details = openssl_pkey_get_details($key);

    config()->set('oauth.enabled', true);
    config()->set('app.url', 'https://forms.example.com');
    config()->set('app.front_url', 'https://forms.example.com');
    config()->set('passport.private_key', $privateKey);
    config()->set('passport.public_key', $details['key']);
}

it('enables MCP on cloud instances regardless of the self-hosted opt-in flag', function (bool $configured) {
    config()->set('app.self_hosted', false);
    config()->set('opnform.mcp.enabled', $configured);

    expect(app(McpAvailability::class)->enabled())->toBeTrue();
})->with([false, true]);

it('requires self-hosted instances to opt in to MCP', function (bool $configured, bool $expected) {
    config()->set('app.self_hosted', true);
    config()->set('opnform.mcp.enabled', $configured);
    Setting::forget(SettingsKey::MCP_ENABLED);

    expect(app(McpAvailability::class)->enabled())->toBe($expected);
})->with([
    'disabled' => [false, false],
    'enabled' => [true, true],
]);

it('uses the stored self-hosted setting before the environment default', function (bool $environment, bool $stored, bool $expected) {
    config()->set('app.self_hosted', true);
    config()->set('opnform.mcp.enabled', $environment);
    Setting::set(SettingsKey::MCP_ENABLED, $stored);

    expect(app(McpAvailability::class)->enabled())->toBe($expected);
})->with([
    'stored enable overrides disabled environment' => [false, true, true],
    'stored disable overrides enabled environment' => [true, false, false],
]);

it('allows guest drafts only on cloud instances', function (bool $selfHosted, bool $mcpEnabled, bool $expected) {
    config()->set('app.self_hosted', $selfHosted);
    config()->set('opnform.mcp.enabled', $mcpEnabled);
    Setting::forget(SettingsKey::MCP_ENABLED);

    expect(app(McpAvailability::class)->guestDraftsEnabled())->toBe($expected);
})->with([
    'cloud' => [false, false, true],
    'self-hosted disabled' => [true, false, false],
    'self-hosted enabled' => [true, true, false],
]);

it('keeps an enabled self-hosted MCP unavailable until OAuth is ready', function () {
    config()->set('app.self_hosted', true);
    config()->set('opnform.mcp.enabled', true);
    config()->set('oauth.enabled', false);
    Setting::forget(SettingsKey::MCP_ENABLED);

    expect(app(McpAvailability::class)->enabled())->toBeTrue()
        ->and(app(McpAvailability::class)->available())->toBeFalse();

    $this->postJson('/mcp', [
        'jsonrpc' => '2.0',
        'id' => 1,
        'method' => 'tools/list',
        'params' => [],
    ], ['Accept' => 'application/json, text/event-stream'])->assertNotFound();
});

it('registers MCP routes even when a self-hosted instance is disabled so runtime settings work with route caching', function () {
    $process = new Process(
        [PHP_BINARY, 'artisan', 'route:list', '--json'],
        base_path(),
        ['SELF_HOSTED' => 'true', 'MCP_ENABLED' => 'false'],
    );
    $process->mustRun();

    $routes = collect(json_decode($process->getOutput(), true, flags: JSON_THROW_ON_ERROR));

    expect($routes->pluck('uri')->all())
        ->toContain('mcp')
        ->toContain('agent-drafts/preview/{draft}')
        ->toContain('mcp-oauth/session')
        ->toContain('oauth/token')
        ->toContain('oauth/authorize');
});

it('returns not found from MCP endpoints when a self-hosted admin disables MCP', function (string $method, string $uri) {
    config()->set('app.self_hosted', true);
    Setting::set(SettingsKey::MCP_ENABLED, false);

    $this->json($method, $uri)->assertNotFound();
})->with([
    'MCP server request' => ['POST', '/mcp'],
    'MCP browser request' => ['GET', '/mcp'],
    'MCP session close' => ['DELETE', '/mcp'],
    'OAuth discovery' => ['GET', '/.well-known/oauth-authorization-server'],
    'OAuth token exchange' => ['POST', '/oauth/token'],
    'OAuth authorization' => ['GET', '/oauth/authorize'],
    'OAuth dynamic client registration' => ['POST', '/oauth/register'],
    'guest preview' => ['GET', '/agent-drafts/preview/1'],
    'OAuth session' => ['POST', '/mcp-oauth/session'],
]);

it('returns not found from every guest draft endpoint on an enabled self-hosted instance', function (string $method, string $uri) {
    config()->set('app.self_hosted', true);
    configureOperationalSelfHostedMcp();
    Setting::set(SettingsKey::MCP_ENABLED, true);

    $this->json($method, $uri)->assertNotFound();
})->with([
    'guest preview' => ['GET', '/agent-drafts/preview/1'],
    'guest handoff' => ['POST', '/agent-drafts/handoff/consume'],
    'guest editor read' => ['GET', '/agent-drafts/editor/current'],
    'guest editor replace' => ['PUT', '/agent-drafts/editor/current'],
    'guest editor claim' => ['POST', '/agent-drafts/editor/claim'],
]);

it('keeps MCP routes registered when delegated OAuth is disabled', function () {
    $process = new Process(
        [PHP_BINARY, 'artisan', 'route:list', '--json'],
        base_path(),
        [
            'SELF_HOSTED' => 'true',
            'MCP_ENABLED' => 'true',
            'OAUTH_ENABLED' => 'false',
        ],
    );
    $process->mustRun();

    $routes = collect(json_decode($process->getOutput(), true, flags: JSON_THROW_ON_ERROR));
    $uris = $routes->pluck('uri')->all();

    expect($uris)
        ->toContain('mcp')
        ->toContain('agent-drafts/preview/{draft}')
        ->not->toContain('api/mcp-oauth/session')
        ->not->toContain('oauth/token')
        ->not->toContain('oauth/authorize')
        ->not->toContain('.well-known/oauth-authorization-server');
});
