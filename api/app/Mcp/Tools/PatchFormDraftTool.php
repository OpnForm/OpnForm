<?php

namespace App\Mcp\Tools;

use App\Mcp\Support\McpOutputSchema;
use App\Service\Forms\AgentFormDraftService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;
use Laravel\Mcp\Server\Tools\Annotations\IsOpenWorld;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('patch_form_draft')]
#[Description('Apply validated semantic operations to a private draft. Operations can replace or remove draft content. Requires expected_version, so concurrent agent or editor changes cannot be silently overwritten. Supported ops: set_form_values, add_block, update_block, remove_block, move_block. Before changing presentation_style, fields, layout, or media, read opnform://reference/form-fields/v1; focused mode derives one step per block and media belongs in the block image property.')]
#[IsReadOnly(false)]
#[IsDestructive]
#[IsOpenWorld(false)]
class PatchFormDraftTool extends GuestDraftMcpTool
{
    public function handle(Request $request, AgentFormDraftService $drafts): ResponseFactory
    {
        $validated = $request->validate([
            'draft_token' => ['required', 'string', 'size:43'],
            'expected_version' => ['required', 'integer', 'min:1'],
            'operations' => ['required', 'array', 'min:1', 'max:100'],
            'operations.*' => ['required', 'array'],
            'operations.*.op' => ['required', 'string'],
        ]);

        $draft = $drafts->patch(
            $validated['draft_token'],
            $validated['expected_version'],
            $validated['operations'],
        );

        return Response::structured([
            'draft' => $drafts->serialize($draft),
            'next_step' => 'Render or refresh the preview before presenting the result to the user.',
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'draft_token' => $schema->string()
                ->description('Private capability token returned by create_form_draft.')
                ->min(43)
                ->max(43)
                ->required(),
            'expected_version' => $schema->integer()
                ->description('Version returned by the most recent create, get, or patch call.')
                ->min(1)
                ->required(),
            'operations' => $schema->array()
                ->description('Ordered semantic patch operations. Select blocks by block_id when possible; index is supported. See the tool description and schema resource for operation fields.')
                ->items($schema->object([
                    'op' => $schema->string()
                        ->enum(['set_form_values', 'add_block', 'update_block', 'remove_block', 'move_block'])
                        ->required(),
                    'values' => $schema->object(),
                    'block' => $schema->object(),
                    'patch' => $schema->object(),
                    'block_id' => $schema->string(),
                    'index' => $schema->integer()->min(0),
                    'to_index' => $schema->integer()->min(0),
                ]))
                ->min(1)
                ->max(100)
                ->required(),
        ];
    }

    public function outputSchema(JsonSchema $schema): array
    {
        return [
            'draft' => McpOutputSchema::draft($schema)->required(),
            'next_step' => $schema->string()->required(),
        ];
    }
}
