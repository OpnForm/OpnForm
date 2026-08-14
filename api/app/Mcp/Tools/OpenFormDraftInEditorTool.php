<?php

namespace App\Mcp\Tools;

use App\Service\Forms\AgentFormDraftService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsOpenWorld;

#[Name('open_form_draft_in_editor')]
#[Description('Create a fresh one-time OpnForm editor link for a guest draft. Use when the previous editor link expired or was consumed.')]
#[IsOpenWorld]
class OpenFormDraftInEditorTool extends Tool
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
}
