<?php

namespace App\Mcp\Tools;

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

#[Name('trash_form')]
#[Description('Move an accessible writable form at its current revision to soft-delete trash. A public form becomes unavailable. Call only after explicit user confirmation. Restore and permanent deletion are intentionally not exposed.')]
#[IsReadOnly(false)]
#[IsDestructive]
#[IsOpenWorld]
class TrashFormTool extends AuthenticatedMcpTool
{
    public function handle(Request $request, McpFormManagementService $forms): ResponseFactory
    {
        $validated = $request->validate([
            'form_id' => ['required', 'integer', 'min:1'],
            'expected_revision' => ['required', 'string', 'size:64'],
            'confirm_trash' => ['required', 'boolean'],
        ]);

        return Response::structured($forms->trash(
            $this->user($request),
            $validated['form_id'],
            $validated['expected_revision'],
            $validated['confirm_trash'],
        ));
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'form_id' => $schema->integer()->min(1)->required(),
            'expected_revision' => $schema->string()->description('Exact 64-character revision returned by get_form.')->min(64)->max(64)->required(),
            'confirm_trash' => $schema->boolean()->description('True only after the user explicitly confirms moving the form to trash.')->required(),
        ];
    }
}
