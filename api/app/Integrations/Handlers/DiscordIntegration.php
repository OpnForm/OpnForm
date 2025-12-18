<?php

namespace App\Integrations\Handlers;

use App\Models\Forms\Form;
use App\Open\MentionParser;
use App\Service\Forms\FormSubmissionFormatter;
use App\Service\Forms\SubmissionUrlService;
use Illuminate\Support\Arr;

class DiscordIntegration extends AbstractIntegrationHandler
{
    public static function getValidationRules(?Form $form): array
    {
        return [
            'discord_webhook_url' => 'required|url|starts_with:https://discord.com/api/webhooks',
            'include_submission_data' => 'boolean',
            'include_hidden_fields_submission_data' => ['nullable', 'boolean'],
            'link_open_form' => 'boolean',
            'link_edit_form' => 'boolean',
            'views_submissions_count' => 'boolean',
            'link_edit_submission' => 'boolean'
        ];
    }

    protected function getWebhookUrl(): ?string
    {
        return $this->integrationData->discord_webhook_url;
    }

    protected function shouldRun(): bool
    {
        return !is_null($this->getWebhookUrl()) && $this->form->is_pro && parent::shouldRun();
    }

    protected function getWebhookData(): array
    {
        $settings = (array) $this->integrationData ?? [];

        $externalLinks = [];
        if (Arr::get($settings, 'link_open_form', true)) {
            $externalLinks[] = '[**🔗 Open Form**](' . $this->form->share_url . ')';
        }
        if (Arr::get($settings, 'link_edit_form', true)) {
            $editFormURL = front_url('forms/' . $this->form->slug . '/show');
            $externalLinks[] = '[**✍️ Edit Form**](' . $editFormURL . ')';
        }
        if (Arr::get($settings, 'link_edit_submission', true) && $this->form->editable_submissions) {
            $editUrl = SubmissionUrlService::buildEditUrl($this->form, $this->submissionData['submission_id']);
            $externalLinks[] = '[**✍️ ' . $this->form->editable_submissions_button_text . '**](' . $editUrl . ')';
        }

        $color = hexdec(str_replace('#', '', $this->form->color));
        $blocks = [];
        if (Arr::get($settings, 'include_submission_data', true)) {
            $formatter = (new FormSubmissionFormatter($this->form, $this->submissionData))->outputStringsOnly();
            if (Arr::get($settings, 'include_hidden_fields_submission_data', false)) {
                $formatter->showHiddenFields();
            }
            $formattedData = $formatter->getFieldsWithValue();

            $submissionString = '';
            foreach ($formattedData as $field) {
                $tmpVal = is_array($field['value']) ? implode(',', $field['value']) : $field['value'];
                $submissionString .= '**' . ucfirst($field['name']) . '**: ' . $tmpVal . "\n";
            }
            $blocks[] = [
                'type' => 'rich',
                'color' => $color,
                'description' => $submissionString,
            ];
        }

        if (Arr::get($settings, 'views_submissions_count', true)) {
            $countString = '**👀 Views**: ' . (string) $this->form->views_count . " \n";
            $countString .= '**🖊️ Submissions**: ' . (string) $this->form->submissions_count;
            $blocks[] = [
                'type' => 'rich',
                'color' => $color,
                'description' => $countString,
            ];
        }

        if (count($externalLinks) > 0) {
            $blocks[] = [
                'type' => 'rich',
                'color' => $color,
                'description' => implode(' - ', $externalLinks),
            ];
        }

        $formattedData = (new FormSubmissionFormatter($this->form, $this->submissionData))->outputStringsOnly()->showHiddenFields()->getFieldsWithValue();
        $message = Arr::get($settings, 'message', 'New form submission');
        return [
            'content' => (new MentionParser($message, $formattedData))->parse(),
            'tts' => false,
            'username' => config('app.name'),
            'avatar_url' => asset('img/logo.png'),
            'embeds' => $blocks,
        ];
    }
}
