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
#[Description('Use this as the default creation tool whenever the user asks to create, build, or preview a new form without explicitly asking to save it in an OpnForm account or workspace. It works immediately without login; never request authentication or use create_form_in_account instead for this guest-first workflow. Creates a temporary unpublished draft that expires after seven days and returns an opaque draft handle for get_form_draft, patch_form_draft, and preview_form_draft.')]
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
            'next_steps' => [
                'Pass draft_handle unchanged to guest draft tools. Do not display this internal handle to the user.',
                'Use patch_form_draft with expected_version for changes.',
                'Render a preview before asking the user to claim or publish the form.',
            ],
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
            'next_steps' => $schema->array()->items($schema->string())->required(),
        ];
    }
}
