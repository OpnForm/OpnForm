<?php

namespace App\Mcp\Tools;

use App\Mcp\Support\McpOutputSchema;
use App\Models\Forms\Form;
use App\Rules\PropertyValidators\LogicPropertyValidator;
use App\Service\Forms\AgentFormFieldCatalog;
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
#[Description('Apply semantic operations to the current guest draft. This tool is data-only and never renders a widget. Requires the latest expected_version and rejects conflicts without overwriting newer work. On success, call preview_form_draft exactly once with the returned draft_handle so the user sees one updated preview. Do not retry after success. Correct validation errors and retry with the same current version; after a version conflict, fetch the draft before retrying. Supported ops: set_form_values, add_block, update_block, remove_block, move_block.')]
#[IsReadOnly(false)]
#[IsDestructive]
#[IsOpenWorld(false)]
class PatchFormDraftTool extends GuestDraftMcpTool
{
    public function handle(Request $request, AgentFormDraftService $drafts): ResponseFactory
    {
        $validated = $request->validate([
            'draft_handle' => ['required', 'string', 'size:43'],
            'expected_version' => ['required', 'integer', 'min:1'],
            'operations' => ['required', 'array', 'min:1', 'max:100'],
            'operations.*' => ['required', 'array'],
            'operations.*.op' => ['required', 'string'],
        ]);

        $draft = $drafts->patch(
            $validated['draft_handle'],
            $validated['expected_version'],
            $validated['operations'],
        );

        return Response::structured([
            'draft_handle' => $validated['draft_handle'],
            'draft' => $drafts->serialize($draft),
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'draft_handle' => $schema->string()
                ->description('Opaque reference returned by create_form_draft. Pass it unchanged.')
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
                    'values' => $schema->object([
                        'title' => $schema->string()->description('Form title.'),
                        'presentation_style' => $schema->string()->enum(Form::PRESENTATION_STYLES),
                        'width' => $schema->string()->enum(Form::WIDTHS),
                        'size' => $schema->string()->enum(Form::SIZES),
                        'theme' => $schema->string()->enum(Form::THEMES),
                        'border_radius' => $schema->string()->enum(Form::BORDER_RADIUS),
                        'color' => $schema->string()->description('Form accent color, preferably a hex color.'),
                        'uppercase_labels' => $schema->boolean(),
                        'show_progress_bar' => $schema->boolean(),
                        'submit_button_text' => $schema->string(),
                        'settings' => $schema->object(),
                        'computed_variables' => $schema->array()
                            ->items($schema->object([
                                'id' => $schema->string()->description('Unique technical ID beginning with cv_.')->required(),
                                'name' => $schema->string()->description('Unique human-readable variable name.')->required(),
                                'formula' => $schema->string()->description('Formula using brace references such as {budget} * 1.2.')->required(),
                                'result_type' => $schema->string()->enum(['number', 'text', 'auto']),
                            ]))
                            ->max(500)
                            ->description('Complete replacement list of computed variables. Use [] to remove all variables.'),
                    ])->description('Top-level form values for set_form_values, including computed_variables. Do not put properties here.'),
                    'block' => $schema->object([
                        'name' => $schema->string()->required(),
                        'type' => $schema->string()->enum(AgentFormFieldCatalog::types())->required(),
                        'content' => $schema->string()->description('For nf-text blocks: sanitized HTML, never Markdown.'),
                        'placeholder' => $schema->string()->nullable(),
                        'help' => $schema->string()->nullable(),
                        'hidden' => $schema->boolean(),
                        'required' => $schema->boolean(),
                        'width' => $schema->string()->enum(['full', '1/2', '1/3', '2/3', '1/4', '3/4']),
                        'logic' => $this->logicSchema($schema)->nullable(),
                    ])->description('Complete block for add_block. Use type nf-page-break only in classic mode.'),
                    'patch' => $schema->object([
                        'name' => $schema->string(),
                        'type' => $schema->string()->enum(AgentFormFieldCatalog::types()),
                        'content' => $schema->string()->description('For nf-text blocks: sanitized HTML, never Markdown.'),
                        'placeholder' => $schema->string()->nullable(),
                        'help' => $schema->string()->nullable(),
                        'hidden' => $schema->boolean(),
                        'required' => $schema->boolean(),
                        'width' => $schema->string()->enum(['full', '1/2', '1/3', '2/3', '1/4', '3/4']),
                        'logic' => $this->logicSchema($schema)->nullable(),
                    ])->description('Fields to merge into the selected block for update_block. Block id cannot change.'),
                    'block_id' => $schema->string()->description('Stable block id from the latest draft.'),
                    'index' => $schema->integer()->description('Zero-based block index. For add_block, insertion at the final count is allowed.')->min(0),
                    'to_index' => $schema->integer()->description('Zero-based destination for move_block.')->min(0),
                ]))
                ->min(1)
                ->max(100)
                ->required(),
        ];
    }

    private function logicSchema(JsonSchema $schema): mixed
    {
        return $schema->object([
            'conditions' => $schema->object([
                'operatorIdentifier' => $schema->string()->enum(['and', 'or']),
                'children' => $schema->array()->items($schema->object()),
                'identifier' => $schema->string()->description('Referenced field or computed-variable ID.'),
                'value' => $schema->object([
                    'operator' => $schema->string()->enum(AgentFormFieldCatalog::logicOperators()),
                    'property_meta' => $schema->object([
                        'id' => $schema->string()->description('Referenced field or computed-variable ID.'),
                        'type' => $schema->string()
                            ->enum(array_keys(AgentFormFieldCatalog::logicOperatorsByReferenceType()))
                            ->description('Use computed for a computed-variable reference.'),
                    ]),
                    'value' => $schema->union(['string', 'number', 'boolean', 'object', 'array', 'null'])
                        ->description('Comparison value. Omit it for operators that do not take a value, such as is_empty or is_checked.'),
                ]),
            ])->description('A condition leaf or a nested and/or group. See opnform://schemas/agent-form-definition/v1 for the recursive shape.'),
            'actions' => $schema->array()
                ->items($schema->string()->enum(LogicPropertyValidator::ACTIONS_VALUES))
                ->min(1),
        ])->description('Conditional behavior for the target block. Empty or safely invalid logic is removed during normalization.');
    }

    public function outputSchema(JsonSchema $schema): array
    {
        return [
            'draft_handle' => $schema->string()->min(43)->max(43)->required(),
            'draft' => McpOutputSchema::draft($schema)->required(),
        ];
    }
}
