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
        ->assertJsonPath('result.protocolVersion', '2025-06-18')
        ->assertJsonPath('result.instructions', function (string $instructions): bool {
            $discoveryInstructions = substr($instructions, 0, 1024);

            return str_contains($discoveryInstructions, 'Default every request to create a new form to the guest draft workflow')
                && str_contains($discoveryInstructions, 'A natural request such as "create a contact form" needs no login')
                && str_contains($discoveryInstructions, 'Never ask the user to connect')
                && str_contains($discoveryInstructions, 'OAuth is required only for connected-account operations')
                && str_contains($discoveryInstructions, 'Enabling or selecting the plugin is not OAuth authentication');
        });
});

it('advertises explicit safety annotations for every MCP tool', function () {
    $response = $this->postJson('/mcp', [
        'jsonrpc' => '2.0',
        'id' => 1,
        'method' => 'tools/list',
        'params' => [],
    ], [
        'Accept' => 'application/json, text/event-stream',
    ])->assertOk();

    $actual = collect($response->json('result.tools'))
        ->mapWithKeys(fn (array $tool): array => [
            $tool['name'] => collect($tool['annotations'])->only([
                'readOnlyHint',
                'destructiveHint',
                'openWorldHint',
            ])->all(),
        ])
        ->sortKeys()
        ->all();

    $readOnly = [
        'get_account_context',
        'get_form',
        'get_form_draft',
        'get_submission',
        'get_submission_export',
        'get_submission_stats',
        'get_workspace',
        'list_forms',
        'list_submissions',
        'list_workspaces',
        'validate_form_definition',
    ];
    $writes = [
        'create_form_draft',
        'create_form_in_account',
        'export_submissions',
        'open_form_draft_in_editor',
        'preview_form_draft',
    ];
    $destructivePrivateWrites = [
        'patch_form_draft',
    ];
    $destructiveOpenWorldWrites = [
        'trash_form',
        'update_form',
    ];

    $expected = collect($readOnly)
        ->mapWithKeys(fn (string $name): array => [$name => [
            'readOnlyHint' => true,
            'destructiveHint' => false,
            'openWorldHint' => false,
        ]])
        ->merge(collect($writes)->mapWithKeys(fn (string $name): array => [$name => [
            'readOnlyHint' => false,
            'destructiveHint' => false,
            'openWorldHint' => false,
        ]]))
        ->merge(collect($destructivePrivateWrites)->mapWithKeys(fn (string $name): array => [$name => [
            'readOnlyHint' => false,
            'destructiveHint' => true,
            'openWorldHint' => false,
        ]]))
        ->merge(collect($destructiveOpenWorldWrites)->mapWithKeys(fn (string $name): array => [$name => [
            'readOnlyHint' => false,
            'destructiveHint' => true,
            'openWorldHint' => true,
        ]]))
        ->put('publish_form', [
            'readOnlyHint' => false,
            'destructiveHint' => false,
            'openWorldHint' => true,
        ])
        ->sortKeys()
        ->all();

    expect($actual)->toBe($expected);
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
        ->assertSee('presentation_modes')
        ->assertSee('focused')
        ->assertSee('block_media')
        ->assertSee('trycloudflare.com')
        ->assertSee('save');
});

it('requires public durable HTTPS URLs for agent-provided media', function () {
    $definition = app(AgentFormDefinition::class);

    expect(fn () => $definition->normalizeAndValidate([
        'title' => 'Unsafe media',
        'cover_picture' => 'http://127.0.0.1/cover.png',
        'properties' => [
            ['name' => 'Name', 'type' => 'text'],
        ],
    ]))->toThrow(\Illuminate\Validation\ValidationException::class);

    $validated = $definition->normalizeAndValidate([
        'title' => 'Safe media',
        'cover_picture' => 'https://cdn.example.com/cover.png',
        'logo_picture' => 'https://cdn.example.com/logo.png',
        'properties' => [
            ['name' => 'Name', 'type' => 'text'],
        ],
    ]);

    expect($validated['cover_picture'])->toBe('https://cdn.example.com/cover.png')
        ->and($validated['logo_picture'])->toBe('https://cdn.example.com/logo.png');
});

it('documents block media in the published form definition schema', function () {
    $schema = app(AgentFormDefinition::class)->jsonSchema();

    expect($schema['$defs']['block']['properties']['image']['$ref'])
        ->toBe('#/$defs/blockImage')
        ->and($schema['$defs']['blockImage'])->not->toHaveKey('required')
        ->and($schema['$defs']['blockImage']['properties']['url']['type'])->toBe(['string', 'null'])
        ->and($schema['$defs']['blockImage']['properties']['layout']['enum'])
        ->toContain('right-split', 'background');
});

it('applies the public media URL policy to block images', function () {
    $definition = app(AgentFormDefinition::class);

    expect(fn () => $definition->normalizeAndValidate([
        'title' => 'Unsafe block image',
        'properties' => [
            [
                'name' => 'Introduction',
                'type' => 'nf-text',
                'content' => '<p>Welcome</p>',
                'image' => ['url' => 'https://draft.trycloudflare.com/image.png'],
            ],
        ],
    ]))->toThrow(\Illuminate\Validation\ValidationException::class);

    $validated = $definition->normalizeAndValidate([
        'title' => 'Safe block image',
        'properties' => [
            [
                'name' => 'Introduction',
                'type' => 'nf-text',
                'content' => '<p>Welcome</p>',
                'image' => ['url' => 'https://cdn.example.com/image.png'],
            ],
        ],
    ]);

    expect($validated['properties'][0]['image']['url'])->toBe('https://cdn.example.com/image.png');
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

it('applies the same custom CSS and settings validation as the form API', function () {
    $definition = [
        'title' => 'Unsafe customization',
        'properties' => [
            ['name' => 'Name', 'type' => 'text'],
        ],
    ];

    OpnFormServer::tool(ValidateFormDefinitionTool::class, [
        'definition' => array_merge($definition, [
            'custom_css' => '</style><script>alert(1)</script>',
        ]),
    ])->assertHasErrors(['valid CSS']);

    OpnFormServer::tool(ValidateFormDefinitionTool::class, [
        'definition' => array_merge($definition, [
            'settings' => ['auto_next' => 'yes'],
        ]),
    ])->assertHasErrors(['true or false']);

    OpnFormServer::tool(ValidateFormDefinitionTool::class, [
        'definition' => array_merge($definition, [
            'custom_css' => '.form-root { color: #2563eb; }',
            'settings' => ['auto_next' => true],
        ]),
    ])->assertOk();
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
