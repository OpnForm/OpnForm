<?php

namespace App\Mcp\Tools;

use App\Mcp\Support\McpOutputSchema;
use App\Models\Forms\Form;
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
                    ])->description('Top-level form values for set_form_values. Do not put properties here.'),
                    'block' => $schema->object([
                        'name' => $schema->string()->required(),
                        'type' => $schema->string()->enum(AgentFormFieldCatalog::types())->required(),
                        'content' => $schema->string()->description('For nf-text blocks: sanitized HTML, never Markdown.'),
                        'placeholder' => $schema->string()->nullable(),
                        'help' => $schema->string()->nullable(),
                        'required' => $schema->boolean(),
                        'width' => $schema->string()->enum(['full', '1/2', '1/3', '2/3', '1/4', '3/4']),
                    ])->description('Complete block for add_block. Use type nf-page-break only in classic mode.'),
                    'patch' => $schema->object([
                        'name' => $schema->string(),
                        'type' => $schema->string()->enum(AgentFormFieldCatalog::types()),
                        'content' => $schema->string()->description('For nf-text blocks: sanitized HTML, never Markdown.'),
                        'placeholder' => $schema->string()->nullable(),
                        'help' => $schema->string()->nullable(),
                        'required' => $schema->boolean(),
                        'width' => $schema->string()->enum(['full', '1/2', '1/3', '2/3', '1/4', '3/4']),
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

    public function outputSchema(JsonSchema $schema): array
    {
        return [
            'draft_handle' => $schema->string()->min(43)->max(43)->required(),
            'draft' => McpOutputSchema::draft($schema)->required(),
        ];
    }
}
