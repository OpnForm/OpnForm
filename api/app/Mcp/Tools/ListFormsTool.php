<?php

namespace App\Mcp\Tools;

use App\Models\Forms\Form;
use App\Service\Forms\McpFormManagementService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Validation\Rule;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;
use Laravel\Mcp\Server\Tools\Annotations\IsOpenWorld;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('list_forms')]
#[Description('List forms accessible to the connected account. Filter by workspace, title, or visibility; results are paginated and exclude trashed forms.')]
#[IsReadOnly]
#[IsDestructive(false)]
#[IsOpenWorld(false)]
class ListFormsTool extends AuthenticatedMcpTool
{
    public function handle(Request $request, McpFormManagementService $forms): ResponseFactory
    {
        $validated = $request->validate([
            'workspace_id' => ['nullable', 'integer', 'min:1'],
            'search' => ['nullable', 'string', 'max:255'],
            'visibility' => ['nullable', Rule::in(Form::VISIBILITY)],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        return Response::structured($forms->listForms(
            $this->user($request),
            $validated['workspace_id'] ?? null,
            $validated['search'] ?? null,
            $validated['visibility'] ?? null,
            $validated['page'] ?? 1,
            $validated['per_page'] ?? 20,
        ));
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'workspace_id' => $schema->integer()->min(1),
            'search' => $schema->string()->max(255),
            'visibility' => $schema->string()->enum(Form::VISIBILITY),
            'page' => $schema->integer()->min(1)->default(1),
            'per_page' => $schema->integer()->min(1)->max(100)->default(20),
        ];
    }
}
