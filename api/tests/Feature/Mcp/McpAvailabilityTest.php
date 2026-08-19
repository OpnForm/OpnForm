<?php

use App\Support\Mcp\McpAvailability;
use App\Enums\SettingsKey;
use App\Models\Setting;
use Symfony\Component\Process\Process;

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
    'guest preview' => ['GET', '/agent-drafts/preview/1'],
    'OAuth session' => ['POST', '/mcp-oauth/session'],
]);

it('can expose guest MCP without delegated OAuth routes', function () {
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
        ->not->toContain('api/mcp-oauth/session')
        ->not->toContain('oauth/token')
        ->not->toContain('oauth/authorize')
        ->not->toContain('.well-known/oauth-authorization-server');
});
