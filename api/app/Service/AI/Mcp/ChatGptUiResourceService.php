<?php

namespace App\Service\AI\Mcp;

use RuntimeException;

class ChatGptUiResourceService
{
    public const RESOURCE_URI = 'ui://opnform/chatgpt/draft-preview';
    private const RESOURCE_NAME = 'OpnForm Draft Preview';
    private const RESOURCE_DESCRIPTION = 'Inline OpnForm draft preview widget.';
    private const RESOURCE_MIME_TYPE = 'text/html;profile=mcp-app';

    public function list(): array
    {
        return [[
            'uri' => self::RESOURCE_URI,
            'name' => self::RESOURCE_NAME,
            'description' => self::RESOURCE_DESCRIPTION,
            'mimeType' => self::RESOURCE_MIME_TYPE,
            '_meta' => $this->resourceMeta(),
        ]];
    }

    public function read(string $uri): array
    {
        if (! str_starts_with($uri, self::RESOURCE_URI)) {
            throw new RuntimeException('Resource not found');
        }

        $seededPreviewUrl = $this->extractPreviewUrlFromResourceUri($uri);

        return [[
            'uri' => $uri,
            'mimeType' => self::RESOURCE_MIME_TYPE,
            'text' => $this->template($seededPreviewUrl),
            '_meta' => $this->resourceMeta(),
        ]];
    }

    private function extractPreviewUrlFromResourceUri(string $uri): string
    {
        $query = parse_url($uri, PHP_URL_QUERY);
        if (! is_string($query) || $query === '') {
            return '';
        }

        parse_str($query, $params);
        $previewUrl = (string) ($params['preview_url'] ?? '');
        return $previewUrl;
    }

    private function resourceMeta(): array
    {
        return [
            'ui' => [
                'domain' => $this->widgetDomain(),
                'csp' => [
                    'connectDomains' => [],
                    'resourceDomains' => [],
                    'frameDomains' => $this->frameDomains(),
                    'redirectDomains' => $this->redirectDomains(),
                ],
            ],
            'openai/widgetDescription' => self::RESOURCE_DESCRIPTION,
            'openai/widgetPrefersBorder' => true,
            // Compatibility aliases for older hosts.
            'openai/widgetDomain' => $this->widgetDomain(),
            'openai/widgetCSP' => [
                'connect_domains' => [],
                'resource_domains' => [],
                'frame_domains' => $this->frameDomains(),
                'redirect_domains' => $this->redirectDomains(),
            ],
        ];
    }

    private function widgetDomain(): string
    {
        $configured = $this->originFromUrl((string) config('app.chatgpt_widget_domain'));
        if ($configured !== '') {
            return $configured;
        }

        $fallback = $this->originFromUrl((string) config('app.front_url'));
        if ($fallback !== '') {
            return $fallback;
        }

        return $this->originFromUrl((string) config('app.url'));
    }

    private function frameDomains(): array
    {
        $domains = [];

        foreach ((array) config('app.chatgpt_widget_frame_domains', []) as $domain) {
            $origin = $this->originFromUrl((string) $domain);
            if ($origin !== '') {
                $domains[] = $origin;
            }
        }

        $frontOrigin = $this->originFromUrl((string) config('app.front_url'));
        if ($frontOrigin !== '') {
            $domains[] = $frontOrigin;
        }

        $appOrigin = $this->originFromUrl((string) config('app.url'));
        if ($appOrigin !== '') {
            $domains[] = $appOrigin;
        }

        return array_values(array_unique($domains));
    }

    private function redirectDomains(): array
    {
        return $this->frameDomains();
    }

    private function originFromUrl(string $url): string
    {
        if ($url === '') {
            return '';
        }

        $parts = parse_url($url);
        if (! is_array($parts)) {
            return '';
        }

        $scheme = isset($parts['scheme']) ? strtolower((string) $parts['scheme']) : '';
        $host = isset($parts['host']) ? strtolower((string) $parts['host']) : '';
        if ($scheme === '' || $host === '') {
            return '';
        }

        $port = isset($parts['port']) ? ':' . (int) $parts['port'] : '';
        return "{$scheme}://{$host}{$port}";
    }

