<?php

namespace App\Integrations\Handlers;

use App\Models\Forms\Form;
use App\Rules\PublicWebhookUrlRule;
use Illuminate\Support\Facades\Http;

class MakeIntegration extends AbstractIntegrationHandler
{
    public static function getValidationRules(?Form $form): array
    {
        return [
            'webhook_url' => ['required', 'url', new PublicWebhookUrlRule()],
            'webhook_secret' => 'nullable|string|min:12',
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

    public function handle(): void
    {
        if (empty($this->integrationData->webhook_secret)) {
            parent::handle();
            return;
        }

        if ($this->shouldRun()) {
            $this->sendJsonWebhook(Http::throw());
        }
    }

    protected function getWebhookData(): array
    {
        $data = parent::getWebhookData();

        unset($data['submission'], $data['message']);

        return $data;
    }
}
