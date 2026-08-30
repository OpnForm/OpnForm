<?php

namespace App\Rules\PropertyValidators;

/**
 * Validates select and multi-select options including image support.
 * Ensures options have required fields and validates images based on display mode.
 */
class SelectOptionsPropertyValidator implements PropertyValidatorInterface
{
    /**
     * Valid option display modes.
     */
    private const OPTION_DISPLAY_MODES = ['text_only', 'text_and_image', 'image_only'];

    /**
     * Valid option image sizes.
     */
    private const OPTION_IMAGE_SIZES = ['sm', 'md', 'lg'];

    /**
     * Display modes that require images.
     */
    private const IMAGE_REQUIRED_MODES = ['text_and_image', 'image_only'];

    public function validate(array $property, int $index, array $context): array
    {
        $errors = [];
        $type = $property['type'] ?? null;

        // Only validate select and multi_select types
        if (!in_array($type, ['select', 'multi_select'])) {
            return $errors;
        }

        // Validate option_display_mode
        $optionDisplayMode = $property['option_display_mode'] ?? 'text_only';
        if (!in_array($optionDisplayMode, self::OPTION_DISPLAY_MODES)) {
            $errors['option_display_mode'] = 'The option display mode must be one of: ' . implode(', ', self::OPTION_DISPLAY_MODES) . '.';
        }

        // Validate option_image_size (only when images are enabled)
        if (isset($property['option_image_size'])) {
            if (!in_array($property['option_image_size'], self::OPTION_IMAGE_SIZES)) {
                $errors['option_image_size'] = 'The option image size must be one of: ' . implode(', ', self::OPTION_IMAGE_SIZES) . '.';
            }
        }

        // Validate options array
        $options = $property[$type]['options'] ?? null;
        $allowsCreation = ($property['allow_creation'] ?? false) === true;

        if (!is_array($options)) {
            if ($allowsCreation && $options === null) {
                return $errors;
            }

            $errors["{$type}.options"] = $options === null
                ? 'At least one option is required.'
                : 'The options must be an array.';
            return $errors;
        }

        if (count($options) < 1 && ! $allowsCreation) {
            $errors["{$type}.options"] = 'At least one option is required.';
            return $errors;
        }

        $requiresImage = in_array($optionDisplayMode, self::IMAGE_REQUIRED_MODES);

        // Validate each option
        foreach ($options as $optionIndex => $option) {
            $optionErrors = $this->validateOption($option, $optionIndex, $type, $requiresImage);
            foreach ($optionErrors as $field => $message) {
                $errors["{$type}.options.{$optionIndex}.{$field}"] = $message;
            }
        }

        return $errors;
    }

    /**
     * Validate a single option.
     */
    private function validateOption(mixed $option, int $optionIndex, string $type, bool $requiresImage): array
    {
        $errors = [];

        if (!is_array($option)) {
            $errors[''] = 'Option must be an array.';
            return $errors;
        }

        // Option names are the persisted response values and must be usable strings.
        // In particular, "0" is a valid option name and must not be treated as empty.
        if (! array_key_exists('name', $option)
            || $option['name'] === null
            || (is_string($option['name']) && trim($option['name']) === '')) {
            $errors['name'] = 'The option name is required.';
        } elseif (!is_string($option['name'])) {
            $errors['name'] = 'The option name must be a string.';
        }

        // Validate option image based on display mode
        $image = $option['image'] ?? null;

        // Image is required for text_and_image and image_only modes
        if ($requiresImage && empty($image)) {
            $errors['image'] = 'The image field is required for select options.';
        }

        // If image is provided, validate it's a valid URL
        if (!empty($image) && !filter_var($image, FILTER_VALIDATE_URL)) {
            $errors['image'] = 'The image must be a valid URL.';
        }

        return $errors;
    }
}
