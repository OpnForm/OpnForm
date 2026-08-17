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
use App\Models\User;
use App\Models\Workspace;
use App\Service\Forms\AgentFormDefinition;
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

it('registers account tools only for an authenticated MCP account', function () {
    expect(app(ListFormsTool::class)->eligibleForRegistration())->toBeFalse();

    $user = User::factory()->create();
    auth('oauth')->setUser($user);

    expect(app(ListFormsTool::class)->eligibleForRegistration())->toBeTrue();
});

it('advertises guest tools anonymously and account tools only with scoped OAuth', function () {
    $payload = [
        'jsonrpc' => '2.0',
        'id' => 1,
        'method' => 'tools/list',
        'params' => [],
    ];
    $headers = ['Accept' => 'application/json, text/event-stream'];

    $this->postJson('/mcp', $payload, $headers)
        ->assertOk()
        ->assertSee('create_form_draft')
        ->assertDontSee('list_forms');

    $user = User::factory()->create();
    Passport::actingAs($user, ['mcp:use'], 'oauth');

    $this->postJson('/mcp', $payload, array_merge($headers, [
        'Authorization' => 'Bearer scoped-account-token',
    ]))->assertOk()
        ->assertSee(['create_form_draft', 'list_forms', 'trash_form']);
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
        ->and($form->visibility)->toBe('draft');
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

    OpnFormServer::actingAs($user, 'oauth')->tool(UpdateFormTool::class, [
        'form_id' => $form->id,
        'expected_revision' => $expectedRevision,
        'definition' => managedFormDefinition([
            'title' => 'Updated by agent',
            'visibility' => 'draft',
        ]),
    ])->assertOk()->assertSee('Updated by agent');

    expect($form->refresh()->visibility)->toBe('public');

    OpnFormServer::actingAs($user, 'oauth')->tool(UpdateFormTool::class, [
        'form_id' => $form->id,
        'expected_revision' => $expectedRevision,
        'definition' => managedFormDefinition(['title' => 'Stale overwrite']),
    ])->assertHasErrors(['changed since it was fetched']);

    expect($form->refresh()->title)->toBe('Updated by agent');
});

it('publishes only with confirmation and exposes no publication through update_form', function () {
    $user = User::factory()->create();
    $form = managedForm($user, managedWorkspace($user));

    OpnFormServer::actingAs($user, 'oauth')->tool(PublishFormTool::class, [
        'form_id' => $form->id,
        'confirm_publish' => false,
    ])->assertHasErrors(['explicit confirmation']);

    expect($form->refresh()->visibility)->toBe('draft');

    OpnFormServer::actingAs($user, 'oauth')->tool(PublishFormTool::class, [
        'form_id' => $form->id,
        'confirm_publish' => true,
    ])->assertOk()->assertSee('Form published');

    expect($form->refresh()->visibility)->toBe('public');
});

it('moves forms to soft-delete trash only with confirmation', function () {
    $user = User::factory()->create();
    $form = managedForm($user, managedWorkspace($user));

    OpnFormServer::actingAs($user, 'oauth')->tool(TrashFormTool::class, [
        'form_id' => $form->id,
        'confirm_trash' => false,
    ])->assertHasErrors(['explicit confirmation']);

    $this->assertNotSoftDeleted($form);

    OpnFormServer::actingAs($user, 'oauth')->tool(TrashFormTool::class, [
        'form_id' => $form->id,
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
