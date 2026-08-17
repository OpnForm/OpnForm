<?php

use App\Support\Mcp\McpAvailability;

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
