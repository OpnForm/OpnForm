<?php

namespace App\Rules\PropertyValidators;

use Illuminate\Support\Str;

/**
 * Validates core property fields that are common to ALL property types.
 */
class CorePropertyValidator implements PropertyValidatorInterface
{
    /**
     * Valid values for enumerated fields
     */
    private const VALID_HELP_POSITIONS = ['below_input', 'above_input'];
    private const VALID_WIDTHS = ['full', '1/2', '1/3', '2/3', '3/4', '1/4'];
    private const VALID_ALIGNS = ['left', 'center', 'right', 'justify'];
    private const VALID_IMAGE_LAYOUTS = ['between', 'left-small', 'right-small', 'left-split', 'right-split', 'background'];

    public function validate(array $property, int $index, array $context): array
    {
        $errors = [];
        $position = $index + 1; // 1-based for user-friendly messages

        // Required fields
        if (! isset($property['id']) || $property['id'] === '') {
            $errors['id'] = "The form block number {$position} is missing an id.";
        } elseif (! is_string($property['id'])) {
            $errors['id'] = "The form block number {$position} id must be a string.";
        } elseif (Str::length($property['id']) > 255) {
            $errors['id'] = "The form block number {$position} id must not exceed 255 characters.";
        }

        if (! isset($property['name']) || $property['name'] === '') {
            $errors['name'] = "The form block number {$position} is missing a name.";
        } elseif (! is_string($property['name'])) {
            $errors['name'] = "The form block number {$position} name must be a string.";
        } elseif (Str::length($property['name']) > 500) {
            $errors['name'] = "The form block number {$position} name must not exceed 500 characters.";
        }

        if (! isset($property['type']) || $property['type'] === '') {
            $errors['type'] = "The form block number {$position} is missing a type.";
        } elseif (! is_string($property['type'])) {
            $errors['type'] = "The form block number {$position} type must be a string.";
        }

        // Boolean fields (nullable)
        $booleanFields = ['hidden', 'required', 'multiple', 'use_toggle_switch'];
        foreach ($booleanFields as $field) {
            if (isset($property[$field]) && $property[$field] !== null && !is_bool($property[$field])) {
                // Allow 0, 1, "0", "1" as boolean
                if (!in_array($property[$field], [0, 1, '0', '1', true, false], true)) {
                    $errors[$field] = "The {$field} field must be a boolean.";
                }
            }
        }

        // Enum validations (only if value is set and not null)
        if (isset($property['help_position']) && $property['help_position'] !== null) {
            if (!in_array($property['help_position'], self::VALID_HELP_POSITIONS, true)) {
                $errors['help_position'] = "The help_position must be one of: " . implode(', ', self::VALID_HELP_POSITIONS);
            }
        }

        if (isset($property['width']) && $property['width'] !== null) {
            if (!in_array($property['width'], self::VALID_WIDTHS, true)) {
                $errors['width'] = "The width must be one of: " . implode(', ', self::VALID_WIDTHS);
            }
        }

        if (isset($property['align']) && $property['align'] !== null) {
            if (!in_array($property['align'], self::VALID_ALIGNS, true)) {
                $errors['align'] = "The align must be one of: " . implode(', ', self::VALID_ALIGNS);
            }
        }

        // Image validation (nested object)
        if (isset($property['image']) && ! is_array($property['image'])) {
            $errors['image'] = 'The image configuration must be an object.';
        } elseif (isset($property['image']) && is_array($property['image'])) {
            $image = $property['image'];

            if (isset($image['url']) && $image['url'] !== null && !filter_var($image['url'], FILTER_VALIDATE_URL)) {
                $errors['image.url'] = "The image URL must be a valid URL.";
            }

            if (isset($image['alt']) && $image['alt'] !== null) {
                if (!is_string($image['alt']) || Str::length($image['alt']) > 125) {
                    $errors['image.alt'] = "The image alt text must be a string with max 125 characters.";
                }
            }

            if (isset($image['layout']) && $image['layout'] !== null) {
                if (!in_array($image['layout'], self::VALID_IMAGE_LAYOUTS, true)) {
                    $errors['image.layout'] = "The image layout must be one of: " . implode(', ', self::VALID_IMAGE_LAYOUTS);
                }
            }

            // Focal point validation
            if (isset($image['focal_point']) && ! is_array($image['focal_point'])) {
                $errors['image.focal_point'] = 'The image focal point must be an object.';
            } elseif (isset($image['focal_point']) && is_array($image['focal_point'])) {
                $focalPoint = $image['focal_point'];

                if (isset($focalPoint['x']) && $focalPoint['x'] !== null) {
                    if (!is_numeric($focalPoint['x']) || $focalPoint['x'] < 0 || $focalPoint['x'] > 100) {
                        $errors['image.focal_point.x'] = "The focal point x must be a number between 0 and 100.";
                    }
                }

                if (isset($focalPoint['y']) && $focalPoint['y'] !== null) {
                    if (!is_numeric($focalPoint['y']) || $focalPoint['y'] < 0 || $focalPoint['y'] > 100) {
                        $errors['image.focal_point.y'] = "The focal point y must be a number between 0 and 100.";
                    }
                }
            }

            if (isset($image['brightness']) && $image['brightness'] !== null) {
                $brightness = $image['brightness'];
                if (! is_int($brightness)
                    && (! is_string($brightness) || preg_match('/^-?\d+$/', $brightness) !== 1)) {
                    $errors['image.brightness'] = "The image brightness must be an integer.";
                } elseif ((int) $brightness < -100 || (int) $brightness > 100) {
                    $errors['image.brightness'] = "The image brightness must be between -100 and 100.";
                }
            }
        }

        return $errors;
    }
}
