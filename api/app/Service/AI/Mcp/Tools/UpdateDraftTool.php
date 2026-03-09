<?php

namespace App\Service\AI\Mcp\Tools;

use App\Service\AI\Mcp\AppsUiMetaService;
use App\Service\AI\Mcp\ChatGptDraftsService;
use App\Service\AI\Mcp\FormStateNormalizationService;
use App\Service\AI\Mcp\FormStateValidationService;
use App\Service\AI\Mcp\GuideTokenService;
use RuntimeException;

class UpdateDraftTool extends McpTool
{
    public function __construct(
        private readonly ChatGptDraftsService $drafts,
        private readonly GuideTokenService $guideTokenService,
        private readonly FormStateNormalizationService $formStateNormalizationService,
        private readonly FormStateValidationService $formStateValidationService,
        private readonly AppsUiMetaService $appsUiMetaService
    ) {
    }

    public function name(): string
    {
        return 'update_draft';
    }

    public function title(): string
    {
        return 'Replace Full Draft (Fallback)';
    }

    public function description(): string
    {
        return 'Fallback only: replace the entire form_state. Prefer patch_draft for normal edits. After success, call render_draft_preview before replying.';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['gpt_chat_id', 'form_state', 'guide_token', 'confirm_full_replace', 'replace_reason'],
            'properties' => [
                'gpt_chat_id' => ['type' => 'string', 'format' => 'uuid'],
                'guide_token' => [
                    'type' => 'string',
                    'description' => 'Required token from get_form_generation_guide.',
                ],
                'confirm_full_replace' => [
                    'type' => 'boolean',
                    'description' => 'Must be true to confirm full replacement. Use patch_draft for normal edits.',
                ],
                'replace_reason' => [
                    'type' => 'string',
                    'enum' => ['bulk_regeneration', 'schema_reset', 'cannot_express_with_patch'],
                    'description' => 'Why full replacement is required instead of patch_draft.',
                ],
                'form_state' => [
                    'type' => 'object',
                    'description' => 'Full replacement of form JSON following get_form_generation_guide.form_state_schema.',
                ],
            ],
        ];
    }

    public function execute(array $arguments): array
    {
        $this->guideTokenService->assertValid((string) ($arguments['guide_token'] ?? ''));
        if (($arguments['confirm_full_replace'] ?? null) !== true) {
            throw new RuntimeException('update_draft requires confirm_full_replace=true. Use patch_draft for standard edits.');
        }
        $reason = (string) ($arguments['replace_reason'] ?? '');
        if (! in_array($reason, ['bulk_regeneration', 'schema_reset', 'cannot_express_with_patch'], true)) {
            throw new RuntimeException('update_draft requires a valid replace_reason. Use patch_draft for standard edits.');
        }
        $chatId = (string) ($arguments['gpt_chat_id'] ?? '');
        $formState = $arguments['form_state'] ?? null;
        if (! is_array($formState)) {
            throw new RuntimeException('form_state must be an object');
        }
        $formState = $this->formStateNormalizationService->normalize($formState);
        $this->formStateValidationService->assertValidForUpdate($formState);

        $draft = $this->drafts->update($chatId, $formState);
        $serialized = $this->drafts->serialize($draft);
        $assistantDraft = $this->drafts->assistantDraft($serialized);

        return [
            'draft' => $assistantDraft,
            'draft_context' => $this->drafts->draftContext($serialized),
        ];
    }

    protected function meta(array $arguments, array $structuredContent): array
    {
        $context = $structuredContent['draft_context'] ?? null;
        if (! is_array($context)) {
            return [];
        }

        return $this->appsUiMetaService->forDraftContext($context);
    }

    protected function toolMeta(): array
    {
        return $this->appsUiMetaService->toolMeta();
    }

    protected function isReadOnly(): bool
    {
        return true;
    }
}
