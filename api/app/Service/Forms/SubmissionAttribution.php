<?php

namespace App\Service\Forms;

class SubmissionAttribution
{
    public const MAX_VALUE_LENGTH = 2048;

    public const PARAMETERS = [
        'utm_source',
        'utm_medium',
        'utm_campaign',
        'utm_id',
        'utm_term',
        'utm_content',
        'utm_source_platform',
        'utm_creative_format',
        'utm_marketing_tactic',
        'gclid',
        'gbraid',
        'wbraid',
        'dclid',
        'fbclid',
        'ttclid',
        'msclkid',
    ];

    /**
     * Keep only supported, non-empty attribution values.
     */
    public function sanitize(mixed $parameters): array
    {
        if (!is_array($parameters)) {
            return [];
        }

        $attribution = [];

        foreach (self::PARAMETERS as $parameter) {
            $value = $parameters[$parameter] ?? null;

            if (!is_string($value) || trim($value) === '') {
                continue;
            }

            if (mb_strlen($value) > self::MAX_VALUE_LENGTH) {
                continue;
            }

            $attribution[$parameter] = $value;
        }

        return $attribution;
    }

    public static function columnId(string $parameter): string
    {
        return 'meta.attribution.' . $parameter;
    }

    public static function parameterFromColumnId(string $columnId): ?string
    {
        $prefix = 'meta.attribution.';
        if (!str_starts_with($columnId, $prefix)) {
            return null;
        }

        $parameter = substr($columnId, strlen($prefix));

        return in_array($parameter, self::PARAMETERS, true) ? $parameter : null;
    }
}
