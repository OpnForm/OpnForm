<?php

namespace App\Integrations\Handlers;

use App\Models\Forms\Form;

class ActivepiecesIntegration extends AbstractIntegrationHandler
{
    public static function getValidationRules(?Form $form): array
    {
        return [
            'webhook_url' => 'required|url',
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

        // Remove deprecated fields if they exist
        unset($data['submission'], $data['message']);

        return $data;
    }
}
