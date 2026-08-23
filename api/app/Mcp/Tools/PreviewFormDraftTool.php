<?php

namespace App\Mcp\Tools;

use App\Mcp\Apps\FormDraftPreviewApp;
use App\Mcp\Support\McpOutputSchema;
use App\Service\Forms\AgentFormDraftService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\RendersApp;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;
use Laravel\Mcp\Server\Tools\Annotations\IsOpenWorld;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('preview_form_draft')]
#[Description('Render the current guest draft, return a browser preview valid for one hour, and create a reusable editor link that remains valid for the seven-day guest draft lifetime. Call this tool again whenever a fresh preview link is needed.')]
#[RendersApp(resource: FormDraftPreviewApp::class)]
#[IsReadOnly(false)]
#[IsDestructive(false)]
#[IsOpenWorld(false)]
class PreviewFormDraftTool extends GuestDraftMcpTool
{
    public function handle(Request $request, AgentFormDraftService $drafts): ResponseFactory
    {
        $validated = $request->validate([
            'draft_token' => ['required', 'string', 'size:43'],
        ]);
        $draft = $drafts->get($validated['draft_token']);
        $handoff = $drafts->issueEditorHandoff($validated['draft_token']);

        return Response::structured([
            'draft' => $drafts->serialize($draft),
            'preview_url' => $drafts->previewUrl($draft),
            'editor_url' => $handoff['editor_url'],
            'editor_link_expires_at' => $handoff['expires_at'],
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
        return [
            'draft' => McpOutputSchema::draft($schema)->required(),
            'preview_url' => $schema->string()->format('uri')->required(),
            'editor_url' => $schema->string()->format('uri')->required(),
            'editor_link_expires_at' => $schema->string()->format('date-time')->required(),
        ];
    }
}
