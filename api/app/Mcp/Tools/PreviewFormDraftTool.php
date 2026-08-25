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
#[Description('Render the final interactive preview for an existing guest draft without changing it. Call exactly once after a successful create_form_draft or patch_form_draft, and use it again only when the user explicitly asks to refresh an existing preview. The signed preview remains valid until the seven-day draft expires. This tool is read-only, safe to retry after an error, and does not create an editor link.')]
#[RendersApp(resource: FormDraftPreviewApp::class)]
#[IsReadOnly]
#[IsDestructive(false)]
#[IsOpenWorld(false)]
class PreviewFormDraftTool extends GuestDraftMcpTool
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $this->setMeta('openai/outputTemplate', FormDraftPreviewApp::URI);

        return parent::toArray();
    }

    public function handle(Request $request, AgentFormDraftService $drafts): ResponseFactory
    {
        $validated = $request->validate([
            'draft_handle' => ['required', 'string', 'size:43'],
        ]);
        $draft = $drafts->get($validated['draft_handle']);

        return Response::structured([
            'draft_handle' => $validated['draft_handle'],
            'draft' => $drafts->serialize($draft),
        ])->withMeta('preview_url', $drafts->previewUrl($draft));
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
            'draft_handle' => $schema->string()->min(43)->max(43)->required(),
            'draft' => McpOutputSchema::draft($schema)->required(),
        ];
    }
}
