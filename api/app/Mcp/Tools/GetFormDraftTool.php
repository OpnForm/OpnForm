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
#[Description('Fetch the current canonical definition and version of a private guest draft. Use this after a version conflict or whenever the current state is uncertain.')]
#[IsReadOnly]
#[IsDestructive(false)]
#[IsOpenWorld(false)]
class GetFormDraftTool extends GuestDraftMcpTool
{
    public function handle(Request $request, AgentFormDraftService $drafts): ResponseFactory
    {
        $validated = $request->validate([
            'draft_token' => ['required', 'string', 'size:43'],
        ]);

        return Response::structured([
            'draft' => $drafts->serialize($drafts->get($validated['draft_token'])),
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'draft_token' => $schema->string()
                ->description('Private capability token returned by create_form_draft.')
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
