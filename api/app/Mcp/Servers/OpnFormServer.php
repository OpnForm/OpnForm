<?php

namespace App\Mcp\Servers;

use App\Mcp\Apps\FormDraftPreviewApp;
use App\Mcp\Apps\LegacyFormDraftPreviewApp;
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
#[Instructions('Treat a natural request to create a form as a guest workflow unless the user explicitly asks for account persistence. No login or preview wording is required. Read the form schema and field catalog, follow their authoring guidance, validate, then create or patch the draft. Create and patch are data-only: after either succeeds, call preview_form_draft exactly once so the turn contains one final interactive preview. Use sanitized HTML, never Markdown, in nf-text content. Every input name is a respondent-facing label in sentence case with spaces; technical identifiers belong only in id and may be omitted. Correct relevant quality_warnings before persistence. OAuth is only for saving or managing account forms, workspaces, or submissions. After a guest preview, briefly ask whether to modify or save. After an account form is saved as an unpublished draft, ask whether to publish and require explicit confirmation. Keep replies concise, use OpnForm MCP tools directly, preserve draft handles privately, and do not bypass validation, revisions, permissions, confirmations, plan rules, or rate limits.')]
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
        LegacyFormDraftPreviewApp::class,
    ];

    protected function boot(): void
    {
        $this->addMethod('tools/call', CallTool::class);
    }
}
