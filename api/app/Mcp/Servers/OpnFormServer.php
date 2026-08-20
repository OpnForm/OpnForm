<?php

namespace App\Mcp\Servers;

use App\Mcp\Apps\FormDraftPreviewApp;
use App\Mcp\Methods\CallTool;
use App\Mcp\Resources\FormDefinitionSchemaResource;
use App\Mcp\Resources\FormFieldCatalogResource;
use App\Mcp\Tools\CreateFormDraftTool;
use App\Mcp\Tools\GetFormDraftTool;
use App\Mcp\Tools\GetAccountContextTool;
use App\Mcp\Tools\ListWorkspacesTool;
use App\Mcp\Tools\GetWorkspaceTool;
use App\Mcp\Tools\ListFormsTool;
use App\Mcp\Tools\GetFormTool;
use App\Mcp\Tools\CreateFormTool;
use App\Mcp\Tools\UpdateFormTool;
use App\Mcp\Tools\PublishFormTool;
use App\Mcp\Tools\TrashFormTool;
use App\Mcp\Tools\ListSubmissionsTool;
use App\Mcp\Tools\GetSubmissionTool;
use App\Mcp\Tools\GetSubmissionStatsTool;
use App\Mcp\Tools\ExportSubmissionsTool;
use App\Mcp\Tools\GetSubmissionExportTool;
use App\Mcp\Tools\PatchFormDraftTool;
use App\Mcp\Tools\PreviewFormDraftTool;
use App\Mcp\Tools\OpenFormDraftInEditorTool;
use App\Mcp\Tools\ValidateFormDefinitionTool;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Attributes\Instructions;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Version;

#[Name('OpnForm')]
#[Version('1.0.0')]
#[Instructions('Use OpnForm MCP tools directly. Never invoke Codex or ChatGPT recursively, run `codex exec`, use shell or raw HTTP, inspect repositories or caches, or switch connectors. If the tools required for the active workflow are unexpectedly missing, stop and ask the user to start a new conversation with OpnForm selected before the first message. All account, form, and submission tools require OAuth. Enabling the plugin is not OAuth authentication. In local Codex, start a new conversation after OAuth so the MCP client loads the stored credential. When guest draft tools are available, they need no login. When they are unavailable, authenticate and use create_form to save an unpublished draft in a writable workspace. If an account tool challenges, ask the user to authenticate the OpnForm MCP server in the host; do not repeatedly retry from the guest conversation. Read the schema and field catalog before generating a form or changing its presentation, fields, layout, or media, then validate before saving. Focused presentation creates one step per block automatically: use full-width blocks, no page breaks or standalone media blocks, and attach optional media through the block image property. Use only durable public HTTPS asset URLs, never localhost or temporary tunnel URLs.')]
class OpnFormServer extends Server
{
    public int $maxPaginationLength = 100;

    public int $defaultPaginationLength = 50;

    protected array $tools = [
        ValidateFormDefinitionTool::class,
        CreateFormDraftTool::class,
        GetFormDraftTool::class,
        PatchFormDraftTool::class,
        PreviewFormDraftTool::class,
        OpenFormDraftInEditorTool::class,
        GetAccountContextTool::class,
        ListWorkspacesTool::class,
        GetWorkspaceTool::class,
        ListFormsTool::class,
        GetFormTool::class,
        CreateFormTool::class,
        UpdateFormTool::class,
        PublishFormTool::class,
        TrashFormTool::class,
        ListSubmissionsTool::class,
        GetSubmissionTool::class,
        GetSubmissionStatsTool::class,
        ExportSubmissionsTool::class,
        GetSubmissionExportTool::class,
    ];

    protected array $resources = [
        FormDefinitionSchemaResource::class,
        FormFieldCatalogResource::class,
        FormDraftPreviewApp::class,
    ];

    protected function boot(): void
    {
        $this->addMethod('tools/call', CallTool::class);
    }
}
