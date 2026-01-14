<?php

namespace App\Models;

use Mpociot\Versionable\Version as BaseVersion;
use App\Contracts\VersionableNestedDiff;

class Version extends BaseVersion
{
    public function getModel()
    {
        $model = parent::getModel();

        if (is_object($model) && method_exists($model, 'getCasts')) {
            foreach ($model->getCasts() as $attribute => $cast) {
                $value = $model->getAttribute($attribute);

                if (in_array($cast, ['array', 'json'], true)) {
                    if ($value === null || $value === '') {
                        $model->setAttribute($attribute, []);
                    } elseif (is_string($value)) {
                        $decoded = json_decode($value, true);
                        if (json_last_error() === JSON_ERROR_NONE) {
                            $model->setAttribute($attribute, $decoded ?? []);
                        }
                    }
                } elseif ($cast === 'object') {
                    if ($value === null || $value === '') {
                        $model->setAttribute($attribute, (object) []);
                    } elseif (is_string($value)) {
                        $decoded = json_decode($value);
                        if (json_last_error() === JSON_ERROR_NONE) {
                            $model->setAttribute($attribute, $decoded ?? (object) []);
                        }
                    }
                }
            }
        }

        return $model;
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Diff the attributes of this version model against another version.
     * If no version is provided, it will be diffed against the current version.
     *
     * @param Version|null $againstVersion
     * @return array
     */
    public function diff(BaseVersion $againstVersion = null)
    {
        // Use the parent's diff for the base behavior (timestamps filtered, attribute-level diffs)
        $diffArray = parent::diff($againstVersion);

        $model = $this->getModel();

        // If the model opts-in, shrink specified fields to only the changed nested keys
        if ($model instanceof VersionableNestedDiff) {
            $target = $againstVersion
                ? $againstVersion->getModel()
                : $this->versionable()->withTrashed()->first()->currentVersion()->getModel();

            foreach ($model->getVersionNestedDiffFields() as $field) {
                $targetValue = $this->normalizeForNestedDiff($target->getAttribute($field));
                $modelValue = $this->normalizeForNestedDiff($model->getAttribute($field));

                if (is_array($targetValue) && is_array($modelValue)) {
                    $nestedDiff = $this->arrayRecursiveAssocDiff($targetValue, $modelValue);
                    if (!empty($nestedDiff)) {
                        $diffArray[$field] = $nestedDiff;
                    } else {
                        unset($diffArray[$field]);
                    }
                }
            }
        }

        return $diffArray;
    }

    /**
     * Normalize values for nested diffing (arrays/objects/JSON -> array).
     */
    protected function normalizeForNestedDiff($value)
    {
        if ($value === null || $value === '') {
            return [];
        }
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                return $decoded ?? [];
            }
        }
        if (is_object($value)) {
            return json_decode(json_encode($value), true) ?? [];
        }
        return $value;
    }

    /**
     * Compute an associative deep diff of two arrays:
     * returns keys from $a whose values differ from $b, with values taken from $a.
     */
    protected function arrayRecursiveAssocDiff(array $a, array $b): array
    {
        $diff = [];
        foreach ($a as $key => $valueA) {
            $hasKeyInB = array_key_exists($key, $b);
            $valueB = $hasKeyInB ? $b[$key] : null;

            if (is_array($valueA) && is_array($valueB)) {
                $child = $this->arrayRecursiveAssocDiff($valueA, $valueB);
                if (!empty($child)) {
                    $diff[$key] = $child;
                }
            } else {
                if (!$hasKeyInB || $valueA !== $valueB) {
                    $diff[$key] = $valueA;
                }
            }
        }
        return $diff;
    }
}
