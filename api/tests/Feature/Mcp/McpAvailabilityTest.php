<?php

use App\Support\Mcp\McpAvailability;
use Symfony\Component\Process\Process;

it('enables MCP on cloud instances regardless of the self-hosted opt-in flag', function (bool $configured) {
    config()->set('app.self_hosted', false);
    config()->set('opnform.mcp.enabled', $configured);

    expect(app(McpAvailability::class)->enabled())->toBeTrue();
})->with([false, true]);

it('requires self-hosted instances to opt in to MCP', function (bool $configured, bool $expected) {
    config()->set('app.self_hosted', true);
    config()->set('opnform.mcp.enabled', $configured);

    expect(app(McpAvailability::class)->enabled())->toBe($expected);
})->with([
    'disabled' => [false, false],
    'enabled' => [true, true],
]);

it('does not register MCP routes when a self-hosted instance has not opted in', function () {
    $process = new Process(
        [PHP_BINARY, 'artisan', 'route:list', '--json'],
        base_path(),
        ['SELF_HOSTED' => 'true', 'MCP_ENABLED' => 'false'],
    );
    $process->mustRun();

    $routes = collect(json_decode($process->getOutput(), true, flags: JSON_THROW_ON_ERROR));

    expect($routes->pluck('uri')->all())
        ->not->toContain('mcp')
        ->not->toContain('api/agent-drafts/preview/{draft}')
        ->not->toContain('api/mcp-oauth/session')
        ->not->toContain('oauth/token')
        ->not->toContain('oauth/authorize');
});

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
