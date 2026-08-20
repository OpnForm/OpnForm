<?php

use App\Http\Controllers\AgentFormDraftController;
use App\Mcp\Servers\OpnFormServer;
use App\Mcp\Tools\PreviewFormDraftTool;
use App\Models\Forms\AgentFormDraft;
use App\Models\OAuthProvider;
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
    config()->set('app.self_hosted', false);
    config()->set('app.front_api_secret', 'test-front-secret');
    config()->set('app.front_url', 'https://opnform.test');
});

it('renders an MCP App preview with short-lived preview and reusable editor links', function () {
    $created = app(AgentFormDraftService::class)->create(editorDraftDefinition());

    OpnFormServer::tool(PreviewFormDraftTool::class, [
        'draft_token' => $created['token'],
    ])->assertOk()
        ->assertSee('preview_url')
        ->assertSee('editor_url')
        ->assertSee('Agent customer intake');

    OpnFormServer::resource(\App\Mcp\Apps\FormDraftPreviewApp::class)
        ->assertOk()
        ->assertSee('Open in OpnForm')
        ->assertSee('window.openai?.openExternal')
        ->assertSee('<iframe');

    $draft = $created['draft']->refresh();
    $handoff = $draft->editorHandoffs()->sole();
    expect($handoff->token_hash)->toMatch('/^[a-f0-9]{64}$/')
        ->and($handoff->expires_at->isSameSecond($draft->expires_at))->toBeTrue()
        ->and(app(AgentFormDraftService::class)->issueEditorHandoff($created['token'])['editor_url'])
        ->toStartWith('https://opnform.test/agent-drafts/edit#handoff=')
        ->and(app(\App\Mcp\Apps\FormDraftPreviewApp::class)->resolvedAppMeta()['csp']['frameDomains'])
        ->toBe(['https://opnform.test']);
});

it('publishes standard and ChatGPT-compatible CSP metadata with the full frontend origin', function () {
    config()->set('app.front_url', 'http://127.0.0.1:33676');
    $previewApp = app(\App\Mcp\Apps\FormDraftPreviewApp::class);
    $resource = $previewApp->handle(new \Laravel\Mcp\Request())
        ->content()
        ->toResource($previewApp);

    expect($previewApp->uri())->toBe('ui://opnform/form-draft-preview-v3')
        ->and($previewApp->resolvedAppMeta()['csp']['resourceDomains'])
        ->toBe(['http://127.0.0.1:33676'])
        ->and($previewApp->resolvedAppMeta()['csp']['frameDomains'])
        ->toBe(['http://127.0.0.1:33676'])
        ->and($resource['text'])->not->toContain('native-preview')
        ->and($resource['_meta']['openai/widgetCSP'])
        ->toBe([
            'connect_domains' => [],
            'resource_domains' => ['http://127.0.0.1:33676'],
            'frame_domains' => ['http://127.0.0.1:33676'],
            'redirect_domains' => ['http://127.0.0.1:33676'],
        ]);

    config()->set('app.front_url', 'https://opnform.test');
    $secureApp = app(\App\Mcp\Apps\FormDraftPreviewApp::class);
    $secureResource = $secureApp->handle(new \Laravel\Mcp\Request())
        ->content()
        ->toResource($secureApp);

    expect($secureApp->resolvedAppMeta()['csp']['frameDomains'])
        ->toBe(['https://opnform.test'])
        ->and($secureApp->resolvedAppMeta()['csp']['resourceDomains'])
        ->toBe(['https://opnform.test'])
        ->and($secureResource['_meta']['openai/widgetCSP']['frame_domains'])
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

it('keeps each generated browser preview link valid for one hour', function () {
    $this->freezeTime();
    $draft = app(AgentFormDraftService::class)->create(editorDraftDefinition())['draft'];
    $previewUrl = app(AgentFormDraftService::class)->previewUrl($draft);
    parse_str((string) parse_url($previewUrl, PHP_URL_QUERY), $previewQuery);
    parse_str((string) parse_url($previewQuery['source'], PHP_URL_QUERY), $sourceQuery);

    expect((int) $sourceQuery['expires'])
        ->toBe(now()->addMinutes(AgentFormDraftService::PREVIEW_URL_TTL_MINUTES)->timestamp);
});

it('keeps every guest editor link reusable until the draft expires', function () {
    $this->freezeTime();
    $drafts = app(AgentFormDraftService::class);
    $created = $drafts->create(editorDraftDefinition());
    $firstHandoff = $drafts->issueEditorHandoff($created['token']);
    $draft = $created['draft']->refresh();
    $firstCapability = $draft->editorHandoffs()->sole();

    expect($firstCapability->expires_at->isSameSecond($draft->expires_at))->toBeTrue();

    $response = $this->withHeader('x-api-secret', 'test-front-secret')
        ->postJson(route('agent-drafts.handoff.consume'), [
            'handoff_token' => $firstHandoff['handoff_token'],
        ])
        ->assertOk()
        ->assertJsonPath('draft.version', 1);

    $editorSession = $response->json('editor_session');
    $draft->refresh();
    expect($editorSession)->toHaveLength(43)
        ->and($draft->editor_session_hash)->toBe(hash('sha256', $editorSession))
        ->and($firstCapability->refresh()->last_used_at)->not->toBeNull();

    $this->travel(1)->day();
    $secondHandoff = $drafts->issueEditorHandoff($created['token']);
    $this->withHeader('x-api-secret', 'test-front-secret')
        ->postJson(route('agent-drafts.handoff.consume'), [
            'handoff_token' => $secondHandoff['handoff_token'],
        ])
        ->assertOk()
        ->assertJsonPath('editor_session', $editorSession);

    $this->withHeader('x-api-secret', 'test-front-secret')
        ->postJson(route('agent-drafts.handoff.consume'), [
            'handoff_token' => $firstHandoff['handoff_token'],
        ])
        ->assertOk()
        ->assertJsonPath('editor_session', $editorSession);

    $this->assertDatabaseCount('agent_form_draft_handoffs', 2);

    $this->travel(5)->days();
    $this->travel(23)->hours();
    $this->withHeader('x-api-secret', 'test-front-secret')
        ->postJson(route('agent-drafts.handoff.consume'), [
            'handoff_token' => $firstHandoff['handoff_token'],
        ])
        ->assertOk();

    $this->travel(2)->hours();
    $this->withHeader('x-api-secret', 'test-front-secret')
        ->postJson(route('agent-drafts.handoff.consume'), [
            'handoff_token' => $firstHandoff['handoff_token'],
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('handoff_token');
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

it('refuses a guest payment account that does not belong to the claim workspace', function () {
    $user = User::factory()->create();
    $workspace = createUserWorkspace($user);
    $foreignProvider = OAuthProvider::factory()->for(User::factory()->create())->create([
        'provider' => 'stripe',
        'provider_user_id' => 'acct_foreign',
    ]);
    $drafts = app(AgentFormDraftService::class);
    $created = $drafts->create(editorDraftDefinition([
        'properties' => [[
            'id' => 'payment-field',
            'name' => 'Payment',
            'type' => 'payment',
            'amount' => 10,
            'currency' => 'USD',
            'stripe_account_id' => $foreignProvider->id,
        ]],
    ]));
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
            'workspace_id' => $workspace->id,
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('properties.0.stripe_account_id');

    $this->assertDatabaseCount('forms', 0);
});
