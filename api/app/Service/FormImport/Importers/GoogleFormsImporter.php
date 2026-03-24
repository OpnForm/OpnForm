<?php

namespace App\Service\FormImport\Importers;

use App\Models\OAuthProvider;
use App\Service\FormImport\FormImportException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;

class GoogleFormsImporter extends AbstractImporter
{
    private const GOOGLE_FORMS_API = 'https://forms.googleapis.com/v1/forms/';

    public function import(array $importData): array
    {
        $accessToken = $this->resolveAccessToken($importData);

        $formId = $this->extractFormId($importData['url'] ?? '');
        if (! $formId) {
            throw new FormImportException('Could not extract a form ID from the URL. Please use the edit URL (docs.google.com/forms/d/FORM_ID/edit).');
        }

        $formData = $this->fetchForm($formId, $accessToken);

        return $this->parseFormData($formData);
    }

    private function resolveAccessToken(array $importData): string
    {
        $oauthProviderId = $importData['oauth_provider_id'] ?? null;
        if (! $oauthProviderId) {
            throw new FormImportException('Please select a Google account to import from.');
        }

        $provider = OAuthProvider::where('id', $oauthProviderId)
            ->where('user_id', Auth::id())
            ->first();

        if (!$provider || !$provider->access_token) {
            throw new FormImportException('Google account not found or token expired. Please reconnect your Google account.');
        }

        return $provider->access_token;
    }

    public function allowedDomains(): array
    {
        return ['docs.google.com'];
    }

    private function extractFormId(string $url): ?string
    {
        // Match: /forms/d/{formId}/...
        if (preg_match('#/forms/d/([a-zA-Z0-9_-]+)#', $url, $matches)) {
            // Reject published IDs (/d/e/...)
            if ($matches[1] === 'e') {
                return null;
            }

            return $matches[1];
        }

        return null;
    }

    private function fetchForm(string $formId, string $accessToken): array
    {
        $response = Http::timeout(15)
            ->withToken($accessToken)
            ->get(self::GOOGLE_FORMS_API . $formId);

        if ($response->status() === 401 || $response->status() === 403) {
            throw new FormImportException(
                'Google authentication expired or insufficient permissions. Please reconnect your Google account.'
            );
        }

        if ($response->status() === 404) {
            throw new FormImportException('Form not found. Make sure you have access to this Google Form.');
        }

        if (! $response->successful()) {
            throw new FormImportException('Failed to fetch Google Form. HTTP status: ' . $response->status());
        }

        return $response->json();
    }

    private function parseFormData(array $data): array
    {
        $title = $this->sanitizeText($data['info']['title'] ?? $data['info']['documentTitle'] ?? 'Imported Google Form', 60);
        $description = $this->sanitizeText($data['info']['description'] ?? '', 8000);
        $items = $data['items'] ?? [];

        $properties = [];

        if ($description != '') {
            $properties[] = $this->mapTextItem(['description' => $description]);
        }

        foreach ($items as $item) {
            $mapped = $this->mapItem($item);
            if ($mapped !== null) {
                $properties[] = $mapped;
            }
        }

        return [
            'title' => $title,
            'properties' => $properties,
        ];
    }

    private function mapItem(array $item): ?array
    {
        if (isset($item['questionItem'])) {
            return $this->mapQuestionItem($item);
        }

        if (isset($item['questionGroupItem'])) {
            return $this->mapQuestionGroupItem($item);
        }

        if (isset($item['textItem'])) {
            return $this->mapTextItem($item);
        }

        if (isset($item['pageBreakItem'])) {
            return $this->mapPageBreak();
        }

        return null;
    }

    private function mapQuestionItem(array $item): ?array
    {
        $question = $item['questionItem']['question'] ?? [];
        $title = $this->sanitizeText($item['title'] ?? 'Untitled', 255);
        $required = (bool) ($question['required'] ?? false);

        if (isset($question['choiceQuestion'])) {
            return $this->mapChoiceQuestion($question['choiceQuestion'], $title, $required, $item);
        }

        if (isset($question['scaleQuestion'])) {
            return $this->mapScaleQuestion($question['scaleQuestion'], $title, $required);
        }

        if (isset($question['dateQuestion'])) {
            return $this->mapDateQuestion($question['dateQuestion'], $title, $required);
        }

        if (isset($question['timeQuestion'])) {
            return $this->mapTimeQuestion($title, $required);
        }

        if (isset($question['ratingQuestion'])) {
            return $this->mapRatingQuestion($question['ratingQuestion'], $title, $required);
        }

        if (isset($question['fileUploadQuestion'])) {
            return $this->baseProperty($title, 'files', $required);
        }

        if (isset($question['textQuestion'])) {
            $isParagraph = (bool) ($question['textQuestion']['paragraph'] ?? false);
            $property = $this->baseProperty($title, 'text', $required);
            if ($isParagraph) {
                $property['multi_lines'] = true;
            }

            return $property;
        }

        // Unknown question type → fallback to text
        return $this->baseProperty($title, 'text', $required);
    }

