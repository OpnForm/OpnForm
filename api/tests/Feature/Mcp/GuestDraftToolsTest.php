<?php

use App\Mcp\Servers\OpnFormServer;
use App\Mcp\Tools\CreateFormDraftTool;
use App\Mcp\Tools\GetFormDraftTool;
use App\Mcp\Tools\OpenFormDraftInEditorTool;
use App\Mcp\Tools\PatchFormDraftTool;
use App\Mcp\Tools\PreviewFormDraftTool;
use App\Models\Forms\AgentFormDraft;
use App\Service\Forms\AgentFormDraftRateLimiter;
use App\Service\Forms\AgentFormDraftService;
use Illuminate\Validation\ValidationException;

function guestDraftDefinition(array $overrides = []): array
{
    return array_replace([
        'title' => 'Customer intake',
        'properties' => [
            ['id' => 'name-field', 'name' => 'Name', 'type' => 'text'],
            ['id' => 'email-field', 'name' => 'Email', 'type' => 'email'],
        ],
    ], $overrides);
}

beforeEach(function () {
    config()->set('app.self_hosted', false);
});

it('publishes a neutral draft handle contract and accurate preview annotations', function () {
    $create = app(CreateFormDraftTool::class)->toArray();
    $preview = app(PreviewFormDraftTool::class)->toArray();
    $open = app(OpenFormDraftInEditorTool::class)->toArray();

    expect($create['outputSchema']['properties'])->toHaveKey('draft_handle')
        ->and($create['outputSchema']['properties'])->not->toHaveKey('preview_url')
        ->and($create['outputSchema']['properties'])->not->toHaveKey('draft_token')
        ->and($create['_meta'])->not->toHaveKey('ui')
        ->and(app(PatchFormDraftTool::class)->toArray()['_meta'])->not->toHaveKey('ui')
        ->and($preview['_meta']['ui']['resourceUri'])->toBe('ui://opnform/form-draft-preview.html')
        ->and($preview['_meta']['openai/outputTemplate'])->toBe('ui://opnform/form-draft-preview.html')
        ->and($preview['inputSchema']['properties'])->toHaveKey('draft_handle')
        ->and($preview['description'])
        ->toContain('modify the draft again or save it', 'Do not request OAuth unless the user chooses save')
        ->and($preview['outputSchema']['properties'])->toHaveKey('next_step')
        ->and($preview['outputSchema']['properties'])->not->toHaveKeys([
            'preview_url',
            'editor_url',
            'editor_link_expires_at',
        ])
        ->and($preview['annotations']['readOnlyHint'])->toBeTrue()
        ->and($open['_meta']['ui']['visibility'])->toBe(['model', 'app'])
        ->and($open['outputSchema']['properties'])->toHaveKey('editor_handoff_ready')
        ->and($open['outputSchema']['properties'])->not->toHaveKey('editor_url')
        ->and($open['outputSchema']['properties'])->not->toHaveKey('handoff_token');
});

it('creates a seven-day server-side guest draft and returns an opaque handle', function () {
    $before = now();

    OpnFormServer::tool(CreateFormDraftTool::class, [
        'definition' => guestDraftDefinition(['visibility' => 'public']),
    ])->assertOk()
        ->assertSee('draft_handle')
        ->assertDontSee('preview_url')
        ->assertDontSee('capability secret')
        ->assertSee('Customer intake');

    $draft = AgentFormDraft::query()->sole();

    expect($draft->token_hash)->toMatch('/^[a-f0-9]{64}$/')
        ->and($draft->definition['visibility'])->toBe('draft')
        ->and($draft->version)->toBe(1)
        ->and($draft->schema_version)->toBe(1)
        ->and($draft->status)->toBe(AgentFormDraft::STATUS_ACTIVE)
        ->and($draft->expires_at->betweenIncluded($before->copy()->addDays(7)->subSecond(), now()->addDays(7)->addSecond()))->toBeTrue();

    $this->assertDatabaseCount('forms', 0);
});

it('fetches a draft with its opaque handle without exposing stored hashes', function () {
    $created = app(AgentFormDraftService::class)->create(guestDraftDefinition());

    OpnFormServer::tool(GetFormDraftTool::class, [
        'draft_handle' => $created['token'],
    ])->assertOk()
        ->assertSee('Customer intake')
        ->assertDontSee($created['draft']->token_hash)
        ->assertDontSee('token_hash');
});

