<?php

namespace App\Mcp\Tools;

use App\Service\Forms\McpFormManagementService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tools\Annotations\IsOpenWorld;

#[Name('publish_form')]
#[Description('Publish an accessible writable form at its current revision. Call only after showing the result or preview and receiving explicit confirmation from the user.')]
#[IsOpenWorld]
class PublishFormTool extends AuthenticatedMcpTool
{
    public function handle(Request $request, McpFormManagementService $forms): ResponseFactory
    {
        $validated = $request->validate([
            'form_id' => ['required', 'integer', 'min:1'],
            'expected_revision' => ['required', 'string', 'size:64'],
            'confirm_publish' => ['required', 'boolean'],
        ]);

        return Response::structured($forms->publish(
            $this->user($request),
            $validated['form_id'],
            $validated['expected_revision'],
            $validated['confirm_publish'],
        ));
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'form_id' => $schema->integer()->min(1)->required(),
            'expected_revision' => $schema->string()->description('Exact 64-character revision returned by get_form.')->min(64)->max(64)->required(),
            'confirm_publish' => $schema->boolean()->description('True only after the user explicitly confirms publication.')->required(),
        ];
    }
}
