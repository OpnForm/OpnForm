<?php

namespace App\Mcp\Tools;

use App\Mcp\Support\McpOutputSchema;
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

#[Name('get_submission')]
#[Description('Read one submission from an accessible form, with response values labeled by form field. This tool never changes the submission.')]
#[IsReadOnly]
#[IsDestructive(false)]
#[IsOpenWorld(false)]
class GetSubmissionTool extends AuthenticatedMcpTool
{
    public function handle(Request $request, McpSubmissionService $submissions): ResponseFactory
    {
        $validated = $request->validate([
            'form_id' => ['required', 'integer', 'min:1'],
            'submission_id' => ['required', 'integer', 'min:1'],
        ]);

        return Response::structured([
            'submission' => $submissions->get(
                $this->user($request),
                $validated['form_id'],
                $validated['submission_id'],
            ),
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'form_id' => $schema->integer()->min(1)->required(),
            'submission_id' => $schema->integer()->min(1)->required(),
        ];
    }

    public function outputSchema(JsonSchema $schema): array
    {
        return ['submission' => McpOutputSchema::submission($schema)->required()];
    }
}
