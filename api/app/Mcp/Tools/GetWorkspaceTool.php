<?php

namespace App\Mcp\Tools;

use App\Service\Forms\McpFormManagementService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;
use Laravel\Mcp\Server\Tools\Annotations\IsOpenWorld;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('get_workspace')]
#[Description('Read one accessible OpnForm workspace and the connected account role. This integration never changes workspace settings or members.')]
#[IsReadOnly]
#[IsDestructive(false)]
#[IsOpenWorld(false)]
class GetWorkspaceTool extends AuthenticatedMcpTool
{
    public function handle(Request $request, McpFormManagementService $forms): ResponseFactory
    {
        $validated = $request->validate(['workspace_id' => ['required', 'integer', 'min:1']]);

        return Response::structured([
            'workspace' => $forms->serializeWorkspace($forms->workspace($this->user($request), $validated['workspace_id'])),
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return ['workspace_id' => $schema->integer()->min(1)->required()];
    }
}
