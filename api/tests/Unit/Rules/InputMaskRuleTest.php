<?php

use App\Rules\InputMaskRule;

it('can validate input mask', function () {
    $failCalled = false;
    $fail = function () use (&$failCalled) {
        $failCalled = true;
    };
    $validator = new InputMaskRule('(999) 999-9999');

    collect([
        '(123) 456-7890',
        '(098) 765-4321',
        '(999) 999-9999'
    ])->each(function ($value) use ($validator, $fail, &$failCalled) {
        $validator->validate('value', $value, $fail);
        $this->assertFalse($failCalled, "Validation should pass for value: " . $value);
        $failCalled = false; // Reset for the next iteration
    });

    // Test an invalid format
    $validator->validate('value', '1234567890', $fail);
    $this->assertTrue($failCalled, "Validation should fail for value: 1234567890");
});

it('can validate all nine mask', function () {
    $failCalled = false;
    $fail = function () use (&$failCalled) {
        $failCalled = true;
    };
    $validator = new InputMaskRule('9999999999');

    // Test a valid numeric input
    $validator->validate('value', '1234567890', $fail);
    $this->assertFalse($failCalled, "Validation should pass for numeric value");

    // Test an invalid non-numeric input
    $failCalled = false; // Reset
    $validator->validate('value', '123abc4567', $fail);
    $this->assertTrue($failCalled, "Validation should fail for non-numeric value");
});

it('can validate mask with mixed characters', function () {
    $failCalled = false;
    $fail = function () use (&$failCalled) {
        $failCalled = true;
    };
    $validator = new InputMaskRule('a*-999-a999');

    // Test valid inputs
    $validInputs = [
        'ab-123-d456',
        'c8-987-w111'
    ];

    foreach ($validInputs as $input) {
        $validator->validate('value', $input, $fail);
        $this->assertFalse($failCalled, "Validation should pass for valid input: " . $input);
        $failCalled = false; // Reset for next iteration
    }

    // Test invalid inputs
    $invalidInputs = [
        '1bc-123-d456', // First char not 'a'
        'abc-def-d456', // Middle part not '9'
        'abc-123-dabc', // Last part not '9'
        'abc-123-d45', // Missing char for 999
        'abc-123-d4567', // Extra char
        'abc123d456', // Missing hyphens
    ];

    foreach ($invalidInputs as $input) {
        $validator->validate('value', $input, $fail);
        $this->assertTrue($failCalled, "Validation should fail for invalid input: " . $input);
        $failCalled = false; // Reset for next iteration
    }
});

it('can validate mask with optional characters', function () {
    $failCalled = false;
    $fail = function () use (&$failCalled) {
        $failCalled = true;
    };
    // Mask: 999?999
    // Parsed: 9(required), 9(required), 9?(optional), 9(required), 9(required), 9(required)
    // Required: 5 digits (positions 1,2,4,5,6), Optional: 1 digit (position 3)
    $validator = new InputMaskRule('999?999');

    // Should accept 5 digits (all required, optional skipped)
    $validator->validate('value', '12345', $fail);
    $this->assertFalse($failCalled, "Validation should pass for 5 digits (all required)");
    $failCalled = false;

    // Should accept 6 digits (all required + optional filled)
    $validator->validate('value', '123456', $fail);
    $this->assertFalse($failCalled, "Validation should pass for 6 digits (all filled)");
    $failCalled = false;

    // Should fail with only 4 digits (not enough required - need at least 5)
    $validator->validate('value', '1234', $fail);
    $this->assertTrue($failCalled, "Validation should fail for only 4 digits (need 5 required)");
    $failCalled = false;

    // Should fail with only 3 digits (not enough required)
    $validator->validate('value', '123', $fail);
    $this->assertTrue($failCalled, "Validation should fail for only 3 digits");
    $failCalled = false;

    // Should fail with only 2 digits (not enough required)
    $validator->validate('value', '12', $fail);
    $this->assertTrue($failCalled, "Validation should fail for only 2 digits");
    $failCalled = false;

    // Should fail with 7 digits (too many - max is 6)
    $validator->validate('value', '1234567', $fail);
    $this->assertTrue($failCalled, "Validation should fail for 7 digits (exceeds max)");
});

