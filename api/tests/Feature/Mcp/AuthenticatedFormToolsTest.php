<?php

use App\Mcp\Servers\OpnFormServer;
use App\Mcp\Tools\CreateFormTool;
use App\Mcp\Tools\GetAccountContextTool;
use App\Mcp\Tools\GetFormTool;
use App\Mcp\Tools\ListFormsTool;
use App\Mcp\Tools\ListWorkspacesTool;
use App\Mcp\Tools\PublishFormTool;
use App\Mcp\Tools\TrashFormTool;
use App\Mcp\Tools\UpdateFormTool;
use App\Models\Forms\Form;
use App\Enums\SettingsKey;
use App\Models\Setting;
use App\Models\OAuthProvider;
use App\Models\User;
use App\Models\Workspace;
use App\Service\Forms\AgentFormDefinition;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
use Laravel\Passport\Passport;

function managedFormDefinition(array $overrides = []): array
{
    return array_replace([
        'schema_version' => 1,
        'title' => 'Agent-managed intake',
        'properties' => [
            ['id' => 'name', 'name' => 'Name', 'type' => 'text'],
            ['id' => 'email', 'name' => 'Email', 'type' => 'email'],
        ],
    ], $overrides);
}

function managedWorkspace(User $user, string $role = User::ROLE_ADMIN): Workspace
{
    $workspace = Workspace::factory()->create();
    $workspace->users()->attach($user, ['role' => $role]);

    return $workspace;
}

function managedForm(User $user, Workspace $workspace, array $overrides = []): Form
{
    return Form::factory()
        ->forWorkspace($workspace)
        ->createdBy($user)
        ->create(array_replace([
            'title' => 'Existing managed form',
            'visibility' => 'draft',
            'properties' => managedFormDefinition()['properties'],
            'computed_variables' => [],
            'settings' => [],
        ], $overrides));
}

function managedFormRevision(User $user, Form $form): string
{
    $management = app(\App\Service\Forms\McpFormManagementService::class);

    return $management->serializeForm($management->form($user, $form->id))['revision'];
}

function configureManagedSelfHostedMcp(): void
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

it('always advertises account tools as OAuth protected', function () {
    $tool = app(ListFormsTool::class);

    expect($tool->eligibleForRegistration())->toBeTrue()
        ->and($tool->toArray()['securitySchemes'])->toBe([
            [
                'type' => 'oauth2',
                'scopes' => ['mcp:use'],
            ],
        ]);
});

it('advertises guest and account tools with explicit per-tool auth policies', function () {
    $payload = [
        'jsonrpc' => '2.0',
        'id' => 1,
        'method' => 'tools/list',
        'params' => [],
    ];
    $headers = ['Accept' => 'application/json, text/event-stream'];

    $response = $this->postJson('/mcp', $payload, $headers)->assertOk();
    $tools = collect($response->json('result.tools'))->keyBy('name');

    expect($tools)->toHaveKeys(['create_form_draft', 'create_form_in_account', 'list_forms', 'trash_form'])
        ->not->toHaveKey('create_form')
        ->and($tools['create_form_draft']['title'])->toBe('Create a Guest Form Draft')
        ->and($tools['create_form_draft']['description'])
        ->toContain('default creation tool', 'works immediately without login', 'never request authentication')
        ->and($tools['create_form_draft']['securitySchemes'])->toBe([
            ['type' => 'noauth'],
        ])
        ->and($tools['create_form_in_account']['title'])->toBe('Save a Form to an OpnForm Account')
        ->and($tools['create_form_in_account']['description'])
        ->toContain('only when the user explicitly asks', 'use create_form_draft without login instead')
        ->and($tools['create_form_in_account']['securitySchemes'])->toBe([
            [
                'type' => 'oauth2',
                'scopes' => ['mcp:use'],
            ],
        ])
        ->and($tools['list_forms']['securitySchemes'])->toBe([
            [
                'type' => 'oauth2',
                'scopes' => ['mcp:use'],
            ],
        ]);

    $user = User::factory()->create();
    Passport::actingAs($user, ['mcp:use'], 'oauth');

    $this->postJson('/mcp', $payload, array_merge($headers, [
        'Authorization' => 'Bearer scoped-account-token',
    ]))->assertOk()
        ->assertSee(['create_form_draft', 'list_forms', 'trash_form']);
});

