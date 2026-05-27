<?php

namespace App\Service\Forms;

use App\Models\Forms\Form;
use Illuminate\Support\Str;

class FormSpamContentAnalyzer
{
    private const FIELD_TEXT_KEYS = [
        'name',
        'type',
        'placeholder',
        'help',
        'helpful_text',
        'content',
        'image_block',
        'prefill',
    ];

    public function keywordScanText(Form $form): string
    {
        $parts = [
            $form->title,
            $form->description,
            $form->submit_button_text,
            $form->submitted_text,
            $this->collectText($form->seo_meta ?? []),
        ];

        foreach ($this->visibleProperties($form) as $field) {
            foreach (self::FIELD_TEXT_KEYS as $key) {
                if (array_key_exists($key, $field)) {
                    $parts[] = $this->collectText($field[$key]);
                }
            }

            $parts[] = implode(' ', $this->extractUrls($field));
        }

        return $this->normalizeText(implode(' ', array_filter($parts)));
    }

    public function promptContent(Form $form): string
    {
        $content = [];
        $content[] = 'Title: ' . $this->normalizeText((string) $form->title);
        $content[] = 'Description: ' . $this->normalizeText((string) $form->description);
        $content[] = 'Submit Button: ' . $this->normalizeText((string) $form->submit_button_text);
        $content[] = 'Submitted Text: ' . $this->summarizeText((string) $form->submitted_text);
        $content[] = 'Active input fields: ' . $this->activeInputFieldCount($form);

        foreach ($this->visibleProperties($form) as $field) {
            $content[] = '- Field Name: ' . $this->summarizeText((string) ($field['name'] ?? 'N/A'));
            $content[] = '  Field Type: ' . $this->summarizeText((string) ($field['type'] ?? 'N/A'));

            if ($this->isDisabled($field)) {
                $content[] = '  Disabled: yes';
            }

            foreach (['placeholder', 'help', 'helpful_text', 'content', 'image_block'] as $key) {
                if (!empty($field[$key])) {
                    $label = Str::of($key)->replace('_', ' ')->title();
                    $content[] = "  {$label}: " . $this->summarizeText((string) $field[$key]);
                }
            }

            $urls = $this->extractUrls($field);
            if (!empty($urls)) {
                $content[] = '  External Links: ' . implode(', ', $urls);
            }
        }

        return implode("\n", $content);
    }

    public function activeInputFieldCount(Form $form): int
    {
        return collect($form->properties ?? [])
            ->filter(fn ($field) => is_array($field) && $this->isActiveInputField($field))
            ->count();
    }

    private function visibleProperties(Form $form): array
    {
        return collect($form->properties ?? [])
            ->filter(fn ($field) => is_array($field) && !$this->isHidden($field))
            ->values()
            ->all();
    }

    private function isActiveInputField(array $field): bool
    {
        $type = strtolower((string) ($field['type'] ?? ''));

        return $type !== ''
            && !str_starts_with($type, 'nf-')
            && !$this->isHidden($field)
            && !$this->isDisabled($field);
    }

    private function isHidden(array $field): bool
    {
        return filter_var($field['hidden'] ?? false, FILTER_VALIDATE_BOOLEAN);
    }

    private function isDisabled(array $field): bool
    {
        return filter_var($field['disabled'] ?? false, FILTER_VALIDATE_BOOLEAN);
    }

    private function collectText(mixed $value): string
    {
        if (is_string($value)) {
            return $this->normalizeText($value);
        }

        if (is_scalar($value)) {
            return (string) $value;
        }

        if (is_object($value)) {
            $value = (array) $value;
        }

        if (!is_array($value)) {
            return '';
        }

        return implode(' ', array_map(fn ($item) => $this->collectText($item), $value));
    }

    private function normalizeText(string $value): string
    {
        $decoded = html_entity_decode($value, ENT_QUOTES | ENT_HTML5);
        $withoutTags = preg_replace('/<[^>]+>/', ' ', $decoded);

        return trim((string) preg_replace('/\s+/', ' ', $withoutTags));
    }

    private function summarizeText(string $value): string
    {
        return Str::limit($this->normalizeText($value), 1000);
    }

    private function extractUrls(mixed $value): array
    {
        $urls = [];

        foreach ($this->collectRawStrings($value) as $text) {
            if (preg_match_all('/href\s*=\s*["\']([^"\']+)["\']/i', $text, $matches)) {
                $urls = array_merge($urls, $matches[1]);
            }

            if (preg_match_all('~https?://[^\s"\'<>\\\\]+~i', $text, $matches)) {
                $urls = array_merge($urls, $matches[0]);
            }
        }

        return collect($urls)
            ->map(fn ($url) => rtrim($url, '.,);'))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    private function collectRawStrings(mixed $value): array
    {
        if (is_string($value)) {
            return [$value];
        }

        if (is_object($value)) {
            $value = (array) $value;
        }

        if (!is_array($value)) {
            return [];
        }

        $strings = [];
        foreach ($value as $item) {
            $strings = array_merge($strings, $this->collectRawStrings($item));
        }

        return $strings;
    }
}
