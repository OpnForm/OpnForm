<?php

namespace App\Mcp\Apps;

use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Uri;

#[Name('legacy_form_draft_preview_v8')]
#[Description('Compatibility resource for the form preview URI published by the current OpenAI plugin snapshot.')]
#[Uri(LegacyFormDraftPreviewApp::URI)]
class LegacyFormDraftPreviewApp extends FormDraftPreviewApp
{
    public const URI = 'ui://opnform/form-draft-preview-v8.html';
}