it('hides guest draft capabilities but keeps validation and OAuth tools on self-hosted instances', function () {
    config()->set('app.self_hosted', true);
    configureManagedSelfHostedMcp();
    Setting::set(SettingsKey::MCP_ENABLED, true);
    $headers = ['Accept' => 'application/json, text/event-stream'];

    $toolsResponse = $this->postJson('/mcp', [
        'jsonrpc' => '2.0',
        'id' => 1,
        'method' => 'tools/list',
        'params' => [],
    ], $headers)->assertOk();
    $toolNames = collect($toolsResponse->json('result.tools'))->pluck('name');

    expect($toolNames)
        ->toContain('validate_form_definition', 'create_form_in_account', 'list_forms', 'list_submissions')
        ->not->toContain(
            'create_form_draft',
            'get_form_draft',
            'patch_form_draft',
            'preview_form_draft',
            'open_form_draft_in_editor',
        );

    $resourcesResponse = $this->postJson('/mcp', [
        'jsonrpc' => '2.0',
        'id' => 2,
        'method' => 'resources/list',
        'params' => [],
    ], $headers)->assertOk();
    $resourceUris = collect($resourcesResponse->json('result.resources'))->pluck('uri');

    expect($resourceUris)
        ->toContain('opnform://schemas/agent-form-definition/v1', 'opnform://reference/form-fields/v1')
        ->not->toContain('ui://opnform/form-draft-preview-v5');

    $this->postJson('/mcp', [
        'jsonrpc' => '2.0',
        'id' => 3,
        'method' => 'tools/call',
        'params' => [
            'name' => 'create_form_draft',
            'arguments' => ['definition' => managedFormDefinition()],
        ],
    ], $headers)->assertOk()
        ->assertJsonPath('error.code', -32602)
        ->assertJsonPath('error.message', 'Tool [create_form_draft] not found.');
});

it('lists only the connected account workspaces with form write capability', function () {
    $user = User::factory()->create();
    $writable = managedWorkspace($user);
    $readonly = managedWorkspace($user, User::ROLE_READONLY);
    Workspace::factory()->create(['name' => 'Other account workspace']);

    OpnFormServer::actingAs($user, 'oauth')
        ->tool(GetAccountContextTool::class)
        ->assertOk()
        ->assertSee([$user->email, $writable->name, $readonly->name])
        ->assertDontSee('Other account workspace');

    OpnFormServer::actingAs($user, 'oauth')
        ->tool(ListWorkspacesTool::class)
        ->assertOk()
        ->assertSee(['can_write_forms', User::ROLE_READONLY]);
});

it('creates an unpublished form automatically when the account has one workspace', function () {
    $user = User::factory()->create();
    $workspace = managedWorkspace($user);

    OpnFormServer::actingAs($user, 'oauth')->tool(CreateFormTool::class, [
        'definition' => managedFormDefinition(['visibility' => 'public']),
    ])->assertOk()
        ->assertSee(['unpublished draft', 'Agent-managed intake', 'publish_form']);

    $form = Form::query()->sole();
    expect($form->workspace_id)->toBe($workspace->id)
        ->and($form->creator_id)->toBe($user->id)
        ->and($form->visibility)->toBe('draft')
        ->and($form->edit_url)->toBeString()->not->toBeEmpty();
});

