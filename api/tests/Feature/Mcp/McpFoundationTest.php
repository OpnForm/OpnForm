<?php

use App\Mcp\Resources\FormDefinitionSchemaResource;
use App\Mcp\Resources\FormFieldCatalogResource;
use App\Mcp\Servers\OpnFormServer;
use App\Mcp\Tools\ValidateFormDefinitionTool;
use App\Service\Forms\AgentFormDefinition;
use Illuminate\Testing\Fluent\AssertableJson;
use Illuminate\Support\Facades\RateLimiter;

it('exposes the OpnForm MCP endpoint and initializes the protocol', function () {
    $response = $this->postJson('/mcp', [
        'jsonrpc' => '2.0',
        'id' => 1,
        'method' => 'initialize',
        'params' => [
            'protocolVersion' => '2025-06-18',
            'capabilities' => [],
            'clientInfo' => [
                'name' => 'OpnForm test client',
                'version' => '1.0.0',
            ],
        ],
    ], [
        'Accept' => 'application/json, text/event-stream',
    ]);

    $response->assertOk()
        ->assertJsonPath('result.serverInfo.name', 'OpnForm')
        ->assertJsonPath('result.serverInfo.version', '1.0.0')
        ->assertJsonPath('result.protocolVersion', '2025-06-18');
});

it('registers a permissive dedicated rate limiter for MCP traffic', function () {
    $limits = RateLimiter::limiter('mcp')(request());

    expect($limits)->toHaveCount(2)
        ->and($limits[0]->maxAttempts)->toBe(120)
        ->and($limits[1]->maxAttempts)->toBe(3000);
});

it('publishes the versioned form definition schema', function () {
    OpnFormServer::resource(FormDefinitionSchemaResource::class)
        ->assertOk()
        ->assertSee('agent-form-definition/v1.json')
        ->assertSee('schema_version')
        ->assertSee('properties');
});

it('keeps every normalized top-level key represented in the published schema', function () {
    $definition = app(AgentFormDefinition::class);
    $normalized = $definition->normalizeAndValidate([
        'title' => 'Schema coverage',
        'properties' => [
            ['name' => 'Name', 'type' => 'text'],
        ],
    ]);

    expect(array_diff(array_keys($normalized), array_keys($definition->jsonSchema()['properties'])))->toBe([]);
});

it('publishes the canonical form field catalog', function () {
    OpnFormServer::resource(FormFieldCatalogResource::class)
        ->assertOk()
        ->assertSee('input_types')
        ->assertSee('nf-page-break')
        ->assertSee('payment')
        ->assertSee('save');
});

it('normalizes and validates a form definition without persistence', function () {
    OpnFormServer::tool(ValidateFormDefinitionTool::class, [
        'definition' => [
            'title' => '  Customer intake  ',
            'properties' => [
                [
                    'name' => '<b>Full name</b>',
                    'type' => 'text',
                    'help' => '<script>alert(1)</script><p>Legal name</p>',
                ],
                [
                    'name' => 'Plan',
                    'type' => 'radio',
                    'select' => [
                        'options' => [
                            ['name' => 'Basic'],
                            ['name' => 'Pro'],
                        ],
                    ],
                ],
            ],
        ],
    ])->assertOk()
        ->assertStructuredContent(function (AssertableJson $json) {
            $json->where('valid', true)
                ->where('schema_version', 1)
                ->where('definition.title', 'Customer intake')
                ->where('definition.visibility', 'draft')
                ->where('definition.properties.0.name', 'Full name')
                ->where('definition.properties.0.hidden', false)
                ->where('definition.properties.1.type', 'select')
                ->where('definition.properties.1.without_dropdown', true)
                ->where('definition.properties.1.select.options.0.id', 'Basic')
                ->has('definition.properties.0.id')
                ->etc();
        });

    $this->assertDatabaseCount('forms', 0);
});

it('rejects unknown field types and top-level keys', function () {
    OpnFormServer::tool(ValidateFormDefinitionTool::class, [
        'definition' => [
            'title' => 'Invalid form',
            'surprise' => true,
            'properties' => [
                ['name' => 'Unknown', 'type' => 'not-a-field'],
            ],
        ],
    ])->assertHasErrors();
});

it('rejects unsupported schema versions', function () {
    OpnFormServer::tool(ValidateFormDefinitionTool::class, [
        'definition' => [
            'schema_version' => 99,
            'title' => 'Future form',
            'properties' => [
                ['name' => 'Name', 'type' => 'text'],
            ],
        ],
    ])->assertHasErrors(['Unsupported schema version']);
});
