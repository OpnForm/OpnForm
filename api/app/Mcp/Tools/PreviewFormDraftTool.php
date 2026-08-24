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
#[Description('Use this when an existing guest draft preview needs to be shown or re-rendered without changing the draft. Create_form_draft and patch_form_draft already render their previews automatically. The returned browser preview is valid for one hour. This read-only tool does not create an editor link. Call open_form_draft_in_editor only when the user chooses to continue editing in OpnForm.')]
#[RendersApp(resource: FormDraftPreviewApp::class)]
#[IsReadOnly]
#[IsDestructive(false)]
#[IsOpenWorld(false)]
class PreviewFormDraftTool extends GuestDraftMcpTool
{
    public function handle(Request $request, AgentFormDraftService $drafts): ResponseFactory
    {
        $validated = $request->validate([
            'draft_handle' => ['required', 'string', 'size:43'],
        ]);
        $draft = $drafts->get($validated['draft_handle']);

        return Response::structured([
            'draft_handle' => $validated['draft_handle'],
            'draft' => $drafts->serialize($draft),
            'preview_url' => $drafts->previewUrl($draft),
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
            'draft_handle' => $schema->string()->min(43)->max(43)->required(),
            'draft' => McpOutputSchema::draft($schema)->required(),
            'preview_url' => $schema->string()->format('uri')->required(),
        ];
    }
}
