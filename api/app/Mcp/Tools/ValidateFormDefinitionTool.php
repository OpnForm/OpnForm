<?php

namespace App\Mcp\Tools;

use App\Service\Forms\AgentFormDefinition;
use App\Service\Forms\AgentFormQualityAnalyzer;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsOpenWorld;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('validate_form_definition')]
#[Description('Normalize and validate an OpnForm agent form definition without storing it. Returns the canonical definition with defaults, stable block IDs, sanitized content, normalized aliases, and non-blocking authoring-quality warnings.')]
#[IsReadOnly]
#[IsDestructive(false)]
#[IsIdempotent]
#[IsOpenWorld(false)]
class ValidateFormDefinitionTool extends GuestMcpTool
{
    public function handle(
        Request $request,
        AgentFormDefinition $formDefinition,
        AgentFormQualityAnalyzer $qualityAnalyzer,
    ): ResponseFactory {
        $validated = $request->validate([
            'definition' => ['required', 'array'],
        ]);

        $definition = $formDefinition->normalizeAndValidate($validated['definition']);

        return Response::structured([
            'valid' => true,
            'schema_version' => AgentFormDefinition::SCHEMA_VERSION,
            'definition' => $definition,
            'quality_warnings' => $qualityAnalyzer->analyze($definition),
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'definition' => $schema->object()
                ->description('Form definition following opnform://schemas/agent-form-definition/v1 and the authoring guidelines in opnform://reference/form-fields/v1. IDs and optional defaults may be omitted.')
                ->required(),
        ];
    }

    public function outputSchema(JsonSchema $schema): array
    {
        return [
            'valid' => $schema->boolean()->required(),
            'schema_version' => $schema->integer()->required(),
            'definition' => $schema->object()->required(),
            'quality_warnings' => $schema->array()
                ->items($schema->object([
                    'code' => $schema->string()->required(),
                    'message' => $schema->string()->required(),
                    'path' => $schema->string()->required(),
                ]))
                ->required(),
        ];
    }
}