it('patches form values and blocks with optimistic versioning', function () {
    $drafts = app(AgentFormDraftService::class);
    $created = $drafts->create(guestDraftDefinition());

    $response = OpnFormServer::tool(PatchFormDraftTool::class, [
        'draft_handle' => $created['token'],
        'expected_version' => 1,
        'operations' => [
            [
                'op' => 'set_form_values',
                'values' => ['title' => 'Qualified lead', 'visibility' => 'public'],
            ],
            [
                'op' => 'update_block',
                'block_id' => 'name-field',
                'patch' => ['name' => 'Full legal name', 'required' => true],
            ],
            [
                'op' => 'add_block',
                'index' => 1,
                'block' => ['name' => 'Company', 'type' => 'text'],
            ],
            [
                'op' => 'move_block',
                'block_id' => 'email-field',
                'to_index' => 0,
            ],
        ],
    ]);

    $response->assertOk()
        ->assertSee('Qualified lead')
        ->assertSee('Full legal name')
        ->assertDontSee('preview_url')
        ->assertSee('version');

    $updated = $created['draft']->refresh();

    expect($updated->version)->toBe(2)
        ->and($updated->definition['visibility'])->toBe('draft')
        ->and($updated->definition['properties'][0]['id'])->toBe('email-field')
        ->and($updated->definition['properties'][1]['name'])->toBe('Full legal name')
        ->and($updated->definition['properties'][1]['required'])->toBeTrue()
        ->and($updated->definition['properties'][2]['name'])->toBe('Company')
        ->and($updated->definition['properties'][2]['id'])->toBeString()->not->toBeEmpty();
});

it('describes strict style values in the patch schema and field catalog', function () {
    $tool = OpnFormServer::tool(PatchFormDraftTool::class, [
        'draft_handle' => str_repeat('x', 43),
        'expected_version' => 1,
        'operations' => [[
            'op' => 'set_form_values',
            'values' => ['border_radius' => 'large'],
        ]],
    ]);

    $tool->assertHasErrors(['Draft not found, expired, or already claimed']);

    $schema = app(PatchFormDraftTool::class)->schema(new \Illuminate\JsonSchema\JsonSchemaTypeFactory());

    $styleProperties = $schema['operations']->toArray()['items']['properties']['values']['properties'];

    expect($styleProperties['presentation_style']['enum'])->toBe(['classic', 'focused'])
        ->and($styleProperties['width']['enum'])->toBe(['centered', 'full'])
        ->and($styleProperties['size']['enum'])->toBe(['sm', 'md', 'lg'])
        ->and($styleProperties['theme']['enum'])->toBe(['default', 'simple', 'notion', 'minimal', 'transparent'])
        ->and($styleProperties['border_radius']['enum'])->toBe(['none', 'small', 'full'])
        ->and(\App\Service\Forms\AgentFormFieldCatalog::reference()['form_style']['border_radius'])
        ->toBe(['none', 'small', 'full']);
});

