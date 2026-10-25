<?php

namespace App\Mcp\Tools;

use App\Mcp\Support\McpOutputSchema;
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

#[Name('restore_form_draft_version')]
#[Description('Use only when the user asks to return to an earlier guest draft version. Fetch the latest draft first for expected_version. Copies the selected historical version into a new current version, preserving all intervening revisions. Does not save or publish an account form. Data-only: after success call preview_form_draft once. Do not retry after success; on a version conflict fetch current state before retrying.')]
#[IsReadOnly(false)]
#[IsDestructive]
#[IsOpenWorld(false)]
class RestoreFormDraftVersionTool extends GuestDraftMcpTool
{
    public function handle(Request $request, AgentFormDraftService $drafts): ResponseFactory
    {
        $validated = $request->validate([
            'draft_handle' => ['required', 'string', 'size:43'],
            'expected_version' => ['required', 'integer', 'min:1'],
            'version' => ['required', 'integer', 'min:1'],
        ]);
        $draft = $drafts->restore($validated['draft_handle'], $validated['expected_version'], $validated['version']);

        return Response::structured([
            'draft_handle' => $validated['draft_handle'],
            'draft' => $drafts->serialize($draft),
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'draft_handle' => $schema->string()->min(43)->max(43)->required(),
            'expected_version' => $schema->integer()->min(1)->description('Latest version returned by get_form_draft without a version parameter.')->required(),
            'version' => $schema->integer()->min(1)->description('Historical version requested by the user.')->required(),
        ];
    }

    public function outputSchema(JsonSchema $schema): array
    {
        return [
            'draft_handle' => $schema->string()->min(43)->max(43)->required(),
            'draft' => McpOutputSchema::draft($schema)->required(),
        ];
    }
}
