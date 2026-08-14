<?php

namespace App\Mcp\Servers;

use App\Mcp\Resources\FormDefinitionSchemaResource;
use App\Mcp\Resources\FormFieldCatalogResource;
use App\Mcp\Apps\FormDraftPreviewApp;
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
#[Instructions('Build and manage OpnForm forms. Guest-safe draft tools are available without authentication; account, form, and submission tools require OAuth. Read the schema and field catalog before generating a form definition, and validate definitions before saving them.')]
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
}
