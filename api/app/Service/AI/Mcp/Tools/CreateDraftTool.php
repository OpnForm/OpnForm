<?php

namespace App\Service\AI\Mcp\Tools;

use App\Service\AI\Mcp\AppsUiMetaService;
use App\Service\AI\Mcp\ChatGptDraftsService;
use App\Service\AI\Mcp\FormStateNormalizationService;
use App\Service\AI\Mcp\FormStateValidationService;
use App\Service\AI\Mcp\GuideTokenService;

class CreateDraftTool extends McpTool
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
        return 'create_draft';
    }

    public function title(): string
    {
        return 'Create Draft';
    }

    public function description(): string
    {
        return 'Create a new OpnForm chat draft. gpt_chat_id is always server-generated UUID. After success, call render_draft_preview before replying.';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['guide_token'],
            'properties' => [
                'guide_token' => [
                    'type' => 'string',
                    'description' => 'Required token from get_form_generation_guide.',
                ],
                'form_state' => [
                    'type' => 'object',
                    'description' => 'Form JSON following get_form_generation_guide.form_state_schema.',
                ],
            ],
        ];
    }

    public function execute(array $arguments): array
    {
        $this->guideTokenService->assertValid((string) ($arguments['guide_token'] ?? ''));
        $formState = is_array($arguments['form_state'] ?? null) ? $arguments['form_state'] : [];
        $formState = $this->formStateNormalizationService->normalize($formState);
        $this->formStateValidationService->assertValidForCreate($formState);
        $draft = $this->drafts->create($formState);
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