    private function template(string $seededPreviewUrl = ''): string
    {
        $seededPreviewUrl = str_replace(
            ['\\', '"', "\n", "\r"],
            ['\\\\', '\\"', '', ''],
            $seededPreviewUrl
        );

        $html = <<<'HTML'
<!doctype html>
<html>
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <style>
    html, body { margin: 0; padding: 0; overflow: hidden; font-family: sans-serif; background: #fff; }
    #root { height: 560px; min-height: 560px; max-height: 560px; position: relative; }
    .toolbar { position: absolute; top: 10px; right: 10px; z-index: 3; }
    .save {
      border: 1px solid #d4d4d8;
      background: #fff;
      color: #111827;
      border-radius: 8px;
      padding: 6px 10px;
      font-size: 12px;
      font-weight: 600;
      cursor: pointer;
      box-shadow: 0 1px 2px rgba(0, 0, 0, .08);
    }
    .save[disabled] { opacity: .6; cursor: not-allowed; }
    .frame { width: 100%; height: 100%; border: 0; display: block; }
    .empty { padding: 16px; color: #444; line-height: 1.5; }
    .empty a { color: #0b57d0; }
  </style>
</head>
<body>
  <div id="root"></div>
  <script>
    (function () {
      const root = document.getElementById('root');
      const openai = window.openai || {};
      const seededPreviewUrl = "__SEEDED_PREVIEW_URL__";
      let currentPreviewUrl = '';
      let retriesLeft = 150;
      let retryTimer = null;
      let keepAliveTimer = null;

      function parseMaybeJson(value) {
        if (typeof value !== 'string') return value;
        try {
          return JSON.parse(value);
        } catch (_error) {
          return value;
        }
      }

      function readToolOutput() {
        const candidates = [
          parseMaybeJson(openai?.toolOutput),
          parseMaybeJson(openai?.globals?.toolOutput),
          parseMaybeJson(openai?.state?.toolOutput),
          parseMaybeJson(openai?.state?.output),
          parseMaybeJson(openai?.lastToolResult),
          openai.toolOutput,
          openai.output,
          window.__OPENAI_TOOL_OUTPUT__,
          window.__INITIAL_TOOL_OUTPUT__,
        ];

        for (const candidate of candidates) {
          if (!candidate) continue;
          if (candidate.structuredContent) return candidate.structuredContent;
          return candidate;
        }
        return null;
      }

      function getPreviewUrl(structured) {
        if (!structured || typeof structured !== 'object') return '';
        const context = structured.draft_context || {};
        if (typeof context.preview_url === 'string' && context.preview_url) return context.preview_url;

        const draft = structured.draft || {};
        if (typeof draft.preview_url === 'string' && draft.preview_url) return draft.preview_url;
        return '';
      }

      function deepFindPreviewUrl(value, depth, seen) {
        if (!value || depth <= 0) return '';
        if (typeof value === 'string') {
          const parsed = parseMaybeJson(value);
          if (parsed !== value) return deepFindPreviewUrl(parsed, depth - 1, seen);
          return '';
        }
        if (typeof value !== 'object') return '';
        if (seen.has(value)) return '';
        seen.add(value);

        if (typeof value.preview_url === 'string' && value.preview_url) return value.preview_url;
        if (value.draft_context && typeof value.draft_context.preview_url === 'string' && value.draft_context.preview_url) {
          return value.draft_context.preview_url;
        }
        if (value.draft && typeof value.draft.preview_url === 'string' && value.draft.preview_url) {
          return value.draft.preview_url;
        }

        for (const key of Object.keys(value)) {
          const found = deepFindPreviewUrl(value[key], depth - 1, seen);
          if (found) return found;
        }
        return '';
      }

      function deepFindDraftContext(value, depth, seen) {
        if (!value || depth <= 0) return null;
        if (typeof value === 'string') {
          const parsed = parseMaybeJson(value);
          if (parsed !== value) return deepFindDraftContext(parsed, depth - 1, seen);
          return null;
        }
        if (typeof value !== 'object') return null;
        if (seen.has(value)) return null;
        seen.add(value);

        const context = value.draft_context || value;
        const gptChatId = typeof context.gpt_chat_id === 'string' ? context.gpt_chat_id : '';
        if (gptChatId) {
          const versionRaw = context.draft_version;
          const draftVersion = Number.isFinite(Number(versionRaw)) ? Math.max(1, Number(versionRaw)) : 1;
          return { gpt_chat_id: gptChatId, draft_version: draftVersion };
        }

        for (const key of Object.keys(value)) {
          const found = deepFindDraftContext(value[key], depth - 1, seen);
          if (found) return found;
        }
        return null;
      }

      function resolvePreviewUrl() {
        if (seededPreviewUrl) return seededPreviewUrl;

        const structured = readToolOutput();
        const fromStructured = getPreviewUrl(structured);
        if (fromStructured) return fromStructured;

        const searchTargets = [
          openai,
          openai?.globals,
          openai?.toolOutput,
          openai?.output,
          window.__OPENAI_TOOL_OUTPUT__,
          window.__INITIAL_TOOL_OUTPUT__,
        ];

        for (const target of searchTargets) {
          const found = deepFindPreviewUrl(target, 6, new WeakSet());
          if (found) return found;
        }

        for (const target of searchTargets) {
          const context = deepFindDraftContext(target, 6, new WeakSet());
          if (context?.gpt_chat_id) {
            const base = window.location.origin;
            return `${base}/gpt/drafts/${encodeURIComponent(context.gpt_chat_id)}/preview?v=${encodeURIComponent(String(context.draft_version || 1))}`;
          }
        }

        return '';
      }

      function resolveChatId(previewUrl, structured) {
        const contextId = structured?.draft_context?.gpt_chat_id;
        if (typeof contextId === 'string' && contextId) return contextId;

        const draftId = structured?.draft?.gpt_chat_id;
        if (typeof draftId === 'string' && draftId) return draftId;

        const match = (previewUrl || '').match(/\/gpt\/drafts\/([0-9a-fA-F-]{36})\//);
        return match ? match[1] : '';
      }

      function openTakeover(url) {
        if (!url) return;
        if (typeof openai?.openExternal === 'function') {
          try {
            const maybePromise = openai.openExternal(url);
            if (maybePromise && typeof maybePromise.then === 'function') {
              maybePromise.catch(() => {
                try { window.location.assign(url); } catch (_e) {}
              });
            }
            return;
          } catch (_err) {}
        }
        try {
          window.location.assign(url);
          return;
        } catch (_err) {}

        try {
          window.open(url, '_blank', 'noopener,noreferrer');
        } catch (_err) {}
      }

      function render() {
        const structured = readToolOutput();
        let previewUrl = seededPreviewUrl || getPreviewUrl(structured) || resolvePreviewUrl();
        if (previewUrl && !/[?&]embed=chatgpt(?:&|$)/.test(previewUrl)) {
          previewUrl += (previewUrl.includes('?') ? '&' : '?') + 'embed=chatgpt';
        }

        if (!previewUrl) {
          if (retriesLeft > 0) {
            retriesLeft -= 1;
            if (!root.querySelector('.empty')) {
              root.innerHTML = '<div class="empty">Loading preview...</div>';
            }
            clearTimeout(retryTimer);
            retryTimer = setTimeout(render, 200);
            return;
          }

          root.innerHTML = '<div class="empty">Preview unavailable. Ask to render the draft preview again.</div>';
          return;
        }

        clearTimeout(retryTimer);
        currentPreviewUrl = previewUrl;
        root.innerHTML = '';

        const toolbar = document.createElement('div');
        toolbar.className = 'toolbar';
        const saveButton = document.createElement('button');
        saveButton.className = 'save';
        saveButton.type = 'button';
        saveButton.textContent = 'Save Form ↗';
        saveButton.addEventListener('click', async function () {
          const chatId = resolveChatId(currentPreviewUrl, structured);
          if (!chatId) return;

          saveButton.disabled = true;
          saveButton.textContent = 'Opening...';
          try {
            let takeoverUrl = '';
            if (typeof openai?.callTool === 'function') {
              const result = await openai.callTool('handoff_draft', { gpt_chat_id: chatId });
              takeoverUrl = result?.structuredContent?.takeover_url || result?.takeover_url || '';
            }

            if (!takeoverUrl) {
              const base = new URL(currentPreviewUrl, window.location.href);
              takeoverUrl = `${base.origin}/forms/create/guest?gpt_chat_id=${encodeURIComponent(chatId)}`;
            }

            openTakeover(takeoverUrl);
          } catch (_error) {
            try {
              const base = new URL(currentPreviewUrl, window.location.href);
              openTakeover(`${base.origin}/forms/create/guest?gpt_chat_id=${encodeURIComponent(chatId)}`);
            } catch (_inner) {}
          } finally {
            saveButton.disabled = false;
            saveButton.textContent = 'Save Form ↗';
          }
        });
        toolbar.appendChild(saveButton);
        root.appendChild(toolbar);

        const iframe = document.createElement('iframe');
        iframe.className = 'frame';
        iframe.src = previewUrl;
        iframe.referrerPolicy = 'no-referrer';
        root.appendChild(iframe);
      }

      render();
      if (typeof openai?.on === 'function') {
        openai.on('openai:set_globals', render);
        openai.on('openai:tool_result', render);
      }
      keepAliveTimer = setInterval(render, 1500);
    })();
  </script>
</body>
</html>
HTML;
        return str_replace('__SEEDED_PREVIEW_URL__', $seededPreviewUrl, $html);
    }
}
