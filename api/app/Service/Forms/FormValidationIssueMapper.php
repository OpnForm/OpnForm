<?php

namespace App\Service\Forms;

final class FormValidationIssueMapper
{
    public const MAX_ISSUES = 100;

    private const AGGREGATE_MESSAGES = [
        'One or more properties have validation errors.',
        'One or more computed variables have validation errors.',
    ];

    /**
     * @param  array<string, array<int, string>>  $errors
     * @return array<int, array{code: string, path: string, message: string, meta: array<string, mixed>}>
     */
    public function fromErrors(array $errors): array
    {
        $issues = [];

        foreach ($errors as $path => $messages) {
            foreach ((array) $messages as $message) {
                if (! is_string($message) || in_array($message, self::AGGREGATE_MESSAGES, true)) {
                    continue;
                }

                $code = $this->codeFor($path, $message);
                $issues[] = [
                    'code' => $code,
                    'path' => $path,
                    'message' => $message,
                    'meta' => $this->metaFor($code, $message),
                ];

                if (count($issues) >= self::MAX_ISSUES) {
                    return $issues;
                }
            }
        }

        return $issues;
    }

    public function summary(int $issueCount): string
    {
        return $issueCount === 1
            ? 'The form contains one issue that must be fixed before saving.'
            : "The form contains {$issueCount} issues that must be fixed before saving.";
    }

    /** @param array<string, array<int, string>> $errors */
    public function count(array $errors): int
    {
        $count = 0;

        foreach ($errors as $messages) {
            foreach ((array) $messages as $message) {
                if (is_string($message) && ! in_array($message, self::AGGREGATE_MESSAGES, true)) {
                    $count++;
                }
            }
        }

        return $count;
    }

    /**
     * Laravel MCP serializes validation messages without their attribute keys.
     * Prefixing messages here preserves the exact repair target for agents.
     *
     * @param  array<string, array<int, string>>  $errors
     * @return array<string, array<int, string>>
     */
    public function pathErrors(array $errors): array
    {
        $localizedErrors = [];

        foreach ($errors as $path => $messages) {
            foreach ((array) $messages as $message) {
                if (! is_string($message) || in_array($message, self::AGGREGATE_MESSAGES, true)) {
                    continue;
                }

                $localizedErrors[$path][] = "{$path}: {$message}";
            }
        }

        return $localizedErrors;
    }

    private function codeFor(string $path, string $message): string
    {
        $normalized = strtolower($message);

        return match (true) {
            str_contains($normalized, 'no longer exists'),
            str_contains($normalized, 'unknown field') => 'unknown_reference',
            str_contains($normalized, 'reference type must be') => 'reference_type_mismatch',
            str_contains($normalized, 'cannot reference itself'),
            str_contains($normalized, 'same field') => 'self_reference',
            str_contains($normalized, 'circular dependency') => 'cyclic_dependency',
            str_contains($normalized, 'dependency chain is too deep') => 'dependency_chain_depth_exceeded',
            str_contains($normalized, 'cannot contain more than') => 'condition_count_exceeded',
            str_contains($normalized, 'cannot be nested more than') => 'condition_depth_exceeded',
            str_contains($normalized, 'only available for custom validation'),
            str_contains($normalized, 'operator') && str_contains($normalized, 'not available') => 'unsupported_operator',
            str_contains($normalized, 'already used'),
            str_contains($normalized, 'duplicate') => str_ends_with($path, '.id') ? 'duplicate_id' : 'duplicate_value',
            str_contains($normalized, 'formula') || str_contains($path, '.formula') => 'formula_error',
            str_contains($path, '.logic') => 'invalid_logic',
            default => 'invalid_definition',
        };
    }

    /** @return array<string, mixed> */
    private function metaFor(string $code, string $message): array
    {
        if ($code === 'reference_type_mismatch' && preg_match('/for \[([^\]]+)]/', $message, $matches) === 1) {
            return ['reference_id' => $matches[1]];
        }

        if ($code === 'unknown_reference' && preg_match('/\[([^\]]+)]/', $message, $matches) === 1) {
            return ['reference_id' => $matches[1]];
        }

        if ($code === 'unknown_reference' && preg_match('/"([^"]+)"/', $message, $matches) === 1) {
            return ['reference_id' => $matches[1]];
        }

        return [];
    }
}
