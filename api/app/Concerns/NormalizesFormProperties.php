<?php

namespace App\Concerns;

use Illuminate\Support\Str;
use Stevebauman\Purify\Facades\Purify;

trait NormalizesFormProperties
{
    protected function normalizeProperties(array $properties, bool $backfillIds = false): array
    {
        return collect($properties)->map(function ($field) use ($backfillIds) {
            if (!is_array($field)) {
                return $field;
            }

            if ($backfillIds && empty($field['id'])) {
                $field['id'] = Str::uuid()->toString();
            }

            if (isset($field['name']) && is_string($field['name'])) {
                $field['name'] = trim(strip_tags($field['name']));
            }

            if (isset($field['help']) && is_string($field['help'])) {
                $field['help'] = Purify::clean($field['help']);
                if (strip_tags($field['help']) === '') {
                    $field['help'] = null;
                }
            }

            return $this->normalizeSelectOptionIds($field);
        })->values()->all();
    }

    protected function normalizeSelectOptionIds(array $property): array
    {
        $type = $property['type'] ?? null;

        if (!in_array($type, ['select', 'multi_select'], true)) {
            return $property;
        }

        if (!isset($property[$type]['options']) || !is_array($property[$type]['options'])) {
            return $property;
        }

        $property[$type]['options'] = array_map(function ($option) {
            if (!is_array($option) || !empty($option['id'] ?? null)) {
                return $option;
            }
            if (!empty($option['name'] ?? null) && is_string($option['name'])) {
                $option['id'] = $option['name'];
            }

            return $option;
        }, $property[$type]['options']);

        return $property;
    }
}
