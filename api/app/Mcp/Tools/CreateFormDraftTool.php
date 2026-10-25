<?php

namespace App\Mcp\Tools;

use App\Mcp\Support\McpOutputSchema;
use App\Service\Forms\AgentFormDraftRateLimiter;
use App\Service\Forms\AgentFormDraftService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;
use Laravel\Mcp\Server\Tools\Annotations\IsOpenWorld;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('create_form_draft')]
#[Title('Create a Guest Form Draft')]
#[Description('Default tool for creating a new form unless the user explicitly asks to save it in an OpnForm account or workspace. Creates a temporary unpublished seven-day guest draft without login. This tool is data-only and never renders a widget. On success, call preview_form_draft exactly once with the returned draft_handle so the user sees one final interactive preview. Do not retry after success. Validation errors are safe to correct and retry; draft creation itself is not idempotent.')]
#[IsReadOnly(false)]
#[IsDestructive(false)]
#[IsOpenWorld(false)]
class CreateFormDraftTool extends GuestDraftMcpTool
{
    public function handle(
        Request $request,
        AgentFormDraftService $drafts,
        AgentFormDraftRateLimiter $rateLimiter,
    ): ResponseFactory {
        $validated = $request->validate([
            'definition' => ['required', 'array'],
        ]);

        $identifier = $request->user()
            ? 'user:'.$request->user()->getAuthIdentifier()
            : 'ip:'.request()->ip();
        $rateLimiter->hit($identifier);

        $created = $drafts->create($validated['definition']);

        return Response::structured([
            'draft_handle' => $created['token'],
            'draft' => $drafts->serialize($created['draft']),
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'definition' => $schema->object()
                ->description('Form definition following opnform://schemas/agent-form-definition/v1. The server normalizes defaults, aliases, IDs, and visibility.')
                ->required(),
        ];
    }

    public function outputSchema(JsonSchema $schema): array
    {
        return [
            'draft_handle' => $schema->string()
                ->description('Opaque reference for this temporary draft. Pass it unchanged to guest draft tools.')
                ->min(43)
                ->max(43)
                ->required(),
            'draft' => McpOutputSchema::draft($schema)->required(),
        ];
    }
}
