<?php

namespace App\Service\Forms;

use Illuminate\Support\Str;
use Stevebauman\Purify\Facades\Purify;

class FormDataNormalizer
{
    /**
     * Normalize data shared by HTTP requests, MCP tools, and draft claims.
     */
    public function normalize(array $data, bool $backfillPropertyIds = false): array
    {
        if (isset($data['title']) && is_string($data['title'])) {
            $data['title'] = Str::substr(trim($data['title']), 0, 255);
        }

        if (isset($data['properties']) && is_array($data['properties'])) {
            $data['properties'] = $this->normalizeProperties($data['properties'], $backfillPropertyIds);
        }

        return $data;
    }

    public function normalizeProperties(array $properties, bool $backfillIds = false): array
    {
        return collect($properties)->map(function ($property) use ($backfillIds) {
            if (! is_array($property)) {
                return $property;
            }

            if ($backfillIds && empty($property['id'])) {
                $property['id'] = Str::uuid()->toString();
            }

            if (isset($property['name']) && is_string($property['name'])) {
                $property['name'] = trim(strip_tags($property['name']));
            }

            if (isset($property['help']) && is_string($property['help'])) {
                $property['help'] = Purify::clean($property['help']);

                if (strip_tags($property['help']) === '') {
                    $property['help'] = null;
                }
            }

            return $this->normalizeSelectOptionIds($property);
        })->values()->all();
    }

    private function normalizeSelectOptionIds(array $property): array
    {
        $type = $property['type'] ?? null;

        if (! in_array($type, ['select', 'multi_select'], true)) {
            return $property;
        }

        if (! isset($property[$type]['options']) || ! is_array($property[$type]['options'])) {
            return $property;
        }

        $property[$type]['options'] = array_map(function ($option) {
            if (! is_array($option) || ! empty($option['id'] ?? null)) {
                return $option;
            }

            if (! empty($option['name'] ?? null) && is_string($option['name'])) {
                $option['id'] = $option['name'];
            }

            return $option;
        }, $property[$type]['options']);

        return $property;
    }
}
