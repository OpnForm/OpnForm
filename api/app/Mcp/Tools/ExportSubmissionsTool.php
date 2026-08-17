<?php

namespace App\Mcp\Tools;

use App\Service\Forms\McpSubmissionService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tools\Annotations\IsOpenWorld;

#[Name('export_submissions')]
#[Description('Queue a private CSV export for all submissions in an accessible form, or up to 1,000 selected submission IDs. Poll get_submission_export for its temporary download URL.')]
#[IsOpenWorld(false)]
class ExportSubmissionsTool extends AuthenticatedMcpTool
{
    public function handle(Request $request, McpSubmissionService $submissions): ResponseFactory
    {
        $validated = $request->validate([
            'form_id' => ['required', 'integer', 'min:1'],
            'submission_ids' => ['nullable', 'array', 'max:1000'],
            'submission_ids.*' => ['required', 'integer', 'min:1', 'distinct'],
        ]);

        return Response::structured($submissions->startExport(
            $this->user($request),
            $validated['form_id'],
            $validated['submission_ids'] ?? [],
        ));
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'form_id' => $schema->integer()->min(1)->required(),
            'submission_ids' => $schema->array()
                ->description('Optional selected IDs. Omit to export all submissions.')
                ->items($schema->integer()->min(1))
                ->max(1000),
        ];
    }
}
