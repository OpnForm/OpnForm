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

#[Name('get_form')]
#[Description('Fetch an accessible form as the canonical agent form definition. Use its revision value when calling update_form, publish_form, or trash_form.')]
#[IsReadOnly]
#[IsDestructive(false)]
#[IsOpenWorld(false)]
class GetFormTool extends AuthenticatedMcpTool
{
    public function handle(Request $request, McpFormManagementService $forms): ResponseFactory
    {
        $validated = $request->validate(['form_id' => ['required', 'integer', 'min:1']]);
        $form = $forms->form($this->user($request), $validated['form_id']);

        return Response::structured(['form' => $forms->serializeForm($form)]);
    }

    public function schema(JsonSchema $schema): array
    {
        return ['form_id' => $schema->integer()->min(1)->required()];
    }

    public function outputSchema(JsonSchema $schema): array
    {
        return ['form' => McpOutputSchema::form($schema)->required()];
    }
}
