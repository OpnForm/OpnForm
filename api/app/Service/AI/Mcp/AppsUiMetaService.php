<?php

namespace App\Service\AI\Mcp;

class AppsUiMetaService
{
    public function toolMeta(): array
    {
        return [
            'ui' => [
                'resourceUri' => ChatGptUiResourceService::RESOURCE_URI,
            ],
            'openai/outputTemplate' => ChatGptUiResourceService::RESOURCE_URI,
            'openai/widgetAccessible' => true,
            'openai/toolInvocation/invoking' => 'Updating draft preview...',
            'openai/toolInvocation/invoked' => 'Draft preview ready.',
        ];
    }

    public function forDraftContext(array $draftContext): array
    {
        $previewUrl = (string) ($draftContext['preview_url'] ?? $this->buildPreviewUrl($draftContext));
        $previewUrlWithEmbed = $this->withEmbedFlag($previewUrl);
        $resourceUri = ChatGptUiResourceService::RESOURCE_URI;
        if ($previewUrlWithEmbed !== '') {
            $resourceUri .= '?preview_url=' . rawurlencode($previewUrlWithEmbed);
        }

        return [
            'ui' => [
                'resourceUri' => $resourceUri,
                'previewUri' => $resourceUri,
            ],
            'openai/outputTemplate' => $resourceUri,
            'openai/widgetDescription' => 'Live preview of the current OpnForm draft.',
            'openai/widgetPrefersBorder' => true,
        ];
    }

    private function buildPreviewUrl(array $draftContext): string
    {
        $chatId = (string) ($draftContext['gpt_chat_id'] ?? '');
        if ($chatId === '') {
            return '';
        }

        $draftVersion = (int) ($draftContext['draft_version'] ?? 1);
        $draftVersion = max(1, $draftVersion);
        return front_url('gpt/drafts/' . $chatId . '/preview?v=' . $draftVersion);
    }

    private function withEmbedFlag(string $previewUrl): string
    {
        if ($previewUrl === '') {
            return '';
        }

        $separator = str_contains($previewUrl, '?') ? '&' : '?';
        return $previewUrl . $separator . 'embed=chatgpt';
    }
}
