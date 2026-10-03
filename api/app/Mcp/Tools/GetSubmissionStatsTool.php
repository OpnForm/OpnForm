<?php

namespace App\Mcp\Tools;

use App\Mcp\Support\McpOutputSchema;
use App\Service\Forms\McpSubmissionService;
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

#[Name('get_submission_stats')]
#[Description('Analyze submissions for an accessible form using the same bounded field summaries available in OpnForm. Returns all-time views/completion overview plus a status/date-filtered summary.')]
#[IsReadOnly]
#[IsDestructive(false)]
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

    public function outputSchema(JsonSchema $schema): array
    {
        return [
            'form_id' => $schema->integer()->min(1)->required(),
            'filters' => $schema->object([
                'status' => $schema->string()->required(),
                'date_from' => $schema->string()->nullable()->required(),
                'date_to' => $schema->string()->nullable()->required(),
            ])->withoutAdditionalProperties()->required(),
            'overview' => $schema->object([
                'views' => $schema->integer()->min(0)->required(),
                'completed_submissions' => $schema->integer()->min(0)->required(),
                'partial_submissions' => $schema->integer()->min(0)->required(),
                'completion_rate' => $schema->number()->min(0)->required(),
            ])->withoutAdditionalProperties()->required(),
            'filtered_submissions' => $schema->integer()->min(0)->required(),
            'average_completion_seconds' => $schema->number()->min(0)->nullable()->required(),
            'field_summary' => McpOutputSchema::fieldSummary($schema)->required(),
        ];
    }
}
