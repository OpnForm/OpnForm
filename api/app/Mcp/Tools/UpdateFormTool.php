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

#[Name('update_form')]
#[Description('Replace the editable definition of an accessible form. This can remove existing fields, and changes to an already-public form are immediately public. Requires the revision value returned by get_form to prevent silent concurrent overwrites. This tool cannot change visibility or trash a form.')]
#[IsReadOnly(false)]
#[IsDestructive]
#[IsOpenWorld]
class UpdateFormTool extends AuthenticatedMcpTool
{
    public function handle(Request $request, McpFormManagementService $forms): ResponseFactory
    {
        $validated = $request->validate([
            'form_id' => ['required', 'integer', 'min:1'],
            'expected_revision' => ['required', 'string', 'size:64'],
            'definition' => ['required', 'array'],
        ]);

        return Response::structured($forms->update(
            $this->user($request),
            $validated['form_id'],
            $validated['expected_revision'],
            $validated['definition'],
        ));
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'form_id' => $schema->integer()->min(1)->required(),
            'expected_revision' => $schema->string()->description('Exact 64-character revision returned by get_form.')->min(64)->max(64)->required(),
            'definition' => $schema->object()->description('Complete canonical OpnForm agent form definition v1.')->required(),
        ];
    }

    public function outputSchema(JsonSchema $schema): array
    {
        return [
            'message' => $schema->string()->required(),
            'form' => McpOutputSchema::form($schema)->required(),
            'disabled_features' => $schema->union(['object', 'array'])->required(),
        ];
    }
}
