<?php

namespace App\Integrations\Handlers;

use App\Models\Forms\Form;
use App\Open\MentionParser;
use App\Service\Forms\FormSubmissionFormatter;
use App\Service\Forms\SubmissionUrlService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Arr;

class TelegramIntegration extends AbstractIntegrationHandler
{
    protected const TELEGRAM_API_BASE = 'https://api.telegram.org/bot';

    public static function getValidationRules(?Form $form): array
    {
        return [
            'include_submission_data' => 'boolean',
            'include_hidden_fields_submission_data' => ['nullable', 'boolean'],
            'link_open_form' => 'boolean',
            'link_edit_form' => 'boolean',
            'views_submissions_count' => 'boolean',
            'link_edit_submission' => 'boolean'
        ];
    }

    protected function getChatId(): ?string
    {
        return $this->provider?->provider_user_id;
    }

    protected function getWebhookUrl(): ?string
    {
        $token = config('services.telegram.bot_token');
        if (!$token) {
            return null;
        }
        return self::TELEGRAM_API_BASE . $token . '/sendMessage';
    }

    protected function shouldRun(): bool
    {
        $hasValidProvider = $this->formIntegration->oauth_id && $this->provider;
        $shouldRun = !is_null($this->getWebhookUrl()) && $hasValidProvider && $this->form->is_pro && parent::shouldRun();
        return $shouldRun;
    }

    protected function getWebhookData(): array
    {
        $settings = (array) $this->integrationData ?? [];
        $messageParts = [];

        $formatter = (new FormSubmissionFormatter($this->form, $this->submissionData))->outputStringsOnly();
        if (Arr::get($settings, 'include_hidden_fields_submission_data', false)) {
            $formatter->showHiddenFields();
        }
        $formattedData = $formatter->getFieldsWithValue();

        $mentionMessage = Arr::get($settings, 'message', 'New form submission');
        $parsedMentionMessage = (new MentionParser($mentionMessage, $formattedData))->parse();
        $messageParts[] = $this->escapeMarkdownV2($parsedMentionMessage);

        if (Arr::get($settings, 'include_submission_data', true)) {
            $messageParts[] = "\n\n📝 *Submission Details:*";
            foreach ($formattedData as $field) {
                $fieldName = ucfirst($field['name']);
                $fieldValue = is_array($field['value']) ? implode(', ', $field['value']) : $field['value'];
                $messageParts[] = "*" . $this->escapeMarkdownV2($fieldName) . "*: " . $this->escapeMarkdownV2($fieldValue ?? '');
            }
        }

        if (Arr::get($settings, 'views_submissions_count', true)) {
            $messageParts[] = "\n📊 *Form Statistics:*";
            $messageParts[] = "👀 *Views*: " . (string) $this->form->views_count;
            $messageParts[] = "🖊️ *Submissions*: " . (string) $this->form->submissions_count;
        }

        $links = [];
        if (Arr::get($settings, 'link_open_form', true)) {
            $url = $this->escapeMarkdownV2($this->form->share_url);
            $links[] = "[🔗 Open Form](" . $url . ")";
        }
        if (Arr::get($settings, 'link_edit_form', true)) {
            $url = $this->escapeMarkdownV2(front_url('forms/' . $this->form->slug . '/show'));
            $links[] = "[✍️ Edit Form](" . $url . ")";
        }
        if (Arr::get($settings, 'link_edit_submission', true) && $this->form->editable_submissions) {
            $editUrl = SubmissionUrlService::buildEditUrl($this->form, $this->submissionData['submission_id']);
            $buttonText = $this->escapeMarkdownV2($this->form->editable_submissions_button_text);
            $url = $this->escapeMarkdownV2($editUrl);
            $links[] = "[✍️ " . $buttonText . "](" . $url . ")";
        }
        if (count($links) > 0) {
            $messageParts[] = "\n" . implode("\n", $links);
        }

        $finalMessageText = implode("\n", $messageParts);

        $payload = [
            'chat_id' => $this->getChatId(),
            'text' => $finalMessageText,
            'parse_mode' => 'MarkdownV2'
        ];

        return $payload;
    }

    /**
     * Escape special characters for Telegram MarkdownV2 format
     * @see https://core.telegram.org/bots/api#markdownv2-style
     */
    protected function escapeMarkdownV2(string $text): string
    {
        $specialChars = ['_', '*', '[', ']', '(', ')', '~', '`', '>', '#', '+', '-', '=', '|', '{', '}', '.', '!'];
        $text = str_replace('\\', '\\\\', $text);
        return str_replace($specialChars, array_map(fn ($char) => '\\' . $char, $specialChars), $text);
    }

    public static function isOAuthRequired(): bool
    {
        return true;
    }

    public function handle(): void
    {
        if (!$this->shouldRun()) {
            return;
        }
        $url = $this->getWebhookUrl();
        if (!$url) {
            logger()->error('TelegramIntegration failed: Missing bot token.', [
                'form_id' => $this->form?->id,
                'integration_id' => $this->formIntegration?->id,
            ]);
            return;
        }

        $data = $this->getWebhookData();
        if (empty($data['chat_id'])) {
            logger()->error('TelegramIntegration failed: Missing chat_id.', [
                'form_id' => $this->form?->id,
                'integration_id' => $this->formIntegration?->id,
                'provider_id' => $this->provider?->id
            ]);
            return;
        }

        try {
            $response = Http::post($url, $data);

            if ($response->failed()) {
                logger()->warning('TelegramIntegration request failed', [
                    'form_id' => $this->form->id,
                    'integration_id' => $this->formIntegration->id,
                    'status' => $response->status(),
                    'response' => $response->json() ?? $response->body()
                ]);
            }
        } catch (\Exception $e) {
            logger()->error('TelegramIntegration failed during API call', [
                'form_id' => $this->form->id,
                'integration_id' => $this->formIntegration->id,
                'message' => $e->getMessage(),
                'exception' => $e
            ]);
        }
    }
}