it('publishes and applies computed variable and display logic draft patches', function () {
    $schema = app(PatchFormDraftTool::class)->schema(new \Illuminate\JsonSchema\JsonSchemaTypeFactory());
    $operationProperties = $schema['operations']->toArray()['items']['properties'];

    expect($operationProperties['values']['properties']['computed_variables']['items']['properties'])
        ->toHaveKeys(['id', 'name', 'formula', 'result_type'])
        ->and($operationProperties['block']['properties'])
        ->toHaveKeys(['hidden', 'logic'])
        ->and($operationProperties['patch']['properties'])
        ->toHaveKeys(['hidden', 'logic'])
        ->and($operationProperties['patch']['properties']['logic']['type'])
        ->toBe(['object', 'null'])
        ->and($operationProperties['patch']['properties']['logic']['properties']['conditions']['properties']['value']['properties']['value']['type'])
        ->toBe(['string', 'number', 'boolean', 'object', 'array', 'null'])
        ->and($operationProperties['patch']['properties']['logic']['properties']['actions']['items']['enum'])
        ->toContain('show-block', 'hide-block', 'require-answer');

    $drafts = app(AgentFormDraftService::class);
    $created = $drafts->create(guestDraftDefinition([
        'properties' => [
            ['id' => 'budget', 'name' => 'Budget', 'type' => 'number'],
            ['id' => 'details', 'name' => 'Details', 'type' => 'text'],
        ],
    ]));

    OpnFormServer::tool(PatchFormDraftTool::class, [
        'draft_handle' => $created['token'],
        'expected_version' => 1,
        'operations' => [
            [
                'op' => 'set_form_values',
                'values' => [
                    'computed_variables' => [[
                        'id' => 'cv_priority_score',
                        'name' => 'Priority score',
                        'formula' => '{budget} * 1.5',
                        'result_type' => 'number',
                    ]],
                ],
            ],
            [
                'op' => 'update_block',
                'block_id' => 'details',
                'patch' => [
                    'hidden' => true,
                    'logic' => [
                        'conditions' => [
                            'operatorIdentifier' => 'and',
                            'children' => [[
                                'identifier' => 'cv_priority_score',
                                'value' => [
                                    'operator' => 'greater_than',
                                    'property_meta' => ['id' => 'cv_priority_score', 'type' => 'computed'],
                                    'value' => 15000,
                                ],
                            ]],
                        ],
                        'actions' => ['show-block'],
                    ],
                ],
            ],
        ],
    ])->assertOk();

    $definition = $created['draft']->refresh()->definition;
    expect($created['draft']->version)->toBe(2)
        ->and($definition['computed_variables'][0]['formula'])->toBe('{budget} * 1.5')
        ->and($definition['properties'][1]['hidden'])->toBeTrue()
        ->and($definition['properties'][1]['logic']['conditions']['children'][0]['value']['value'])
        ->toBe(15000);
});

it('returns actionable validation errors for invalid style values', function () {
    $drafts = app(AgentFormDraftService::class);
    $created = $drafts->create(guestDraftDefinition());

    OpnFormServer::tool(PatchFormDraftTool::class, [
        'draft_handle' => $created['token'],
        'expected_version' => 1,
        'operations' => [[
            'op' => 'set_form_values',
            'values' => ['border_radius' => 'large'],
        ]],
    ])->assertHasErrors(['border_radius must be one of: none, small, full.']);
});

it('rejects stale patches without changing the draft', function () {
    $drafts = app(AgentFormDraftService::class);
    $created = $drafts->create(guestDraftDefinition());

    $drafts->patch($created['token'], 1, [[
        'op' => 'set_form_values',
        'values' => ['title' => 'First update'],
    ]]);

    OpnFormServer::tool(PatchFormDraftTool::class, [
        'draft_handle' => $created['token'],
        'expected_version' => 1,
        'operations' => [[
            'op' => 'set_form_values',
            'values' => ['title' => 'Stale overwrite'],
        ]],
    ])->assertHasErrors(['Current version is 2']);

    expect($created['draft']->refresh()->definition['title'])->toBe('First update')
        ->and($created['draft']->version)->toBe(2);
});

it('rolls back invalid patch operations and preserves the previous version', function () {
    $drafts = app(AgentFormDraftService::class);
    $created = $drafts->create(guestDraftDefinition([
        'properties' => [
            ['id' => 'only-field', 'name' => 'Name', 'type' => 'text'],
        ],
    ]));

    OpnFormServer::tool(PatchFormDraftTool::class, [
        'draft_handle' => $created['token'],
        'expected_version' => 1,
        'operations' => [[
            'op' => 'remove_block',
            'block_id' => 'only-field',
        ]],
    ])->assertHasErrors();

    expect($created['draft']->refresh()->version)->toBe(1)
        ->and($created['draft']->definition['properties'])->toHaveCount(1);
});

