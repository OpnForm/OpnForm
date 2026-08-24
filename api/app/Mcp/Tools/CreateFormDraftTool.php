<?php

namespace App\Mcp\Tools;

use App\Mcp\Apps\FormDraftPreviewApp;
use App\Mcp\Support\McpOutputSchema;
use App\Service\Forms\AgentFormDraftRateLimiter;
use App\Service\Forms\AgentFormDraftService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\RendersApp;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;
use Laravel\Mcp\Server\Tools\Annotations\IsOpenWorld;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('create_form_draft')]
#[Title('Create a Guest Form Draft')]
#[Description('Use this as the default creation tool when the user asks to create or build a new form without explicitly asking to save it in an OpnForm account or workspace. A natural request such as "create a contact form" is sufficient; the user does not need to mention guest mode, login, a draft, or a preview. It works immediately without login and returns a temporary unpublished seven-day draft with its interactive preview; never request authentication or use create_form_in_account for this guest-first workflow.')]
#[RendersApp(resource: FormDraftPreviewApp::class)]
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
            'preview_url' => $drafts->previewUrl($created['draft']),
            'next_steps' => [
                'Pass draft_handle unchanged to guest draft tools. Do not display this internal handle to the user.',
                'Use patch_form_draft with expected_version for changes.',
                'The interactive preview is included in this result; present it before any text summary.',
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
            'preview_url' => $schema->string()->format('uri')->required(),
            'next_steps' => $schema->array()->items($schema->string())->required(),
        ];
    }
}
