<?php

namespace App\Service\AI\Mcp\Tools;

use App\Service\AI\Mcp\AppsUiMetaService;
use App\Service\AI\Mcp\ChatGptDraftsService;

class GetDraftTool extends McpTool
{
    public function __construct(
        private readonly ChatGptDraftsService $drafts,
        private readonly AppsUiMetaService $appsUiMetaService
    ) {
    }

    public function name(): string
    {
        return 'get_draft';
    }

    public function description(): string
    {
        return 'Get the current OpnForm chat draft by gpt_chat_id.';
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
        $draft = $this->drafts->fetch($chatId);
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
