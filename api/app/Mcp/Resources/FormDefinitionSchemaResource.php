<?php

namespace App\Mcp\Resources;

use App\Service\Forms\AgentFormDefinition;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\MimeType;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Uri;
use Laravel\Mcp\Server\Resource;

#[Name('form_definition_schema')]
#[Description('Versioned JSON Schema for OpnForm agent form definitions.')]
#[Uri('opnform://schemas/agent-form-definition/v1')]
#[MimeType('application/schema+json')]
class FormDefinitionSchemaResource extends Resource
{
    public function handle(Request $request, AgentFormDefinition $definition): Response
    {
        return Response::json($definition->jsonSchema());
    }
}
