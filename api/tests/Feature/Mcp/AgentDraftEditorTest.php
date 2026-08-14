<?php

use App\Http\Controllers\AgentFormDraftController;
use App\Mcp\Servers\OpnFormServer;
use App\Mcp\Tools\PreviewFormDraftTool;
use App\Models\Forms\AgentFormDraft;
use App\Models\User;
use App\Service\Forms\AgentFormDraftService;
use Illuminate\Support\Facades\URL;

function editorDraftDefinition(array $overrides = []): array
{
    return array_replace([
        'title' => 'Agent customer intake',
        'properties' => [
            ['id' => 'name-field', 'name' => 'Name', 'type' => 'text'],
            ['id' => 'email-field', 'name' => 'Email', 'type' => 'email'],
        ],
    ], $overrides);
}

beforeEach(function () {
    config()->set('app.front_api_secret', 'test-front-secret');
    config()->set('app.front_url', 'https://opnform.test');
});

it('renders an MCP App preview with short-lived preview and editor links', function () {
    $created = app(AgentFormDraftService::class)->create(editorDraftDefinition());

    OpnFormServer::tool(PreviewFormDraftTool::class, [
        'draft_token' => $created['token'],
    ])->assertOk()
        ->assertSee('preview_url')
        ->assertSee('editor_url')
        ->assertSee('Agent customer intake');

    OpnFormServer::resource(\App\Mcp\Apps\FormDraftPreviewApp::class)
        ->assertOk()
        ->assertSee('Open in OpnForm editor')
        ->assertSee('<iframe');

    $draft = $created['draft']->refresh();
    expect($draft->handoff_token_hash)->toMatch('/^[a-f0-9]{64}$/')
        ->and($draft->handoff_expires_at->isFuture())->toBeTrue()
        ->and(app(AgentFormDraftService::class)->issueEditorHandoff($created['token'])['editor_url'])
        ->toStartWith('https://opnform.test/agent-drafts/edit#handoff=')
        ->and(app(\App\Mcp\Apps\FormDraftPreviewApp::class)->resolvedAppMeta()['csp']['frameDomains'])
        ->toBe(['https://opnform.test']);
});

it('serves preview data only through a valid signed URL without exposing capabilities', function () {
    $draft = app(AgentFormDraftService::class)->create(editorDraftDefinition())['draft'];
    $signedUrl = URL::temporarySignedRoute('agent-drafts.preview', now()->addMinute(), ['draft' => $draft->id]);

    $this->getJson($signedUrl)
        ->assertOk()
        ->assertHeader('Cache-Control', 'max-age=0, no-store, private')
        ->assertJsonPath('draft.definition.title', 'Agent customer intake')
        ->assertJsonMissing(['token_hash' => $draft->token_hash]);

    $this->getJson(route('agent-drafts.preview', $draft))->assertForbidden();
});

it('consumes editor handoffs once and stores only a hashed editor session', function () {
    $drafts = app(AgentFormDraftService::class);
    $created = $drafts->create(editorDraftDefinition());
    $handoff = $drafts->issueEditorHandoff($created['token']);

    $response = $this->withHeader('x-api-secret', 'test-front-secret')
        ->postJson(route('agent-drafts.handoff.consume'), [
            'handoff_token' => $handoff['handoff_token'],
        ])
        ->assertOk()
        ->assertJsonPath('draft.version', 1);

    $editorSession = $response->json('editor_session');
    $draft = $created['draft']->refresh();
    expect($editorSession)->toHaveLength(43)
        ->and($draft->editor_session_hash)->toBe(hash('sha256', $editorSession))
        ->and($draft->handoff_token_hash)->toBeNull()
        ->and($draft->handoff_consumed_at)->not->toBeNull();

    $this->withHeader('x-api-secret', 'test-front-secret')
        ->postJson(route('agent-drafts.handoff.consume'), [
            'handoff_token' => $handoff['handoff_token'],
        ])
        ->assertUnprocessable();
});