it('cleans dependent variables and logic after removing an MCP draft block', function () {
    $drafts = app(AgentFormDraftService::class);
    $created = $drafts->create(guestDraftDefinition([
        'properties' => [
            ['id' => 'amount', 'name' => 'Amount', 'type' => 'number'],
            [
                'id' => 'details',
                'name' => 'Details',
                'type' => 'text',
                'hidden' => true,
                'logic' => [
                    'conditions' => [
                        'identifier' => 'cv_double',
                        'value' => [
                            'operator' => 'greater_than',
                            'property_meta' => ['id' => 'cv_double', 'type' => 'computed'],
                            'value' => 10,
                        ],
                    ],
                    'actions' => ['show-block'],
                ],
            ],
        ],
        'computed_variables' => [
            ['id' => 'cv_base', 'name' => 'Base', 'formula' => '{amount}', 'result_type' => 'number'],
            ['id' => 'cv_double', 'name' => 'Double', 'formula' => '{cv_base} * 2', 'result_type' => 'number'],
        ],
    ]));

    OpnFormServer::tool(PatchFormDraftTool::class, [
        'draft_handle' => $created['token'],
        'expected_version' => 1,
        'operations' => [[
            'op' => 'remove_block',
            'block_id' => 'amount',
        ]],
    ])->assertOk();

    $definition = $created['draft']->refresh()->definition;
    expect($definition['computed_variables'])->toBeEmpty()
        ->and($definition['properties'][0]['id'])->toBe('details')
        ->and($definition['properties'][0])->not->toHaveKey('logic');
});

it('rejects malformed, expired, and claimed draft capabilities with one generic error', function () {
    $drafts = app(AgentFormDraftService::class);
    $created = $drafts->create(guestDraftDefinition());

    OpnFormServer::tool(GetFormDraftTool::class, [
        'draft_handle' => str_repeat('x', 43),
    ])->assertHasErrors(['Draft not found, expired, or already claimed']);

    $created['draft']->forceFill(['expires_at' => now()->subSecond()])->save();

    OpnFormServer::tool(GetFormDraftTool::class, [
        'draft_handle' => $created['token'],
    ])->assertHasErrors(['Draft not found, expired, or already claimed']);

    $claimed = $drafts->create(guestDraftDefinition());
    $claimed['draft']->forceFill(['status' => AgentFormDraft::STATUS_CLAIMED])->save();

    OpnFormServer::tool(GetFormDraftTool::class, [
        'draft_handle' => $claimed['token'],
    ])->assertHasErrors(['Draft not found, expired, or already claimed']);
});

it('rejects duplicate block ids', function () {
    OpnFormServer::tool(CreateFormDraftTool::class, [
        'definition' => guestDraftDefinition([
            'properties' => [
                ['id' => 'duplicate', 'name' => 'One', 'type' => 'text'],
                ['id' => 'duplicate', 'name' => 'Two', 'type' => 'email'],
            ],
        ]),
    ])->assertHasErrors([
        'properties.1.id',
        'The field ID [duplicate] is already used by field 1.',
    ]);
});

it('accepts computed variables as display logic sources', function () {
    OpnFormServer::tool(CreateFormDraftTool::class, [
        'definition' => guestDraftDefinition([
            'properties' => [
                ['id' => 'amount', 'name' => 'Amount', 'type' => 'number'],
                [
                    'id' => 'details',
                    'name' => 'Details',
                    'type' => 'text',
                    'hidden' => true,
                    'logic' => [
                        'conditions' => [
                            'operatorIdentifier' => 'and',
                            'children' => [[
                                'identifier' => 'cv_total',
                                'value' => [
                                    'operator' => 'greater_than',
                                    'property_meta' => ['id' => 'cv_total', 'type' => 'computed'],
                                    'value' => 100,
                                ],
                            ]],
                        ],
                        'actions' => ['show-block'],
                    ],
                ],
            ],
            'computed_variables' => [[
                'id' => 'cv_total',
                'name' => 'Total',
                'formula' => '{amount} * 2',
                'result_type' => 'number',
            ]],
        ]),
    ])->assertOk();

    $this->assertDatabaseCount('agent_form_drafts', 1);
    $condition = AgentFormDraft::query()->firstOrFail()
        ->definition['properties'][1]['logic']['conditions']['children'][0];
    expect($condition['identifier'])->toBe('cv_total')
        ->and($condition['value']['property_meta'])->toBe(['id' => 'cv_total', 'type' => 'computed']);
});

