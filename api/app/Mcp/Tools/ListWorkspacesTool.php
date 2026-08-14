<?php

namespace App\Mcp\Tools;

use App\Service\Forms\McpFormManagementService;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tools\Annotations\IsOpenWorld;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('list_workspaces')]
#[Description('List workspaces available to the connected account, including its role and whether forms can be changed. Workspace management is intentionally not available.')]
#[IsReadOnly]
#[IsOpenWorld(false)]
class ListWorkspacesTool extends AuthenticatedMcpTool
{
    public function handle(Request $request, McpFormManagementService $forms): ResponseFactory
    {
        return Response::structured([
            'workspaces' => $forms->listWorkspaces($this->user($request)),
        ]);
    }
}
