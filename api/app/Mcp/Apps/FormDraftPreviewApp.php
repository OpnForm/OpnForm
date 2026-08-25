<?php

namespace App\Mcp\Apps;

use App\Support\Mcp\McpAvailability;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\AppResource;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Uri;
use Laravel\Mcp\Server\Ui\AppMeta;
use Laravel\Mcp\Server\Ui\Csp;

#[Name('form_draft_preview')]
#[Description('Interactive preview of a temporary guest OpnForm draft with an optional editor handoff.')]
#[Uri(FormDraftPreviewApp::URI)]
class FormDraftPreviewApp extends AppResource
{
    /**
     * This URI is persisted by OpenAI plugin versions and existing conversations.
     * Keep it stable for non-breaking widget changes and continue serving old URIs.
     */
    public const URI = 'ui://opnform/form-draft-preview.html';

    public function shouldRegister(McpAvailability $availability): bool
    {
        return $availability->guestDraftsEnabled();
    }

    public function handle(Request $request): Response
    {
        $response = Response::view('mcp.form-draft-preview-app');
        $origin = $this->frontOrigin();

        if ($origin !== null) {
            $response->withMeta('openai/widgetCSP', [
                'connect_domains' => [],
                'resource_domains' => [$origin],
                'frame_domains' => [$origin],
                'redirect_domains' => [$origin],
            ]);
        }

        return $response;
    }

    public function appMeta(): AppMeta
    {
        $origin = $this->frontOrigin();
        if ($origin === null) {
            return AppMeta::make();
        }

        return AppMeta::make()->csp(
            Csp::make()
                ->resourceDomains([$origin])
                ->frameDomains([$origin]),
        )->domain($origin);
    }

    private function frontOrigin(): ?string
    {
        $frontUrl = parse_url(front_url());
        if (! is_array($frontUrl) || ! isset($frontUrl['scheme'], $frontUrl['host'])) {
            return null;
        }

        $origin = $frontUrl['scheme'].'://'.$frontUrl['host'];

        return isset($frontUrl['port']) ? $origin.':'.$frontUrl['port'] : $origin;
    }
}
