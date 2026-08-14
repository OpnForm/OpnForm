<?php

namespace App\Mcp\Apps;

use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\AppResource;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Uri;
use Laravel\Mcp\Server\Ui\AppMeta;
use Laravel\Mcp\Server\Ui\Csp;

#[Name('form_draft_preview')]
#[Description('Interactive preview of a guest OpnForm draft with a secure editor handoff.')]
#[Uri('ui://opnform/form-draft-preview')]
class FormDraftPreviewApp extends AppResource
{
    public function handle(Request $request): Response
    {
        return Response::view('mcp.form-draft-preview-app');
    }

    public function appMeta(): AppMeta
    {
        $frontUrl = parse_url(front_url());
        if (! is_array($frontUrl) || ! isset($frontUrl['scheme'], $frontUrl['host'])) {
            return AppMeta::make();
        }

        $origin = $frontUrl['scheme'].'://'.$frontUrl['host'];
        if (isset($frontUrl['port'])) {
            $origin .= ':'.$frontUrl['port'];
        }

        return AppMeta::make()->csp(
            Csp::make()->frameDomains([$origin]),
        );
    }
}
