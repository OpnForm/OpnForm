<?php

namespace App\Mcp\Tools;

use App\Mcp\Support\McpOutputSchema;
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

#[Name('get_account_context')]
#[Description('Confirm the connected OpnForm account and summarize its accessible workspaces. Use this before account-scoped work when the current context is unclear.')]
#[IsReadOnly]
#[IsDestructive(false)]
#[IsOpenWorld(false)]
class GetAccountContextTool extends AuthenticatedMcpTool
{
    public function handle(Request $request, McpFormManagementService $forms): ResponseFactory
    {
        $user = $this->user($request);
        $workspaces = $forms->listWorkspaces($user);

        return Response::structured([
            'account' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ],
            'workspaces' => $workspaces,
            'workspace_selection_required' => count($workspaces) > 1,
        ]);
    }

    public function outputSchema(JsonSchema $schema): array
    {
        return [
            'account' => $schema->object([
                'id' => $schema->integer()->min(1)->required(),
                'name' => $schema->string()->required(),
                'email' => $schema->string()->format('email')->required(),
            ])->withoutAdditionalProperties()->required(),
            'workspaces' => $schema->array()->items(McpOutputSchema::workspace($schema))->required(),
            'workspace_selection_required' => $schema->boolean()->required(),
        ];
    }
}
