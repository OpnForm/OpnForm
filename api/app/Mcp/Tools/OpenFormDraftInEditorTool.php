<?php

namespace App\Mcp\Tools;

use App\Service\Forms\AgentFormDraftService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;
use Laravel\Mcp\Server\Tools\Annotations\IsOpenWorld;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('open_form_draft_in_editor')]
#[Description('Create a reusable OpnForm editor link that remains valid until the guest draft expires.')]
#[IsReadOnly(false)]
#[IsDestructive(false)]
#[IsOpenWorld(false)]
class OpenFormDraftInEditorTool extends GuestDraftMcpTool
{
    public function handle(Request $request, AgentFormDraftService $drafts): ResponseFactory
    {
        $validated = $request->validate([
            'draft_token' => ['required', 'string', 'size:43'],
        ]);

        return Response::structured($drafts->issueEditorHandoff($validated['draft_token']));
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'draft_token' => $schema->string()->min(43)->max(43)->required(),
        ];
    }

    public function outputSchema(JsonSchema $schema): array
    {
        return [
            'handoff_token' => $schema->string()->min(43)->max(43)->required(),
            'editor_url' => $schema->string()->format('uri')->required(),
            'expires_at' => $schema->string()->format('date-time')->required(),
        ];
    }
}