it('requires the trusted frontend secret for editor endpoints', function () {
    $drafts = app(AgentFormDraftService::class);
    $created = $drafts->create(editorDraftDefinition());
    $session = $drafts->consumeEditorHandoff(
        $drafts->issueEditorHandoff($created['token'])['handoff_token'],
    )['editor_session'];

    $this->withHeader(AgentFormDraftController::SESSION_HEADER, $session)
        ->getJson(route('agent-drafts.editor.current'))
        ->assertForbidden();
});

it('syncs editor replacements through the canonical optimistic version', function () {
    $drafts = app(AgentFormDraftService::class);
    $created = $drafts->create(editorDraftDefinition());
    $session = $drafts->consumeEditorHandoff(
        $drafts->issueEditorHandoff($created['token'])['handoff_token'],
    )['editor_session'];
    $headers = [
        'x-api-secret' => 'test-front-secret',
        AgentFormDraftController::SESSION_HEADER => $session,
    ];

    $definition = $created['draft']->definition;
    $definition['title'] = 'Edited in browser';

    $this->withHeaders($headers)
        ->putJson(route('agent-drafts.editor.replace'), [
            'expected_version' => 1,
            'definition' => $definition,
        ])
        ->assertOk()
        ->assertJsonPath('draft.version', 2)
        ->assertJsonPath('draft.definition.title', 'Edited in browser');

    $this->withHeaders($headers)
        ->putJson(route('agent-drafts.editor.replace'), [
            'expected_version' => 1,
            'definition' => $definition,
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('expected_version');

    expect($drafts->get($created['token'])->version)->toBe(2);
});

it('claims a draft explicitly into an owned workspace as an unpublished form', function () {
    $user = User::factory()->create();
    $workspace = createUserWorkspace($user);
    $drafts = app(AgentFormDraftService::class);
    $created = $drafts->create(editorDraftDefinition(['no_branding' => true]));
    $session = $drafts->consumeEditorHandoff(
        $drafts->issueEditorHandoff($created['token'])['handoff_token'],
    )['editor_session'];
    $headers = [
        'x-api-secret' => 'test-front-secret',
        AgentFormDraftController::SESSION_HEADER => $session,
    ];

    $response = $this->actingAs($user, 'api')
        ->withHeaders($headers)
        ->postJson(route('agent-drafts.editor.claim'), [
            'expected_version' => 1,
            'workspace_id' => $workspace->id,
        ])
        ->assertOk()
        ->assertJsonPath('form.visibility', 'draft')
        ->assertJsonPath('already_claimed', false)
        ->assertJsonStructure(['cleanings' => ['form']]);

    $formId = $response->json('form.id');
    $this->assertDatabaseHas('forms', [
        'id' => $formId,
        'workspace_id' => $workspace->id,
        'creator_id' => $user->id,
        'visibility' => 'draft',
    ]);
    $this->assertDatabaseHas('agent_form_drafts', [
        'id' => $created['draft']->id,
        'status' => AgentFormDraft::STATUS_CLAIMED,
        'claimed_form_id' => $formId,
    ]);

    $this->actingAs($user, 'api')
        ->withHeaders($headers)
        ->postJson(route('agent-drafts.editor.claim'), [
            'expected_version' => 1,
            'workspace_id' => $workspace->id,
        ])
        ->assertOk()
        ->assertJsonPath('form.id', $formId)
        ->assertJsonPath('already_claimed', true);

    $this->assertDatabaseCount('forms', 1);
});

it('refuses claim into another users workspace', function () {
    $user = User::factory()->create();
    $foreignWorkspace = createUserWorkspace(User::factory()->create());
    $drafts = app(AgentFormDraftService::class);
    $created = $drafts->create(editorDraftDefinition());
    $session = $drafts->consumeEditorHandoff(
        $drafts->issueEditorHandoff($created['token'])['handoff_token'],
    )['editor_session'];

    $this->actingAs($user, 'api')
        ->withHeaders([
            'x-api-secret' => 'test-front-secret',
            AgentFormDraftController::SESSION_HEADER => $session,
        ])
        ->postJson(route('agent-drafts.editor.claim'), [
            'expected_version' => 1,
            'workspace_id' => $foreignWorkspace->id,
        ])
        ->assertForbidden();

    $this->assertDatabaseCount('forms', 0);
});
