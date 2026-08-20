<?php

namespace App\Mcp\Tools;

use App\Service\Forms\McpFormManagementService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;
use Laravel\Mcp\Server\Tools\Annotations\IsOpenWorld;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('create_form_in_account')]
#[Title('Save a Form to an OpnForm Account')]
#[Description('Use this authenticated tool only when the user explicitly asks to save a new form in their connected OpnForm account or a workspace. Do not use it for a generic request to create or preview a new form; use create_form_draft without login instead. Saves the canonical definition as an unpublished draft in an accessible writable workspace. If the account has exactly one workspace, workspace_id may be omitted.')]
#[IsReadOnly(false)]
#[IsDestructive(false)]
#[IsOpenWorld(false)]
class CreateFormTool extends AuthenticatedMcpTool
{
    public function handle(Request $request, McpFormManagementService $forms): ResponseFactory
    {
        $validated = $request->validate([
            'workspace_id' => ['nullable', 'integer', 'min:1'],
            'definition' => ['required', 'array'],
        ]);

        return Response::structured($forms->create(
            $this->user($request),
            $validated['workspace_id'] ?? null,
            $validated['definition'],
        ));
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'workspace_id' => $schema->integer()->description('Required only when the account has multiple workspaces.')->min(1),
            'definition' => $schema->object()->description('Canonical OpnForm agent form definition v1.')->required(),
        ];
    }
}