it('requires workspace selection only when multiple workspaces are available', function () {
    $user = User::factory()->create();
    $selected = managedWorkspace($user);
    managedWorkspace($user);

    OpnFormServer::actingAs($user, 'oauth')->tool(CreateFormTool::class, [
        'definition' => managedFormDefinition(),
    ])->assertHasErrors(['multiple workspaces']);

    OpnFormServer::actingAs($user, 'oauth')->tool(CreateFormTool::class, [
        'workspace_id' => $selected->id,
        'definition' => managedFormDefinition(),
    ])->assertOk();

    expect(Form::query()->sole()->workspace_id)->toBe($selected->id);
});

it('allows readonly members to inspect forms but rejects every mutation', function () {
    $owner = User::factory()->create();
    $readonly = User::factory()->create();
    $workspace = managedWorkspace($owner);
    $workspace->users()->attach($readonly, ['role' => User::ROLE_READONLY]);
    $form = managedForm($owner, $workspace);

    OpnFormServer::actingAs($readonly, 'oauth')->tool(GetFormTool::class, [
        'form_id' => $form->id,
    ])->assertOk()->assertSee('Existing managed form');

    OpnFormServer::actingAs($readonly, 'oauth')->tool(CreateFormTool::class, [
        'workspace_id' => $workspace->id,
        'definition' => managedFormDefinition(),
    ])->assertHasErrors(['read-only']);

    OpnFormServer::actingAs($readonly, 'oauth')->tool(PublishFormTool::class, [
        'form_id' => $form->id,
        'expected_revision' => managedFormRevision($readonly, $form),
        'confirm_publish' => true,
    ])->assertHasErrors(['read-only']);
});

it('lists and searches only accessible non-trashed forms', function () {
    $user = User::factory()->create();
    $workspace = managedWorkspace($user);
    managedForm($user, $workspace, ['title' => 'Customer survey']);
    $trashed = managedForm($user, $workspace, ['title' => 'Customer survey old']);
    $trashed->delete();

    $other = User::factory()->create();
    managedForm($other, managedWorkspace($other), ['title' => 'Customer survey secret']);

    OpnFormServer::actingAs($user, 'oauth')->tool(ListFormsTool::class, [
        'search' => 'customer',
        'visibility' => 'draft',
    ])->assertOk()
        ->assertSee('Customer survey')
        ->assertDontSee(['Customer survey old', 'Customer survey secret']);
});

it('updates a canonical form without changing publication state and rejects stale writes', function () {
    $user = User::factory()->create();
    $workspace = managedWorkspace($user);
    $form = managedForm($user, $workspace, ['visibility' => 'public']);
    $management = app(\App\Service\Forms\McpFormManagementService::class);
    $expectedRevision = $management->serializeForm($management->form($user, $form->id))['revision'];
    $queries = [];
    DB::listen(function (QueryExecuted $query) use (&$queries) {
        $queries[] = $query->sql;
    });

    OpnFormServer::actingAs($user, 'oauth')->tool(UpdateFormTool::class, [
        'form_id' => $form->id,
        'expected_revision' => $expectedRevision,
        'definition' => managedFormDefinition([
            'title' => 'Updated by agent',
            'visibility' => 'draft',
        ]),
    ])->assertOk()->assertSee('Updated by agent');

    expect($form->refresh()->visibility)->toBe('public');

    if (DB::connection()->getDriverName() !== 'sqlite') {
        expect(collect($queries)->contains(fn (string $query) => str_contains(strtolower($query), 'for update')))->toBeTrue();
    }

    OpnFormServer::actingAs($user, 'oauth')->tool(UpdateFormTool::class, [
        'form_id' => $form->id,
        'expected_revision' => $expectedRevision,
        'definition' => managedFormDefinition(['title' => 'Stale overwrite']),
    ])->assertHasErrors(['changed since it was fetched']);

    expect($form->refresh()->title)->toBe('Updated by agent');
});

