<?php

use App\Mcp\Servers\OpnFormServer;
use App\Mcp\Tools\GetFormDraftTool;
use App\Mcp\Tools\PreviewFormDraftTool;
use App\Mcp\Tools\RestoreFormDraftVersionTool;
use App\Models\Forms\Form;
use App\Service\Forms\AgentFormDraftService;
use App\Service\Forms\McpFormManagementService;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\URL;

beforeEach(function () {
    config()->set('app.self_hosted', false);
    config()->set('app.front_url', 'https://opnform.test');
});

function versionedDraft(): array
{
    return app(AgentFormDraftService::class)->create([
        'title' => 'Contact v1',
        'properties' => [['id' => 'name', 'name' => 'Name', 'type' => 'text']],
    ]);
}

function versionedPreviewSource($draft): string
{
    parse_str(parse_url(app(AgentFormDraftService::class)->previewUrl($draft), PHP_URL_QUERY), $query);

    return $query['source'];
}

it('keeps signed previews pinned after patches and restoration', function () {
    $service = app(AgentFormDraftService::class);
    $created = versionedDraft();
    $v1 = $created['draft']->definition;
    $firstUrl = versionedPreviewSource($created['draft']);
    $patched = $service->patch($created['token'], 1, [
        ['op' => 'set_form_values', 'values' => ['title' => 'Contact v2']],
        ['op' => 'add_block', 'block' => ['name' => 'Email', 'type' => 'email']],
    ]);
    $v2 = $patched->definition;
    $secondUrl = versionedPreviewSource($patched);

    $this->getJson($firstUrl)->assertOk()->assertJsonPath('draft.version', 1)->assertJsonPath('draft.definition', $v1);
    $this->getJson($secondUrl)->assertOk()->assertJsonPath('draft.version', 2)->assertJsonPath('draft.definition', $v2);

    $restored = $service->restore($created['token'], 2, 1);
    expect($restored->version)->toBe(3)->and($restored->definition)->toBe($v1);
    $this->getJson($firstUrl)->assertOk()->assertJsonPath('draft.definition', $v1);
    $this->getJson($secondUrl)->assertOk()->assertJsonPath('draft.definition', $v2);
    expect($service->get($created['token'], 2)->definition)->toBe($v2);
});

it('preserves revisions written through the editor too', function () {
    $service = app(AgentFormDraftService::class);
    $created = versionedDraft();
    $source = versionedPreviewSource($created['draft']);
    $session = $service->consumeEditorHandoff($service->issueEditorHandoff($created['token'])['handoff_token']);
    $definition = $created['draft']->definition;
    $definition['title'] = 'Edited in OpnForm';
    $service->replaceFromEditor($session['editor_session'], 1, $definition);

    $this->getJson($source)->assertOk()->assertJsonPath('draft.definition.title', 'Contact v1');
    expect($service->get($created['token'])->version)->toBe(2);
});

it('rejects stale restoration without changing either revision', function () {
    $service = app(AgentFormDraftService::class);
    $created = versionedDraft();
    $service->patch($created['token'], 1, [['op' => 'set_form_values', 'values' => ['title' => 'Newer work']]]);

    expect(fn () => $service->restore($created['token'], 1, 1))->toThrow(ValidationException::class);
    expect($service->get($created['token'])->definition['title'])->toBe('Newer work');
    $this->assertDatabaseCount('agent_form_draft_versions', 1);
});

it('never substitutes latest for a missing revision or another drafts revision', function () {
    $service = app(AgentFormDraftService::class);
    $first = versionedDraft();
    $service->patch($first['token'], 1, [['op' => 'set_form_values', 'values' => ['title' => 'Other draft v2']]]);
    $service->restore($first['token'], 2, 1);
    $second = versionedDraft();

    expect(fn () => $service->get($second['token'], 2))->toThrow(ValidationException::class);
    expect(fn () => $service->restore($second['token'], 1, 2))->toThrow(ValidationException::class);
    expect($service->get($second['token'])->version)->toBe(1);
});

it('signs the version and keeps expiry enforcement', function () {
    $created = versionedDraft();
    $source = versionedPreviewSource($created['draft']);
    expect($source)->toContain('version=1');
    $this->getJson(str_replace('version=1', 'version=2', $source))->assertForbidden();
    $this->travel(8)->days();
    $this->getJson($source)->assertForbidden();
    expect(fn () => app(AgentFormDraftService::class)->get($created['token'], 1))->toThrow(ValidationException::class);
});

it('keeps signed snapshots readable after saving without exposing later account edits', function () {
    $service = app(AgentFormDraftService::class);
    $created = versionedDraft();
    $firstUrl = versionedPreviewSource($created['draft']);
    $current = $service->patch($created['token'], 1, [['op' => 'set_form_values', 'values' => ['title' => 'Contact v2']]]);
    $secondUrl = versionedPreviewSource($current);
    $unversionedUrl = URL::temporarySignedRoute('agent-drafts.preview', $current->expires_at, ['draft' => $current->id]);
    $session = $service->consumeEditorHandoff($service->issueEditorHandoff($created['token'], 2)['handoff_token']);
    $user = $this->createUser();
    $workspace = $this->createUserWorkspace($user);
    $saved = $service->claim($session['editor_session'], 2, $workspace, $user);
    $saved['form']->forceFill(['title' => 'Changed after saving'])->save();

    $this->getJson($firstUrl)->assertOk()->assertJsonPath('draft.version', 1)->assertJsonPath('draft.definition.title', 'Contact v1');
    $this->getJson($secondUrl)->assertOk()->assertJsonPath('draft.version', 2)->assertJsonPath('draft.definition.title', 'Contact v2');
    $this->getJson($unversionedUrl)->assertNotFound();
    expect(fn () => $service->get($created['token']))->toThrow(ValidationException::class);
    expect(fn () => $service->restore($created['token'], 2, 1))->toThrow(ValidationException::class);
    $this->travel(8)->days();
    $this->getJson($firstUrl)->assertForbidden();
});

