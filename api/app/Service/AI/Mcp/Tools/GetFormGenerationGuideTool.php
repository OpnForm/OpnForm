<?php

namespace App\Service\AI\Mcp\Tools;

use App\Service\AI\Mcp\GuideTokenService;
use App\Service\AI\Prompts\Form\FormStateSchemaFactory;
use App\Service\AI\Prompts\Form\PresentationRules;

class GetFormGenerationGuideTool extends McpTool
{
    public function __construct(
        private readonly GuideTokenService $guideTokenService
    ) {
    }

    public function name(): string
    {
        return 'get_form_generation_guide';
    }

    public function description(): string
    {
        return 'Get OpnForm form-state schema, constraints, and mandatory tool workflow (including handoff before save/share).';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'presentation_style' => [
                    'type' => 'string',
                    'enum' => [PresentationRules::MODE_CLASSIC, PresentationRules::MODE_FOCUSED],
                    'description' => 'Optional presentation style (classic or focused). Defaults to classic.',
                ],
            ],
        ];
    }

    public function execute(array $arguments): array
    {
        $requestedStyle = $arguments['presentation_style'] ?? PresentationRules::MODE_CLASSIC;
        $presentationStyle = \in_array($requestedStyle, [PresentationRules::MODE_CLASSIC, PresentationRules::MODE_FOCUSED], true)
            ? $requestedStyle
            : PresentationRules::MODE_CLASSIC;

        $rules = PresentationRules::buildContext(['presentation_style' => $presentationStyle]);

        return [
            'presentation_style' => $presentationStyle,
            'constraints_text' => $rules['constraintsText'],
            'allowed_field_types' => $rules['allowedFieldTypes'],
            'required_top_level_keys' => ['title', 'properties', 're_fillable', 'use_captcha', 'redirect_url', 'submitted_text', 'uppercase_labels', 'submit_button_text', 're_fill_button_text', 'color'],
            'form_state_schema' => FormStateSchemaFactory::buildFullFormSchema(),
            'workflow' => [
                '1_call_get_form_generation_guide' => 'Fetch schema/constraints first.',
                '2_call_create_draft' => 'Create with form_state and required guide_token. gpt_chat_id is returned by server.',
                '3_call_patch_draft' => 'Preferred for edits: partially update config/properties (requires guide_token).',
                '4_call_update_draft' => 'Fallback: replace full form_state when patch operations are insufficient.',
                '5_call_handoff_draft' => 'MANDATORY when user wants to save, use, publish, or share the form. Return takeover_url.',
            ],
            'assistant_rules' => [
                'When user asks to save/use/publish/share/export the form, call handoff_draft first and return only takeover_url for finalization in OpnForm.',
                'Do not claim the form is saved in an account from MCP tools alone. Saving and sharing are completed in OpnForm after handoff.',
                'Prefer patch_draft for edits. Do not call update_draft unless full replacement is strictly required.',
                'After every successful create_draft, patch_draft, or update_draft call, reply immediately using that tool result (it already carries widget preview metadata).',
                'Use render_draft_preview only as recovery if widget preview failed to render in a prior turn.',
                'Do not print preview URLs in chat responses. Use the embedded preview widget instead.',
            ],
            'patch_draft_operations' => [
                'set_form_config' => 'values object for partial top-level form config updates (cannot edit properties).',
                'add_property' => 'Insert field at index or append when index omitted.',
                'update_property' => 'Patch existing field by index or field_id.',
                'remove_property' => 'Remove field by index or field_id.',
                'move_property' => 'Move field from from_index/from_field_id to to_index.',
            ],
            'update_draft_policy' => [
                'status' => 'fallback_only',
                'use_when' => [
                    'bulk_regeneration',
                    'schema_reset',
                    'cannot_express_with_patch',
                ],
            ],
            'privacy_rules' => [
                'Never include user personal data (email, phone, address, account identifiers) in tool arguments.',
                'Do not prefill fields with user-specific values. Keep placeholders generic.',
            ],
            'schema_version' => FormStateSchemaFactory::SCHEMA_VERSION,
            'guide_token_ttl_seconds' => 900,
            'guide_token' => $this->guideTokenService->issue($presentationStyle),
        ];
    }

    protected function isReadOnly(): bool
    {
        return true;
    }
}
