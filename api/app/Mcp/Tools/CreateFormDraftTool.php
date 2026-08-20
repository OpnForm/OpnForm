<?php

namespace App\Mcp\Tools;

use App\Service\Forms\AgentFormDraftRateLimiter;
use App\Service\Forms\AgentFormDraftService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tools\Annotations\IsOpenWorld;

#[Name('create_form_draft')]
#[Description('Create a private server-side OpnForm draft that expires after seven days. Works without login. Returns a capability token once; keep it private and use it with get_form_draft and patch_form_draft. The form remains an unpublished draft.')]
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
            'draft_token' => $created['token'],
            'draft' => $drafts->serialize($created['draft']),
            'next_steps' => [
                'Keep draft_token private; it grants access to this draft.',
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
}