it('removes display logic with missing MCP references before persisting', function () {
    OpnFormServer::tool(CreateFormDraftTool::class, [
        'definition' => guestDraftDefinition([
            'properties' => [[
                'id' => 'details',
                'name' => 'Details',
                'type' => 'text',
                'logic' => [
                    'conditions' => [
                        'operatorIdentifier' => 'and',
                        'children' => [[
                            'identifier' => 'removed',
                            'value' => [
                                'operator' => 'equals',
                                'property_meta' => ['id' => 'removed', 'type' => 'text'],
                                'value' => 'yes',
                            ],
                        ]],
                    ],
                    'actions' => ['show-block'],
                ],
            ]],
        ]),
    ])->assertOk();

    $this->assertDatabaseCount('agent_form_drafts', 1);
    expect(AgentFormDraft::query()->firstOrFail()->definition['properties'][0])->not->toHaveKey('logic');
});

it('rejects machine-like labels before persisting a guest draft', function () {
    OpnFormServer::tool(CreateFormDraftTool::class, [
        'definition' => guestDraftDefinition([
            'properties' => [
                ['id' => 'full-name', 'name' => 'full_name', 'type' => 'text'],
                ['id' => 'contact-email', 'name' => 'contact_email', 'type' => 'email'],
            ],
        ]),
    ])->assertHasErrors([
        'properties.0.name: Replace the raw label [full_name] with clear respondent-facing copy in sentence case.',
        'properties.1.name: Replace the raw label [contact_email] with clear respondent-facing copy in sentence case.',
    ]);

    $this->assertDatabaseCount('agent_form_drafts', 0);
});

it('rejects markdown text blocks before persisting a guest draft', function () {
    OpnFormServer::tool(CreateFormDraftTool::class, [
        'definition' => guestDraftDefinition([
            'properties' => [[
                'id' => 'introduction',
                'name' => 'Introduction',
                'type' => 'nf-text',
                'content' => '# Contact us\n\n**Tell us how we can help.**',
            ]],
        ]),
    ])->assertHasErrors([
        'properties.0.content: Replace Markdown with sanitized HTML',
    ]);

    $this->assertDatabaseCount('agent_form_drafts', 0);
});

it('rejects oversized draft definitions before persistence', function () {
    OpnFormServer::tool(CreateFormDraftTool::class, [
        'definition' => guestDraftDefinition([
            'custom_code' => str_repeat('x', 1_000_001),
        ]),
    ])->assertHasErrors(['must not exceed 1 MB']);

    $this->assertDatabaseCount('agent_form_drafts', 0);
});

it('sanitizes rich text html before an agent draft can be rendered', function () {
    $created = app(AgentFormDraftService::class)->create(guestDraftDefinition([
        'properties' => [[
            'id' => 'rich-text',
            'name' => 'Introduction',
            'type' => 'nf-text',
            'content' => '<p>Hello</p><img src=x onerror="alert(1)"><script>alert(2)</script>',
        ]],
    ]));

    $content = $created['draft']->definition['properties'][0]['content'];
    expect($content)->toContain('<p>Hello</p>')
        ->not->toContain('onerror')
        ->not->toContain('<script');
});

it('rate limits only excessive draft creation bursts', function () {
    config()->set('opnform.mcp.rate_limit.draft_creates_per_minute', 2);
    config()->set('opnform.mcp.rate_limit.draft_creates_per_hour', 10);
    $limiter = app(AgentFormDraftRateLimiter::class);

    $limiter->hit('ip:192.0.2.1');
    $limiter->hit('ip:192.0.2.1');

    expect(fn () => $limiter->hit('ip:192.0.2.1'))
        ->toThrow(ValidationException::class, 'Too many drafts');

    expect(fn () => $limiter->hit('ip:192.0.2.2'))->not->toThrow(ValidationException::class);
});

it('purges only expired agent drafts with the scheduled command', function () {
    $drafts = app(AgentFormDraftService::class);
    $active = $drafts->create(guestDraftDefinition())['draft'];
    $expired = $drafts->create(guestDraftDefinition())['draft'];
    $expired->forceFill(['expires_at' => now()->subMinute()])->save();

    $this->artisan('agent-drafts:purge-expired')
        ->expectsOutput('Purged 1 expired agent form draft(s).')
        ->assertSuccessful();

    $this->assertDatabaseHas('agent_form_drafts', ['id' => $active->id]);
    $this->assertDatabaseMissing('agent_form_drafts', ['id' => $expired->id]);
});
