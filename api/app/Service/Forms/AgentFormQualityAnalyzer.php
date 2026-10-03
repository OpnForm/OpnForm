<?php

namespace App\Service\Forms;

use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AgentFormQualityAnalyzer
{
    private const MAX_WARNINGS = 50;

    private const BLOCKING_CODES = [
        'machine_like_label',
        'markdown_text_content',
    ];

    private const RAW_LABELS = [
        'name',
        'email',
        'phone',
        'subject',
        'message',
        'website',
        'company',
        'address',
        'comment',
        'feedback',
    ];

    private const GENERIC_SUBMIT_LABELS = [
        '',
        'submit',
        'send',
        'soumettre',
        'envoyer',
    ];

    public function analyze(array $definition): array
    {
        $warnings = [];
        $properties = collect($definition['properties'] ?? [])
            ->filter(fn ($property): bool => is_array($property) && ! ($property['hidden'] ?? false));
        $inputProperties = $properties
            ->filter(fn (array $property): bool => in_array($property['type'] ?? null, AgentFormFieldCatalog::INPUT_TYPES, true));

        if ($inputProperties->count() > 1 && ! $this->hasVisibleIntroduction($properties->all())) {
            $warnings[] = $this->warning(
                'missing_visible_intro',
                'Add a concise nf-text heading and supporting sentence unless this form is intentionally minimal.',
                'properties',
            );
        }

        foreach ($properties as $index => $property) {
            if (($property['type'] ?? null) !== 'nf-text') {
                continue;
            }

            if ($this->looksLikeMarkdown((string) ($property['content'] ?? ''))) {
                $warnings[] = $this->warning(
                    'markdown_text_content',
                    'Replace Markdown with sanitized HTML in nf-text content, for example <h1>Contact us</h1><p>How can we help?</p>.',
                    "properties.{$index}.content",
                );
            }
        }

        foreach ($inputProperties as $index => $property) {
            $path = "properties.{$index}";
            $label = Str::squish(strip_tags((string) ($property['name'] ?? '')));
            $normalizedLabel = Str::lower($label);

            if ($this->looksLikeRawLabel($label)) {
                $warnings[] = $this->warning(
                    'machine_like_label',
                    "Replace the raw label [{$label}] with clear respondent-facing copy in sentence case.",
                    $path.'.name',
                );
            }

            $placeholder = Str::squish((string) ($property['placeholder'] ?? ''));
            if ($placeholder === '' && $this->wouldBenefitFromPlaceholder($property)) {
                $warnings[] = $this->warning(
                    'missing_helpful_placeholder',
                    'Add a useful example or expected format as the placeholder, without replacing the visible label.',
                    $path.'.placeholder',
                );
            } elseif ($placeholder !== '' && $this->placeholderRepeatsLabel($placeholder, $normalizedLabel)) {
                $warnings[] = $this->warning(
                    'placeholder_repeats_label',
                    'Replace the placeholder with a useful example or format, or remove it if it adds no information.',
                    $path.'.placeholder',
                );
            }

            if (($property['type'] ?? null) === 'text'
                && ! ($property['multi_lines'] ?? false)
                && $this->looksLikeLongAnswer($normalizedLabel)) {
                $warnings[] = $this->warning(
                    'long_answer_should_be_multiline',
                    'Set multi_lines to true for this message, comment, description, or other long-answer field.',
                    $path.'.multi_lines',
                );
            }

            if (count($warnings) >= self::MAX_WARNINGS) {
                break;
            }
        }

        $submitLabel = Str::lower(Str::squish((string) ($definition['submit_button_text'] ?? '')));
        if (in_array($submitLabel, self::GENERIC_SUBMIT_LABELS, true)) {
            $warnings[] = $this->warning(
                'generic_submit_label',
                'Use a contextual action label such as Send message, Request a quote, or Join the waitlist.',
                'submit_button_text',
            );
        }

        if ($this->hasGenericCompletionMessage((string) ($definition['submitted_text'] ?? ''))) {
            $warnings[] = $this->warning(
                'generic_completion_message',
                'Write a specific completion message that confirms what happened and, when known, what comes next.',
                'submitted_text',
            );
        }

        return array_slice($warnings, 0, self::MAX_WARNINGS);
    }

    public function assertReadyForAgentPersistence(array $definition): void
    {
        $blockingWarnings = collect($this->analyze($definition))
            ->where('blocking', true)
            ->values();

        if ($blockingWarnings->isEmpty()) {
            return;
        }

        throw ValidationException::withMessages([
            'definition' => $blockingWarnings
                ->map(fn (array $warning): string => "{$warning['path']}: {$warning['message']}")
                ->all(),
        ]);
    }

    private function hasVisibleIntroduction(array $properties): bool
    {
        foreach ($properties as $property) {
            if (($property['type'] ?? null) !== 'nf-text') {
                continue;
            }

            if (Str::squish(strip_tags((string) ($property['content'] ?? ''))) !== '') {
                return true;
            }
        }

        return false;
    }

    private function looksLikeRawLabel(string $label): bool
    {
        if (preg_match('/^[a-z0-9]+(?:[_-][a-z0-9]+)+$/', $label) === 1) {
            return true;
        }

        return $label === Str::lower($label) && in_array($label, self::RAW_LABELS, true);
    }

    private function looksLikeMarkdown(string $content): bool
    {
        return preg_match('/(^|\n)\s{0,3}#{1,6}\s+\S/m', $content) === 1
            || preg_match('/\*\*[^*\n]+\*\*/', $content) === 1
            || preg_match('/__[^_\n]+__/', $content) === 1
            || preg_match('/(^|\n)\s*```/m', $content) === 1
            || preg_match('/(?<!!)\[[^\]\n]+\]\([^)\n]+\)/', $content) === 1;
    }

    private function wouldBenefitFromPlaceholder(array $property): bool
    {
        if (in_array($property['type'] ?? null, ['email', 'phone_number', 'url'], true)) {
            return true;
        }

        return ($property['type'] ?? null) === 'text' && ($property['multi_lines'] ?? false);
    }

    private function placeholderRepeatsLabel(string $placeholder, string $normalizedLabel): bool
    {
        $normalizedPlaceholder = Str::lower(trim($placeholder, " \t\n\r\0\x0B.:"));

        return $normalizedPlaceholder === $normalizedLabel
            || in_array($normalizedPlaceholder, ["enter {$normalizedLabel}", "your {$normalizedLabel}"], true);
    }

    private function looksLikeLongAnswer(string $label): bool
    {
        return Str::contains($label, [
            'message',
            'comment',
            'feedback',
            'description',
            'details',
            'notes',
            'request',
            'demande',
            'commentaire',
            'description',
            'détails',
        ]);
    }

    private function hasGenericCompletionMessage(string $message): bool
    {
        $message = Str::lower(Str::squish(strip_tags($message)));

        return $message === ''
            || $message === 'thank you for your submission!'
            || Str::startsWith($message, 'amazing, we saved your answers.');
    }

    private function warning(string $code, string $message, string $path): array
    {
        return [
            'code' => $code,
            'message' => $message,
            'path' => $path,
            'blocking' => in_array($code, self::BLOCKING_CODES, true),
        ];
    }
}
