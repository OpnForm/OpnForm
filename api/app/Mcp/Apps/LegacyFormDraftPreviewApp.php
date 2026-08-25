<?php

namespace App\Mcp\Apps;

use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Contracts\HasUriTemplate;
use Laravel\Mcp\Support\UriTemplate;

#[Name('legacy_form_draft_preview')]
#[Description('Compatibility resource for OpnForm preview templates referenced by existing plugin versions and conversations.')]
class LegacyFormDraftPreviewApp extends FormDraftPreviewApp implements HasUriTemplate
{
    public function uriTemplate(): UriTemplate
    {
        return new UriTemplate('ui://opnform/form-draft-preview-{version}');
    }
}
