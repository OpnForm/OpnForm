<?php

namespace App\Mcp\Servers;

use App\Mcp\Resources\FieldTypesResource;
use App\Mcp\Tools\Forms\CreateFormTool;
use App\Mcp\Tools\Forms\DeleteFormTool;
use App\Mcp\Tools\Forms\DuplicateFormTool;
use App\Mcp\Tools\Forms\GetFormTool;
use App\Mcp\Tools\Forms\ListFormsTool;
use App\Mcp\Tools\Forms\UpdateFormTool;
use App\Mcp\Tools\Submissions\GetSubmissionTool;
use App\Mcp\Tools\Submissions\ListSubmissionsTool;
use App\Mcp\Tools\Workspaces\ListWorkspacesTool;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Attributes\Instructions;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Version;

#[Name('OpnForm')]
#[Version('1.0.0')]
#[Instructions('OpnForm MCP Server. Manage forms, view submissions, and build no-code forms programmatically. Without a Bearer token only create-form draft mode and field-types are available. With a token, use list-workspaces first for workspace context, create-form for draft generation, and update-form/delete-form/duplicate-form only for existing forms.')]
class OpnFormServer extends Server
{
    protected array $tools = [
        ListWorkspacesTool::class,
        ListFormsTool::class,
        GetFormTool::class,
        CreateFormTool::class,
        UpdateFormTool::class,
        DeleteFormTool::class,
        DuplicateFormTool::class,
        ListSubmissionsTool::class,
        GetSubmissionTool::class,
    ];

    protected array $resources = [
        FieldTypesResource::class,
    ];

    protected array $prompts = [];
}
