<?php

namespace App\Service\Forms;

class AgentFormAuthoringGuide
{
    private const RULES = [
        'context' => [
            'Infer the form purpose, audience, language, and tone from the request. Make conservative assumptions and produce a useful first draft instead of blocking on non-essential questions.',
            'Keep short forms short. Ask only for information that serves the stated purpose and do not duplicate questions.',
        ],
        'copy' => [
            'Unless the form is intentionally trivial, begin with a concise nf-text heading and one supporting sentence that explains the form purpose.',
            'Use human-facing labels in sentence case. Use short noun labels for identity fields and direct, unbiased questions for surveys; never expose raw identifiers or snake_case.',
            'Use placeholders only for a useful example or expected format. Never use a placeholder instead of a visible label or merely repeat the label.',
            'Use help text only for constraints, unfamiliar information, formatting guidance, or why the information is requested.',
        ],
        'fields' => [
            'Choose the most specific supported field type. Use text with multi_lines true for messages, comments, descriptions, and other long answers.',
            'Mark only essential fields as required. For choice fields, provide clear, non-overlapping options and use visible choices for small option sets when appropriate.',
        ],
        'layout' => [
            'In classic mode, pair at most two related short fields on a row, keep long answers full width, and split longer forms by meaningful sections rather than arbitrary field counts.',
            'In focused mode, keep one concise question or explanation per step and follow the focused presentation constraints from the field catalog.',
            'Use clean neutral style defaults when no brand direction is supplied. Do not invent brand colors, logos, or decorative media.',
        ],
        'completion' => [
            'Use a contextual submit label such as Send message, Request a quote, or Join the waitlist instead of a generic Submit label.',
            'Write a specific completion message that confirms what happened and, when known, what comes next.',
        ],
        'integrity' => [
            'Keep language, capitalization, terminology, tone, and option style consistent throughout the form.',
            'Do not invent legal consent, marketing opt-ins, sensitive questions, response times, eligibility claims, or business commitments.',
        ],
    ];

    public static function reference(): array
    {
        return [
            'objective' => 'Create a polished respondent-facing form, not merely the smallest valid schema.',
            'rules' => self::RULES,
            'validation' => 'Treat quality warnings from validate_form_definition as non-blocking editorial guidance. Correct relevant warnings before creating or saving, while preserving intentional user choices.',
        ];
    }

    public static function completeFormPrompt(): string
    {
        return self::promptForGroups(array_keys(self::RULES));
    }

    public static function fieldsPrompt(): string
    {
        return self::promptForGroups(['context', 'copy', 'fields', 'layout', 'integrity']);
    }

    private static function promptForGroups(array $groups): string
    {
        $lines = collect($groups)
            ->flatMap(fn (string $group): array => self::RULES[$group])
            ->map(fn (string $rule): string => '- '.$rule)
            ->implode("\n");

        return "Form authoring quality baseline:\n{$lines}";
    }
}
