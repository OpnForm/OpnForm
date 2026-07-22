<?php

namespace App\Integrations\Handlers;

use App\Models\Forms\Form;
use App\Rules\PublicWebhookUrlRule;

class MakeIntegration extends AbstractIntegrationHandler
{
    public static function getValidationRules(?Form $form): array
    {
        return [
            'webhook_url' => ['required', 'url', new PublicWebhookUrlRule()],
            'provider_url' => 'nullable|url',
        ];
    }

    protected function getWebhookUrl(): ?string
    {
        return $this->integrationData->webhook_url ?? null;
    }

    protected function shouldRun(): bool
    {
        return !is_null($this->getWebhookUrl()) && parent::shouldRun();
    }

    protected function getWebhookData(): array
    {
        $data = parent::getWebhookData();

        unset($data['submission'], $data['message']);

        return $data;
    }
}