it('rejects Stripe accounts outside the selected workspace on create and update', function () {
    $user = User::factory()->create();
    $workspace = managedWorkspace($user);
    $form = managedForm($user, $workspace);
    $foreignProvider = OAuthProvider::factory()->for(User::factory()->create())->create([
        'provider' => 'stripe',
        'provider_user_id' => 'acct_foreign',
    ]);
    $definition = managedFormDefinition([
        'properties' => [[
            'id' => 'payment-field',
            'name' => 'Payment',
            'type' => 'payment',
            'amount' => 10,
            'currency' => 'USD',
            'stripe_account_id' => $foreignProvider->id,
        ]],
    ]);

    OpnFormServer::actingAs($user, 'oauth')->tool(CreateFormTool::class, [
        'workspace_id' => $workspace->id,
        'definition' => $definition,
    ])->assertHasErrors(['not associated with this workspace']);

    OpnFormServer::actingAs($user, 'oauth')->tool(UpdateFormTool::class, [
        'form_id' => $form->id,
        'expected_revision' => managedFormRevision($user, $form),
        'definition' => $definition,
    ])->assertHasErrors(['not associated with this workspace']);

    expect(Form::query()->count())->toBe(1)
        ->and($form->refresh()->properties)->toBe(managedFormDefinition()['properties']);
});

it('publishes only with confirmation and exposes no publication through update_form', function () {
    $user = User::factory()->create();
    $form = managedForm($user, managedWorkspace($user));
    $expectedRevision = managedFormRevision($user, $form);

    OpnFormServer::actingAs($user, 'oauth')->tool(PublishFormTool::class, [
        'form_id' => $form->id,
        'expected_revision' => $expectedRevision,
        'confirm_publish' => false,
    ])->assertHasErrors(['explicit confirmation']);

    expect($form->refresh()->visibility)->toBe('draft');

    $form->update(['title' => 'Changed after confirmation context']);
    OpnFormServer::actingAs($user, 'oauth')->tool(PublishFormTool::class, [
        'form_id' => $form->id,
        'expected_revision' => $expectedRevision,
        'confirm_publish' => true,
    ])->assertHasErrors(['changed since it was fetched']);

    OpnFormServer::actingAs($user, 'oauth')->tool(PublishFormTool::class, [
        'form_id' => $form->id,
        'expected_revision' => managedFormRevision($user, $form),
        'confirm_publish' => true,
    ])->assertOk()->assertSee('Form published');

    expect($form->refresh()->visibility)->toBe('public');
});

it('moves forms to soft-delete trash only with confirmation', function () {
    $user = User::factory()->create();
    $form = managedForm($user, managedWorkspace($user));
    $expectedRevision = managedFormRevision($user, $form);

    OpnFormServer::actingAs($user, 'oauth')->tool(TrashFormTool::class, [
        'form_id' => $form->id,
        'expected_revision' => $expectedRevision,
        'confirm_trash' => false,
    ])->assertHasErrors(['explicit confirmation']);

    $this->assertNotSoftDeleted($form);

    $form->update(['title' => 'Changed after confirmation context']);
    OpnFormServer::actingAs($user, 'oauth')->tool(TrashFormTool::class, [
        'form_id' => $form->id,
        'expected_revision' => $expectedRevision,
        'confirm_trash' => true,
    ])->assertHasErrors(['changed since it was fetched']);

    OpnFormServer::actingAs($user, 'oauth')->tool(TrashFormTool::class, [
        'form_id' => $form->id,
        'expected_revision' => managedFormRevision($user, $form),
        'confirm_trash' => true,
    ])->assertOk()->assertSee(['moved to trash', 'does not expose restore']);

    $this->assertSoftDeleted($form);
});

it('returns canonical definitions for legacy forms without mutating them', function () {
    $user = User::factory()->create();
    $form = managedForm($user, managedWorkspace($user));

    $definition = app(AgentFormDefinition::class)->fromForm($form);

    expect($definition)
        ->toHaveKey('schema_version', 1)
        ->toHaveKey('title', 'Existing managed form')
        ->toHaveKey('properties')
        ->not->toHaveKey('creator_id')
        ->not->toHaveKey('workspace_id');
});
