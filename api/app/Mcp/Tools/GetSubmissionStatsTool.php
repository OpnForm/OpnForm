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

#[Name('get_submission_stats')]
#[Description('Analyze submissions for an accessible form using the same bounded field summaries available in OpnForm. Returns all-time views/completion overview plus a status/date-filtered summary.')]
#[IsReadOnly]
#[IsOpenWorld(false)]
class GetSubmissionStatsTool extends AuthenticatedMcpTool
{
    public function handle(Request $request, McpSubmissionService $submissions): ResponseFactory
    {
        $validated = $request->validate([
            'form_id' => ['required', 'integer', 'min:1'],
            'status' => ['nullable', Rule::in(['all', 'completed', 'partial'])],
            'date_from' => ['nullable', 'date', Rule::when($request->filled('date_to'), 'before_or_equal:date_to')],
            'date_to' => ['nullable', 'date', Rule::when($request->filled('date_from'), 'after_or_equal:date_from')],
        ]);

        return Response::structured($submissions->stats(
            $this->user($request),
            $validated['form_id'],
            $validated['status'] ?? 'completed',
            $validated['date_from'] ?? null,
            $validated['date_to'] ?? null,
        ));
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'form_id' => $schema->integer()->min(1)->required(),
            'status' => $schema->string()->enum(['all', 'completed', 'partial'])->default('completed'),
            'date_from' => $schema->string()->description('Inclusive ISO 8601 date.'),
            'date_to' => $schema->string()->description('Inclusive ISO 8601 date.'),
        ];
    }
}