it('can validate mask with optional at start', function () {
    $failCalled = false;
    $fail = function () use (&$failCalled) {
        $failCalled = true;
    };
    // Mask: ?999
    // Note: ? at start has no previous token, so it's ignored
    // Parsed: 9(required), 9(required), 9(required)
    // Required: 3 digits
    $validator = new InputMaskRule('?999');

    // Should accept 3 digits (all required)
    $validator->validate('value', '123', $fail);
    $this->assertFalse($failCalled, "Validation should pass for 3 digits");
    $failCalled = false;

    // Should fail with only 2 digits (not enough required - need 3)
    $validator->validate('value', '12', $fail);
    $this->assertTrue($failCalled, "Validation should fail for only 2 digits");
    $failCalled = false;

    // Should fail with 4 digits (too many - max is 3)
    $validator->validate('value', '1234', $fail);
    $this->assertTrue($failCalled, "Validation should fail for 4 digits (exceeds max)");
    $failCalled = false;

    // Should fail with non-numeric (mask expects only digits)
    $validator->validate('value', 'a123', $fail);
    $this->assertTrue($failCalled, "Validation should fail for non-numeric input");
});

it('can validate mask with optional middle character', function () {
    $failCalled = false;
    $fail = function () use (&$failCalled) {
        $failCalled = true;
    };
    // Mask: 99?9-9999
    // Parsed: 9(required), 9?(optional), 9(required), -(literal), 9(required), 9(required), 9(required), 9(required)
    // Required: 1 digit + 1 digit + 4 digits = 6 digits minimum
    // Optional: 1 digit (middle position)
    // Max: 7 digits (with optional filled)
    $validator = new InputMaskRule('99?9-9999');

    // Should accept with 2-digit prefix (optional skipped): 2 + 4 = 6 digits
    $validator->validate('value', '12-3456', $fail);
    $this->assertFalse($failCalled, "Validation should pass for 2-digit prefix (optional skipped)");
    $failCalled = false;

    // Should accept with 3-digit prefix (optional filled): 3 + 4 = 7 digits
    $validator->validate('value', '123-4567', $fail);
    $this->assertFalse($failCalled, "Validation should pass for 3-digit prefix (optional filled)");
    $failCalled = false;

    // Should fail with only 1 digit prefix (not enough required)
    $validator->validate('value', '1-2345', $fail);
    $this->assertTrue($failCalled, "Validation should fail for 1-digit prefix");
    $failCalled = false;

    // Should fail with incomplete suffix
    $validator->validate('value', '12-345', $fail);
    $this->assertTrue($failCalled, "Validation should fail for incomplete suffix");
});

it('can validate empty mask pattern', function () {
    $failCalled = false;
    $fail = function () use (&$failCalled) {
        $failCalled = true;
    };

    // Empty mask should be invalid (backend should prevent this, but test the rule)
    $validator = new InputMaskRule('');
    $validator->validate('value', 'any', $fail);
    // Empty mask pattern should fail validation
    $this->assertTrue($failCalled, "Empty mask should fail validation");
});

it('can validate mask with only literals', function () {
    $failCalled = false;
    $fail = function () use (&$failCalled) {
        $failCalled = true;
    };
    $validator = new InputMaskRule('()');

    // Should accept exact literal match
    $validator->validate('value', '()', $fail);
    $this->assertFalse($failCalled, "Validation should pass for exact literal match");
    $failCalled = false;

    // Should fail with different characters
    $validator->validate('value', '[]', $fail);
    $this->assertTrue($failCalled, "Validation should fail for different literals");
});

it('can validate mask containing only question marks', function () {
    $failCalled = false;
    $fail = function () use (&$failCalled) {
        $failCalled = true;
    };

    // Mask: ? (single question mark at start with nothing before it)
    // The ? has no previous token to make optional, so it's effectively an empty mask
    $validator = new InputMaskRule('?');

    // Empty value should pass (no tokens to validate)
    $validator->validate('value', '', $fail);
    $this->assertFalse($failCalled, "Empty value should pass for mask with only ?");
    $failCalled = false;

    // Any non-empty value should fail (no tokens to consume input)
    $validator->validate('value', 'a', $fail);
    $this->assertTrue($failCalled, "Non-empty value should fail for mask with only ?");
    $failCalled = false;

    // Mask: ??? (multiple question marks)
    // All ? have no previous tokens, so effectively empty mask
    $validator = new InputMaskRule('???');

    // Empty value should pass
    $validator->validate('value', '', $fail);
    $this->assertFalse($failCalled, "Empty value should pass for mask with only ???");
    $failCalled = false;

    // Any value should fail (no tokens to consume input)
    $validator->validate('value', '123', $fail);
    $this->assertTrue($failCalled, "Non-empty value should fail for mask with only ???");
});
