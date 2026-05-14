<?php

namespace App\Service\Forms;

use App\Models\Forms\Form;
use App\Open\MentionParser;

class FormSubmissionProcessor
{
    /**
     * Determines if a form submission should be processed synchronously
     */
    public function shouldProcessSynchronously(Form $form): bool
    {
        // If editable submissions is enabled, always process synchronously
        if ($form->editable_submissions) {
            return true;
        }

        // If no redirect URL, no need to process synchronously
        if (!$form->redirect_url) {
            return false;
        }

        // Check if any UUID/auto-increment fields are used in redirect URL
        foreach ($form->properties as $field) {
            if ($this->isGeneratedField($field) && $this->isFieldUsedInRedirectUrl($form, $field['id'])) {
                return true;
            }
        }

        return false;
    }

    /**
     * Checks if a field is a generated field (UUID or auto-increment)
     */
    private function isGeneratedField(array $field): bool
    {
        return $field['type'] === 'text' &&
            (
                (isset($field['generates_uuid']) && $field['generates_uuid']) ||
                (isset($field['generates_auto_increment_id']) && $field['generates_auto_increment_id'])
            );
    }

    /**
     * Checks if a field ID is used in the form's redirect URL
     */
    private function isFieldUsedInRedirectUrl(Form $form, string $fieldId): bool
    {
        return str_contains($form->redirect_url, $fieldId);
    }

    /**
     * Get the redirect data for a form submission
     */
    public function getRedirectData(Form $form, array $submissionData): array
    {
        $formattedData = collect($submissionData)->map(function ($value, $key) {
            return ['id' => $key, 'value' => $value];
        })->values()->all();

        $redirectUrl = ($form->redirect_url)
            ? (new MentionParser($form->redirect_url, $formattedData))->urlFriendlyOutput()->parseAsText()
            : null;

        if ($redirectUrl && !filter_var($redirectUrl, FILTER_VALIDATE_URL)) {
            $redirectUrl = null;
        }

        return $form->is_pro && $redirectUrl ? [
            'redirect' => true,
            'redirect_url' => $redirectUrl,
        ] : [
            'redirect' => false,
        ];
    }
}
