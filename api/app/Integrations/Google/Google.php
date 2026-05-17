<?php

namespace App\Integrations\Google;

use App\Integrations\Google\Sheets\SpreadsheetManager;
use App\Models\Integration\FormIntegration;
use Google\Client as Client;

class Google
{
    protected GoogleOAuthClient $oauth;

    public function __construct(
        protected FormIntegration $formIntegration
    ) {
        $this->oauth = new GoogleOAuthClient($formIntegration->provider);
    }

    public function getClient(): Client
    {
        return $this->oauth->getClient();
    }

    public function refreshToken(): static
    {
        $this->oauth->refreshToken();

        return $this;
    }

    public function sheets(): SpreadsheetManager
    {
        return new SpreadsheetManager($this, $this->formIntegration);
    }
}
