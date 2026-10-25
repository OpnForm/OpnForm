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
#[Description('Render an interactive preview for an existing guest draft without changing it. Call exactly once after a successful create_form_draft, patch_form_draft, or restore_form_draft_version. Also use it when the user asks to refresh, redisplay, or compare a version: pass version for a historical preview, or omit it for latest. Comparing history does not restore or mutate the draft. After success, briefly summarize the result and ask one question offering two choices: modify the draft again or save it to the user\'s OpnForm account. Do not request OAuth unless the user chooses save. Each preview stays pinned to its displayed version until the seven-day draft expires. This tool is read-only, safe to retry after an error, and does not create an editor link.')]
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
            'version' => ['sometimes', 'integer', 'min:1'],
        ]);
        $draft = $drafts->get($validated['draft_handle'], $validated['version'] ?? null);

        return Response::structured([
            'draft_handle' => $validated['draft_handle'],
            'draft' => $drafts->serialize($draft),
            'next_step' => 'Briefly summarize the preview, then ask one question offering two choices: modify the draft again or save it to the user\'s OpnForm account. Do not request OAuth unless the user chooses save.',
        ])->withMeta('preview_url', $drafts->previewUrl($draft));
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'version' => $schema->integer()->min(1)->description('Optional historical version to compare. Each preview is pinned to the version returned and never follows later edits. Omit for latest.'),
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
            'next_step' => $schema->string()->required(),
        ];
    }
}
