<?php

namespace App\Service\AI\Mcp\Tools;

use App\Service\AI\Mcp\AppsUiMetaService;
use App\Service\AI\Mcp\ChatGptDraftsService;

class HandoffDraftTool extends McpTool
{
    public function __construct(
        private readonly ChatGptDraftsService $drafts,
        private readonly AppsUiMetaService $appsUiMetaService
    ) {
    }

    public function name(): string
    {
        return 'handoff_draft';
    }

    public function title(): string
    {
        return 'Open in OpnForm';
    }

    public function description(): string
    {
        return 'Finalize chat draft handoff and return takeover URL. Must be called before save/use/publish/share flows.';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['gpt_chat_id'],
            'properties' => [
                'gpt_chat_id' => ['type' => 'string', 'format' => 'uuid'],
            ],
        ];
    }

    public function execute(array $arguments): array
    {
        $chatId = (string) ($arguments['gpt_chat_id'] ?? '');
        $draft = $this->drafts->handoff($chatId);
        $serialized = $this->drafts->serialize($draft);
        $assistantDraft = $this->drafts->assistantDraft($serialized);

        return [
            'takeover_url' => front_url('forms/create/guest?gpt_chat_id=' . urlencode($draft->gpt_chat_id)),
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

    protected function isReadOnly(): bool
    {
        return true;
    }

    protected function toolMeta(): array
    {
        return $this->appsUiMetaService->toolMeta();
    }
}
