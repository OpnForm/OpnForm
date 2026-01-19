<?php

namespace App\Rules\PropertyValidators;

/**
 * Interface for property validators used in single-pass form validation.
 * Each validator handles a specific aspect of property validation.
 */
interface PropertyValidatorInterface
{
    /**
     * Validate a single property.
     *
     * @param array $property The property data to validate
     * @param int $index The index of the property in the properties array
     * @param array $context Additional context (properties array, workspace, etc.)
     * @return array<string, string> Field => error message pairs (empty if valid)
     */
    public function validate(array $property, int $index, array $context): array;
}
