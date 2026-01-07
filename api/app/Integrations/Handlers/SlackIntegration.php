<?php

namespace App\Integrations\Handlers;

use App\Models\Forms\Form;
use App\Open\MentionParser;
use App\Service\Forms\FormSubmissionFormatter;
use App\Service\Forms\SubmissionUrlService;
use Illuminate\Support\Arr;

class SlackIntegration extends AbstractIntegrationHandler
{
    public static function getValidationRules(?Form $form): array
    {
        return [
            'slack_webhook_url' => 'required|url|starts_with:https://hooks.slack.com/',
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
        return $this->integrationData->slack_webhook_url;
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
            $externalLinks[] = '*<' . $this->form->share_url . '|🔗 Open Form>*';
        }
        if (Arr::get($settings, 'link_edit_form', true)) {
            $editFormURL = front_url('forms/' . $this->form->slug . '/show');
            $externalLinks[] = '*<' . $editFormURL . '|✍️ Edit Form>*';
        }
        if (Arr::get($settings, 'link_edit_submission', true) && $this->form->editable_submissions) {
            $editUrl = SubmissionUrlService::buildEditUrl($this->form, $this->submissionData['submission_id']);
            $externalLinks[] = '*<' . $editUrl . '|✍️ ' . $this->form->editable_submissions_button_text . '>*';
        }

        $formattedData = (new FormSubmissionFormatter($this->form, $this->submissionData))->outputStringsOnly()->showHiddenFields()->getFieldsWithValue();
        $message = Arr::get($settings, 'message', 'New form submission');
        $blocks = [
            [
                'type' => 'section',
                'text' => [
                    'type' => 'mrkdwn',
                    'text' => (new MentionParser($message, $formattedData))->parse(),
                ],
            ],
        ];

        if (Arr::get($settings, 'include_submission_data', true)) {
            $formatter = (new FormSubmissionFormatter($this->form, $this->submissionData))->outputStringsOnly();
            if (Arr::get($settings, 'include_hidden_fields_submission_data', false)) {
                $formatter->showHiddenFields();
            }
            $formattedData = $formatter->getFieldsWithValue();

            $submissionString = '';
            foreach ($formattedData as $field) {
                $tmpVal = is_array($field['value']) ? implode(',', $field['value']) : $field['value'];
                $submissionString .= '>*' . ucfirst($field['name']) . '*: ' . $tmpVal . " \n";
            }
            $blocks[] = [
                'type' => 'section',
                'text' => [
                    'type' => 'mrkdwn',
                    'text' => $submissionString,
                ],
            ];
        }

        if (Arr::get($settings, 'views_submissions_count', true)) {
            $countString = '*👀 Views*: ' . (string) $this->form->views_count . " \n";
            $countString .= '*🖊️ Submissions*: ' . (string) $this->form->submissions_count;
            $blocks[] = [
                'type' => 'section',
                'text' => [
                    'type' => 'mrkdwn',
                    'text' => $countString,
                ],
            ];
        }

        if (count($externalLinks) > 0) {
            $blocks[] = [
                'type' => 'section',
                'text' => [
                    'type' => 'mrkdwn',
                    'text' => implode('     ', $externalLinks),
                ],
            ];
        }

        return [
            'blocks' => $blocks,
        ];
    }
}
