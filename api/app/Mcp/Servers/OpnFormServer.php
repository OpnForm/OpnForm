<?php

namespace App\Mcp\Servers;

use App\Mcp\Resources\FormDefinitionSchemaResource;
use App\Mcp\Resources\FormFieldCatalogResource;
use App\Mcp\Tools\ValidateFormDefinitionTool;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Attributes\Instructions;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Version;

#[Name('OpnForm')]
#[Version('1.0.0')]
#[Instructions('Build and manage OpnForm forms. Guest-safe draft tools are available without authentication; account, form, and submission tools require OAuth. Read the schema and field catalog before generating a form definition, and validate definitions before saving them.')]
class OpnFormServer extends Server
{
    protected array $tools = [
        ValidateFormDefinitionTool::class,
    ];

    protected array $resources = [
        FormDefinitionSchemaResource::class,
        FormFieldCatalogResource::class,
    ];
}
