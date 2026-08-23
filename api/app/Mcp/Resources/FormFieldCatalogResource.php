<?php

namespace App\Mcp\Resources;

use App\Service\Forms\AgentFormFieldCatalog;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\MimeType;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Uri;
use Laravel\Mcp\Server\Resource;

#[Name('form_field_catalog')]
#[Description('Canonical OpnForm field, presentation, media, layout, and authoring-quality reference, including focused-mode guidance, aliases, and plan behavior.')]
#[Uri('opnform://reference/form-fields/v1')]
#[MimeType('application/json')]
class FormFieldCatalogResource extends Resource
{
    public function handle(Request $request): Response
    {
        return Response::json(AgentFormFieldCatalog::reference());
    }
}
