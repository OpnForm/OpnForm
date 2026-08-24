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
#[Description('Create a reusable OpnForm editor link only after the user chooses to continue editing the guest draft in OpnForm. The link remains valid until the seven-day draft expires.')]
#[IsReadOnly(false)]
#[IsDestructive(false)]
#[IsOpenWorld(false)]
class OpenFormDraftInEditorTool extends GuestDraftMcpTool
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $tool = parent::toArray();

        return [
            ...$tool,
            '_meta' => [
                ...($tool['_meta'] ?? []),
                'ui' => ['visibility' => ['model', 'app']],
            ],
        ];
    }

    public function handle(Request $request, AgentFormDraftService $drafts): ResponseFactory
    {
        $validated = $request->validate([
            'draft_handle' => ['required', 'string', 'size:43'],
        ]);

        $handoff = $drafts->issueEditorHandoff($validated['draft_handle']);

        return Response::structured([
            'editor_url' => $handoff['editor_url'],
            'expires_at' => $handoff['expires_at'],
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'draft_handle' => $schema->string()
                ->description('Opaque reference returned by create_form_draft. Pass it unchanged.')
                ->min(43)
                ->max(43)
                ->required(),
        ];
    }

    public function outputSchema(JsonSchema $schema): array
    {
        return [
            'editor_url' => $schema->string()->format('uri')->required(),
            'expires_at' => $schema->string()->format('date-time')->required(),
        ];
    }
}