it('keeps the guest draft independent when ChatGPT saves a copy in an account', function () {
    $service = app(AgentFormDraftService::class);
    $created = versionedDraft();
    $source = versionedPreviewSource($created['draft']);
    $user = $this->createUser();
    $workspace = $this->createUserWorkspace($user);
    $saved = app(McpFormManagementService::class)->create($user, $workspace->id, $created['draft']->definition);
    $form = Form::query()->findOrFail($saved['form']['id']);

    $service->patch($created['token'], 1, [['op' => 'set_form_values', 'values' => ['title' => 'Guest v2']]]);
    expect($form->refresh()->title)->toBe('Contact v1');
    $form->forceFill(['title' => 'Edited in the account'])->save();
    $restored = $service->restore($created['token'], 2, 1);

    expect($restored->version)->toBe(3)
        ->and($restored->definition['title'])->toBe('Contact v1')
        ->and($service->get($created['token'])->isAvailable())->toBeTrue()
        ->and($form->refresh()->title)->toBe('Edited in the account');
    $this->getJson($source)->assertOk()->assertJsonPath('draft.version', 1)->assertJsonPath('draft.definition.title', 'Contact v1');
});

it('does not open a newer draft from a historical preview', function () {
    $service = app(AgentFormDraftService::class);
    $created = versionedDraft();
    $service->patch($created['token'], 1, [['op' => 'set_form_values', 'values' => ['title' => 'Newer work']]]);

    expect(fn () => $service->issueEditorHandoff($created['token'], 1))->toThrow(ValidationException::class);
    $this->assertDatabaseCount('agent_form_draft_handoffs', 0);
    expect($service->issueEditorHandoff($created['token'], 2)['editor_url'])->toContain('/agent-drafts/edit');
});

it('checks a pinned editor link again when it is consumed', function () {
    $service = app(AgentFormDraftService::class);
    $created = versionedDraft();
    $link = $service->issueEditorHandoff($created['token'], 1);
    $service->patch($created['token'], 1, [['op' => 'set_form_values', 'values' => ['title' => 'Newer work']]]);

    expect(fn () => $service->consumeEditorHandoff($link['handoff_token']))->toThrow(ValidationException::class);
    expect($created['draft']->refresh()->editor_session_hash)->toBeNull();
    expect($service->get($created['token'])->definition['title'])->toBe('Newer work');
});

it('allows a pinned editor link to be reused while its version is still current', function () {
    $service = app(AgentFormDraftService::class);
    $created = versionedDraft();
    $link = $service->issueEditorHandoff($created['token'], 1);
    $first = $service->consumeEditorHandoff($link['handoff_token']);
    $second = $service->consumeEditorHandoff($link['handoff_token']);

    expect($second['editor_session'])->toBe($first['editor_session'])
        ->and($second['draft']->version)->toBe(1);
});

it('exposes historical reads previews and restoration to MCP without mutating old versions', function () {
    $service = app(AgentFormDraftService::class);
    $created = versionedDraft();
    $service->patch($created['token'], 1, [['op' => 'set_form_values', 'values' => ['title' => 'Contact v2']]]);
    foreach ([GetFormDraftTool::class, PreviewFormDraftTool::class] as $tool) {
        OpnFormServer::tool($tool, ['draft_handle' => $created['token'], 'version' => 1])
            ->assertOk()->assertSee('Contact v1')->assertDontSee('Contact v2');
    }
    OpnFormServer::tool(RestoreFormDraftVersionTool::class, [
        'draft_handle' => $created['token'], 'expected_version' => 2, 'version' => 1,
    ])->assertOk()->assertSee('Contact v1');
    expect($service->get($created['token'])->version)->toBe(3)
        ->and($service->get($created['token'], 2)->definition['title'])->toBe('Contact v2');
});

it('starts history from the existing version without inventing lost history', function () {
    $service = app(AgentFormDraftService::class);
    $created = versionedDraft();
    // Simulate a pre-migration draft whose first four definitions were overwritten.
    $created['draft']->forceFill(['version' => 5])->save();
    $source = versionedPreviewSource($created['draft']);
    $service->patch($created['token'], 5, [['op' => 'set_form_values', 'values' => ['title' => 'Version 6']]]);
    $this->getJson($source)->assertOk()->assertJsonPath('draft.version', 5)->assertJsonPath('draft.definition.title', 'Contact v1');
    expect(fn () => $service->get($created['token'], 4))->toThrow(ValidationException::class);
    expect($service->restore($created['token'], 6, 5)->version)->toBe(7);
});

it('does not save a revision for a rejected modification', function () {
    $service = app(AgentFormDraftService::class);
    $created = versionedDraft();
    expect(fn () => $service->patch($created['token'], 1, [
        ['op' => 'set_form_values', 'values' => ['title' => 'Changed']],
        ['op' => 'not_an_operation'],
    ]))->toThrow(ValidationException::class);
    $this->assertDatabaseCount('agent_form_draft_versions', 0);
    expect($service->get($created['token'])->version)->toBe(1)
        ->and($service->get($created['token'])->definition['title'])->toBe('Contact v1');
});
