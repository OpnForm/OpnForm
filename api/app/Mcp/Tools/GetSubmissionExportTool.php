<?php

namespace App\Mcp\Tools;

use App\Service\Forms\McpSubmissionService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;
use Laravel\Mcp\Server\Tools\Annotations\IsOpenWorld;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('get_submission_export')]
#[Description('Check a queued submission export. When completed, returns a temporary private CSV download URL and its expiration time.')]
#[IsReadOnly]
#[IsDestructive(false)]
#[IsOpenWorld(false)]
class GetSubmissionExportTool extends AuthenticatedMcpTool
{
    public function handle(Request $request, McpSubmissionService $submissions): ResponseFactory
    {
        $validated = $request->validate([
            'form_id' => ['required', 'integer', 'min:1'],
            'job_id' => ['required', 'uuid'],
        ]);

        return Response::structured($submissions->exportStatus(
            $this->user($request),
            $validated['form_id'],
            $validated['job_id'],
        ));
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'form_id' => $schema->integer()->min(1)->required(),
            'job_id' => $schema->string()->description('Export job UUID returned by export_submissions.')->required(),
        ];
    }
}