    private function mapChoiceQuestion(array $choice, string $title, bool $required, array $item): array
    {
        $choiceType = $choice['type'] ?? 'RADIO';
        $options = $choice['options'] ?? [];

        $opnType = match ($choiceType) {
            'CHECKBOX' => 'multi_select',
            'DROP_DOWN' => 'select',
            default => 'select', // RADIO
        };

        $labels = [];
        foreach ($options as $option) {
            if (isset($option['isOther']) && $option['isOther']) {
                $labels[] = 'Other';

                continue;
            }
            $text = $this->sanitizeText($option['value'] ?? '', 255);
            if ($text !== '') {
                $labels[] = $text;
            }
        }

        $property = $this->baseProperty($title, $opnType, $required);

        if (! empty($item['description'])) {
            $property['help'] = $this->sanitizeText($item['description'], 1000);
        }

        if ($labels !== []) {
            $property[$opnType]['options'] = array_map(
                fn ($label) => ['id' => $this->generateFieldId(), 'name' => $label],
                $labels
            );
        }

        if ($choiceType === 'CHECKBOX' || count($labels) <= 5) {
            $property['without_dropdown'] = true;
        }

        return $property;
    }

    private function mapScaleQuestion(array $scale, string $title, bool $required): array
    {
        $property = $this->baseProperty($title, 'scale', $required);
        $property['scale_min_value'] = (int) ($scale['low'] ?? 1);
        $property['scale_max_value'] = (int) ($scale['high'] ?? 5);
        $property['scale_step_value'] = 1;

        if ($property['scale_max_value'] <= $property['scale_min_value']) {
            $property['scale_max_value'] = $property['scale_min_value'] + 5;
        }

        if (! empty($scale['lowLabel'])) {
            $property['help'] = $this->sanitizeText($scale['lowLabel'], 255)
                . ' → '
                . $this->sanitizeText($scale['highLabel'] ?? '', 255);
        }

        return $property;
    }

    private function mapRatingQuestion(array $rating, string $title, bool $required): array
    {
        $property = $this->baseProperty($title, 'rating', $required);
        $property['rating_max_value'] = max(1, min(10, (int) ($rating['ratingScaleLevel'] ?? 5)));

        return $property;
    }

    private function mapDateQuestion(array $dateQ, string $title, bool $required): array
    {
        $property = $this->baseProperty($title, 'date', $required);
        if (! empty($dateQ['includeTime'])) {
            $property['with_time'] = true;
        }

        return $property;
    }

    private function mapTimeQuestion(string $title, bool $required): array
    {
        $property = $this->baseProperty($title, 'date', $required);
        $property['with_time'] = true;

        return $property;
    }

    private function mapQuestionGroupItem(array $item): ?array
    {
        $group = $item['questionGroupItem'] ?? [];
        $grid = $group['grid'] ?? [];
        $questions = $group['questions'] ?? [];
        $title = $this->sanitizeText($item['title'] ?? 'Grid', 255);

        $columns = $grid['columns']['options'] ?? [];
        $columnLabels = array_map(
            fn ($col) => $this->sanitizeText($col['value'] ?? '', 255),
            $columns
        );
        $columnLabels = array_filter($columnLabels, fn ($s) => $s !== '');

        $rowLabels = [];
        foreach ($questions as $q) {
            $rowTitle = $this->sanitizeText($q['rowQuestion']['title'] ?? '', 255);
            if ($rowTitle !== '') {
                $rowLabels[] = $rowTitle;
            }
        }

        if ($rowLabels === [] && $columnLabels === []) {
            return null;
        }

        return [
            'id' => $this->generateFieldId(),
            'name' => $title,
            'type' => 'matrix',
            'required' => (bool) ($questions[0]['required'] ?? false),
            'hidden' => false,
            'rows' => array_values($rowLabels),
            'columns' => array_values($columnLabels),
        ];
    }

    private function mapTextItem(array $item): ?array
    {
        $title = $this->sanitizeText($item['title'] ?? '', 255);
        $description = $this->sanitizeText($item['description'] ?? '', 8000);

        $content = '';
        if ($title !== '') {
            $content .= '<p><strong>' . e($title) . '</strong></p>';
        }
        if ($description !== '') {
            $content .= '<p>' . e($description) . '</p>';
        }

        if ($content === '') {
            return null;
        }

        return [
            'id' => $this->generateFieldId(),
            'name' => $title ?: 'Text',
            'type' => 'nf-text',
            'content' => $content,
        ];
    }

    private function mapPageBreak(): array
    {
        return [
            'id' => $this->generateFieldId(),
            'name' => 'Page Break',
            'type' => 'nf-page-break',
        ];
    }

    private function baseProperty(string $name, string $type, bool $required): array
    {
        return [
            'id' => $this->generateFieldId(),
            'name' => $name,
            'type' => $type,
            'required' => $required,
            'hidden' => false,
        ];
    }
}
