<?php

namespace App\Mcp\Tools;

use App\Service\Forms\McpSubmissionService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Validation\Rule;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tools\Annotations\IsOpenWorld;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('list_submissions')]
#[Description('List and search submissions for an accessible form. Search matches response values, not field names. Filter by completed/partial status and date; results are paginated.')]
#[IsReadOnly]
#[IsOpenWorld(false)]
class ListSubmissionsTool extends AuthenticatedMcpTool
{
    public function handle(Request $request, McpSubmissionService $submissions): ResponseFactory
    {
        $validated = $request->validate([
            'form_id' => ['required', 'integer', 'min:1'],
            'search' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', Rule::in(['all', 'completed', 'partial'])],
            'date_from' => ['nullable', 'date', Rule::when($request->filled('date_to'), 'before_or_equal:date_to')],
            'date_to' => ['nullable', 'date', Rule::when($request->filled('date_from'), 'after_or_equal:date_from')],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        return Response::structured($submissions->list(
            $this->user($request),
            $validated['form_id'],
            $validated['search'] ?? null,
            $validated['status'] ?? 'completed',
            $validated['date_from'] ?? null,
            $validated['date_to'] ?? null,
            $validated['page'] ?? 1,
            $validated['per_page'] ?? 50,
        ));
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'form_id' => $schema->integer()->min(1)->required(),
            'search' => $schema->string()->max(255),
            'status' => $schema->string()->enum(['all', 'completed', 'partial'])->default('completed'),
            'date_from' => $schema->string()->description('Inclusive ISO 8601 date.'),
            'date_to' => $schema->string()->description('Inclusive ISO 8601 date.'),
            'page' => $schema->integer()->min(1)->default(1),
            'per_page' => $schema->integer()->min(1)->max(100)->default(50),
        ];
    }
}
