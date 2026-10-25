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

#[Name('get_form_draft')]
#[Description('Fetch a private guest draft. Omit version for the current canonical definition, especially after a conflict. Provide version to inspect an earlier saved revision without changing the current draft.')]
#[IsReadOnly]
#[IsDestructive(false)]
#[IsOpenWorld(false)]
class GetFormDraftTool extends GuestDraftMcpTool
{
    public function handle(Request $request, AgentFormDraftService $drafts): ResponseFactory
    {
        $validated = $request->validate([
            'draft_handle' => ['required', 'string', 'size:43'],
            'version' => ['sometimes', 'integer', 'min:1'],
        ]);

        return Response::structured([
            'draft' => $drafts->serialize($drafts->get($validated['draft_handle'], $validated['version'] ?? null)),
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'version' => $schema->integer()->min(1)->description('Optional historical version. Omit to read the latest version.'),
            'draft_handle' => $schema->string()
                ->description('Opaque reference returned by create_form_draft. Pass it unchanged.')
                ->min(43)
                ->max(43)
                ->required(),
        ];
    }

    public function outputSchema(JsonSchema $schema): array
    {
        return ['draft' => McpOutputSchema::draft($schema)->required()];
    }
}
