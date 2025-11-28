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
