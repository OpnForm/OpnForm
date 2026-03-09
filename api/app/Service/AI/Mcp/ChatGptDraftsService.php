<?php

namespace App\Service\AI\Mcp;

use App\Models\Forms\AI\ChatGptFormDraft;
use Illuminate\Support\Str;
use RuntimeException;

class ChatGptDraftsService
{
    private const DEFAULT_EXPIRY_DAYS = 7;

    public function create(array $formState = []): ChatGptFormDraft
    {
        return ChatGptFormDraft::query()->create([
            'gpt_chat_id' => (string) Str::uuid(),
            'form_state' => $formState,
            'draft_version' => 1,
            'expires_at' => now()->addDays(self::DEFAULT_EXPIRY_DAYS),
            'last_accessed_at' => now(),
        ]);
    }

    public function fetch(string $gptChatId): ChatGptFormDraft
    {
        $draft = $this->getActiveOrFail($gptChatId);
        $this->touch($draft);

        return $draft;
    }

    public function update(string $gptChatId, array $formState): ChatGptFormDraft
    {
        $draft = $this->getActiveOrFail($gptChatId);

        $draft->forceFill([
            'form_state' => $formState,
            'draft_version' => $draft->draft_version + 1,
            'last_accessed_at' => now(),
            'expires_at' => now()->addDays(self::DEFAULT_EXPIRY_DAYS),
        ])->save();

        return $draft;
    }

    public function handoff(string $gptChatId): ChatGptFormDraft
    {
        $draft = $this->getActiveOrFail($gptChatId);

        $draft->forceFill([
            'handed_off_at' => now(),
            'last_accessed_at' => now(),
        ])->save();

        return $draft;
    }

    public function findActive(string $gptChatId): ?ChatGptFormDraft
    {
        if (! Str::isUuid($gptChatId)) {
            return null;
        }

        $draft = ChatGptFormDraft::query()
            ->where('gpt_chat_id', $gptChatId)
            ->first();

        if (! $draft || $draft->isExpired()) {
            return null;
        }

        return $draft;
    }

    public function getActiveOrFail(string $gptChatId): ChatGptFormDraft
    {
        $draft = $this->findActive($gptChatId);
        if (! $draft) {
            throw new RuntimeException('Draft not found');
        }

        return $draft;
    }

    public function serialize(ChatGptFormDraft $draft): array
    {
        return [
            'gpt_chat_id' => $draft->gpt_chat_id,
            'form_state' => $draft->form_state ?? [],
            'draft_version' => $draft->draft_version,
            'expires_at' => $draft->expires_at?->toIso8601String(),
            'preview_url' => front_url('gpt/drafts/' . $draft->gpt_chat_id . '/preview?v=' . $draft->draft_version),
        ];
    }

    public function draftContext(array $serializedDraft): array
    {
        return [
            'gpt_chat_id' => $serializedDraft['gpt_chat_id'] ?? null,
            'draft_version' => $serializedDraft['draft_version'] ?? null,
            'preview_url' => $serializedDraft['preview_url'] ?? null,
        ];
    }

    public function assistantDraft(array $serializedDraft): array
    {
        unset($serializedDraft['preview_url']);
        return $serializedDraft;
    }

    private function touch(ChatGptFormDraft $draft): void
    {
        $draft->forceFill([
            'last_accessed_at' => now(),
        ])->save();
    }
}
