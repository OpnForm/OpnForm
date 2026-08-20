<?php

use App\Service\Telemetry\SendTelemetryJob;
use App\Service\Telemetry\TelemetryEvent;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;

it('records metadata-only MCP usage without request arguments', function () {
    Queue::fake();
    Log::spy();

    $secret = 'never-log-this-secret';
    $response = $this->postJson('/mcp', [
        'jsonrpc' => '2.0',
        'id' => 1,
        'method' => 'tools/call',
        'params' => [
            'name' => 'missing_tool',
            'arguments' => ['draft_token' => $secret],
        ],
    ], [
        'Accept' => 'application/json, text/event-stream',
    ]);

    $response->assertOk();

    Queue::assertPushed(SendTelemetryJob::class, function (SendTelemetryJob $job) use ($secret): bool {
        return $job->eventName === TelemetryEvent::MCP_REQUEST->value()
            && $job->properties['method'] === 'tools/call'
            && $job->properties['tool'] === 'missing_tool'
            && $job->properties['auth_mode'] === 'guest'
            && $job->properties['outcome'] === 'error'
            && ! str_contains(json_encode($job->properties), $secret);
    });

    Log::shouldHaveReceived('info')->once()->withArgs(function (string $message, array $context) use ($secret): bool {
        return $message === 'MCP request completed'
            && $context['method'] === 'tools/call'
            && $context['tool'] === 'missing_tool'
            && $context['outcome'] === 'error'
            && ! str_contains(json_encode($context), $secret);
    });
});

it('does not fail MCP requests when observability dispatch fails', function () {
    Log::shouldReceive('info')->once()->andThrow(new RuntimeException('logger unavailable'));

    $this->postJson('/mcp', [
        'jsonrpc' => '2.0',
        'id' => 1,
        'method' => 'initialize',
        'params' => [
            'protocolVersion' => '2025-06-18',
            'capabilities' => [],
            'clientInfo' => ['name' => 'test', 'version' => '1.0.0'],
        ],
    ], [
        'Accept' => 'application/json, text/event-stream',
    ])->assertOk()->assertJsonPath('result.serverInfo.name', 'OpnForm');
});

it('can disable MCP observability entirely', function () {
    config()->set('opnform.mcp.observability_enabled', false);
    Queue::fake();
    Log::spy();

    $this->postJson('/mcp', [
        'jsonrpc' => '2.0',
        'id' => 1,
        'method' => 'initialize',
        'params' => [
            'protocolVersion' => '2025-06-18',
            'capabilities' => [],
            'clientInfo' => ['name' => 'test', 'version' => '1.0.0'],
        ],
    ], [
        'Accept' => 'application/json, text/event-stream',
    ])->assertOk();

    Queue::assertNothingPushed();
    Log::shouldNotHaveReceived('info');
});
