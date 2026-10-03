<?php

use App\Rules\PropertyValidators\SelectOptionsPropertyValidator;
use Tests\TestCase;

uses(TestCase::class);

describe('SelectOptionsPropertyValidator type filtering', function () {
    it('skips validation for non-select types', function () {
        $validator = new SelectOptionsPropertyValidator();
        $context = ['properties' => []];
        $property = [
            'id' => 'text_field',
            'name' => 'Text Field',
            'type' => 'text',
        ];
        $errors = $validator->validate($property, 0, $context);
        expect($errors)->toBeEmpty();
    });

    it('validates select type', function () {
        $validator = new SelectOptionsPropertyValidator();
        $context = ['properties' => []];
        $property = [
            'id' => 'select_field',
            'name' => 'Select Field',
            'type' => 'select',
            'select' => [
                'options' => [
                    ['id' => 'opt1', 'name' => 'Option 1'],
                ],
            ],
        ];
        $errors = $validator->validate($property, 0, $context);
        expect($errors)->toBeEmpty();
    });

    it('validates multi_select type', function () {
        $validator = new SelectOptionsPropertyValidator();
        $context = ['properties' => []];
        $property = [
            'id' => 'multi_select_field',
            'name' => 'Multi Select Field',
            'type' => 'multi_select',
            'multi_select' => [
                'options' => [
                    ['id' => 'opt1', 'name' => 'Option 1'],
                ],
            ],
        ];
        $errors = $validator->validate($property, 0, $context);
        expect($errors)->toBeEmpty();
    });
});

describe('SelectOptionsPropertyValidator display mode validation', function () {
    it('passes with valid display mode text_only', function () {
        $validator = new SelectOptionsPropertyValidator();
        $property = [
            'type' => 'select',
            'option_display_mode' => 'text_only',
            'select' => [
                'options' => [
                    ['id' => 'opt1', 'name' => 'Option 1'],
                ],
            ],
        ];
        $errors = $validator->validate($property, 0, []);
        expect($errors)->toBeEmpty();
    });

    it('passes with valid display mode text_and_image', function () {
        $validator = new SelectOptionsPropertyValidator();
        $property = [
            'type' => 'select',
            'option_display_mode' => 'text_and_image',
            'select' => [
                'options' => [
                    ['id' => 'opt1', 'name' => 'Option 1', 'image' => 'https://example.com/image.jpg'],
                ],
            ],
        ];
        $errors = $validator->validate($property, 0, []);
        expect($errors)->toBeEmpty();
    });

    it('passes with valid display mode image_only', function () {
        $validator = new SelectOptionsPropertyValidator();
        $property = [
            'type' => 'select',
            'option_display_mode' => 'image_only',
            'select' => [
                'options' => [
                    ['id' => 'opt1', 'name' => 'Option 1', 'image' => 'https://example.com/image.jpg'],
                ],
            ],
        ];
        $errors = $validator->validate($property, 0, []);
        expect($errors)->toBeEmpty();
    });

    it('fails with invalid display mode', function () {
        $validator = new SelectOptionsPropertyValidator();
        $property = [
            'type' => 'select',
            'option_display_mode' => 'invalid_mode',
            'select' => [
                'options' => [
                    ['id' => 'opt1', 'name' => 'Option 1'],
                ],
            ],
        ];
        $errors = $validator->validate($property, 0, []);
        expect($errors)->toHaveKey('option_display_mode');
        expect($errors['option_display_mode'])->toContain('text_only, text_and_image, image_only');
    });

    it('defaults to text_only when display mode not set', function () {
        $validator = new SelectOptionsPropertyValidator();
        $property = [
            'type' => 'select',
            'select' => [
                'options' => [
                    ['id' => 'opt1', 'name' => 'Option 1'],
                ],
            ],
        ];
        $errors = $validator->validate($property, 0, []);
        expect($errors)->toBeEmpty();
    });
});

describe('SelectOptionsPropertyValidator image size validation', function () {
    it('passes with valid image size sm', function () {
        $validator = new SelectOptionsPropertyValidator();
        $property = [
            'type' => 'select',
            'option_image_size' => 'sm',
            'select' => [
                'options' => [
                    ['id' => 'opt1', 'name' => 'Option 1'],
                ],
            ],
        ];
        $errors = $validator->validate($property, 0, []);
        expect($errors)->toBeEmpty();
    });

    it('passes with valid image size md', function () {
        $validator = new SelectOptionsPropertyValidator();
        $property = [
            'type' => 'select',
            'option_image_size' => 'md',
            'select' => [
                'options' => [
                    ['id' => 'opt1', 'name' => 'Option 1'],
                ],
            ],
        ];
        $errors = $validator->validate($property, 0, []);
        expect($errors)->toBeEmpty();
    });

    it('passes with valid image size lg', function () {
        $validator = new SelectOptionsPropertyValidator();
        $property = [
            'type' => 'select',
            'option_image_size' => 'lg',
            'select' => [
                'options' => [
                    ['id' => 'opt1', 'name' => 'Option 1'],
                ],
            ],
        ];
        $errors = $validator->validate($property, 0, []);
        expect($errors)->toBeEmpty();
    });

    it('fails with invalid image size', function () {
        $validator = new SelectOptionsPropertyValidator();
        $property = [
            'type' => 'select',
            'option_image_size' => 'xl',
            'select' => [
                'options' => [
                    ['id' => 'opt1', 'name' => 'Option 1'],
                ],
            ],
        ];
        $errors = $validator->validate($property, 0, []);
        expect($errors)->toHaveKey('option_image_size');
        expect($errors['option_image_size'])->toContain('sm, md, lg');
    });
});

describe('SelectOptionsPropertyValidator options array validation', function () {
    it('requires options when they are not set', function () {
        $validator = new SelectOptionsPropertyValidator();
        $property = [
            'type' => 'select',
        ];
        $errors = $validator->validate($property, 0, []);
        expect($errors)->toHaveKey('select.options');
        expect($errors['select.options'])->toBe('At least one option is required.');
    });

    it('fails when options is not an array', function () {
        $validator = new SelectOptionsPropertyValidator();
        $property = [
            'type' => 'select',
            'select' => [
                'options' => 'not-an-array',
            ],
        ];
        $errors = $validator->validate($property, 0, []);
        expect($errors)->toHaveKey('select.options');
        expect($errors['select.options'])->toBe('The options must be an array.');
    });

    it('allows an empty option list when respondents can create values', function () {
        $validator = new SelectOptionsPropertyValidator();

        expect($validator->validate([
            'type' => 'select',
            'allow_creation' => true,
            'select' => ['options' => []],
        ], 0, []))->toBeEmpty();

        expect($validator->validate([
            'type' => 'multi_select',
            'allow_creation' => true,
        ], 0, []))->toBeEmpty();
    });

    it('fails when options array is empty', function () {
        $validator = new SelectOptionsPropertyValidator();
        $property = [
            'type' => 'select',
            'select' => [
                'options' => [],
            ],
        ];
        $errors = $validator->validate($property, 0, []);
        expect($errors)->toHaveKey('select.options');
        expect($errors['select.options'])->toBe('At least one option is required.');
    });

    it('fails when option is not an array', function () {
        $validator = new SelectOptionsPropertyValidator();
        $property = [
            'type' => 'select',
            'select' => [
                'options' => ['just-a-string'],
            ],
        ];
        $errors = $validator->validate($property, 0, []);
        expect($errors)->toHaveKey('select.options.0.');
        expect($errors['select.options.0.'])->toBe('Option must be an array.');
    });
});

describe('SelectOptionsPropertyValidator option field validation', function () {
    it('fails when option name is missing', function () {
        $validator = new SelectOptionsPropertyValidator();
        $property = [
            'type' => 'select',
            'select' => [
                'options' => [
                    ['id' => 'opt1'],
                ],
            ],
        ];
        $errors = $validator->validate($property, 0, []);
        expect($errors)->toHaveKey('select.options.0.name');
        expect($errors['select.options.0.name'])->toBe('The option name is required.');
    });

    it('fails when option name contains only whitespace', function () {
        $validator = new SelectOptionsPropertyValidator();
        $property = [
            'type' => 'select',
            'select' => [
                'options' => [
                    ['id' => 'opt1', 'name' => '   '],
                ],
            ],
        ];
        $errors = $validator->validate($property, 0, []);
        expect($errors)->toHaveKey('select.options.0.name');
    });

    it('allows an option id to be omitted because response values use option names', function () {
        $validator = new SelectOptionsPropertyValidator();
        $property = [
            'type' => 'select',
            'select' => [
                'options' => [
                    ['name' => 'Option 1'],
                ],
            ],
        ];
        $errors = $validator->validate($property, 0, []);
        expect($errors)->toBeEmpty();
    });

    it('allows an empty option id because it is not a response value', function () {
        $validator = new SelectOptionsPropertyValidator();
        $property = [
            'type' => 'select',
            'select' => [
                'options' => [
                    ['id' => '', 'name' => 'Option 1'],
                ],
            ],
        ];
        $errors = $validator->validate($property, 0, []);
        expect($errors)->toBeEmpty();
    });

    it('only reports the missing response name for an otherwise empty option', function () {
        $validator = new SelectOptionsPropertyValidator();
        $property = [
            'type' => 'select',
            'select' => [
                'options' => [
                    [], // Missing both id and name
                ],
            ],
        ];
        $errors = $validator->validate($property, 0, []);
        expect($errors)->toHaveKey('select.options.0.name');
        expect($errors)->toHaveCount(1);
    });

    it('accepts zero as a string option name', function () {
        $validator = new SelectOptionsPropertyValidator();
        $property = [
            'type' => 'select',
            'select' => [
                'options' => [
                    ['id' => '0', 'name' => '0'],
                ],
            ],
        ];

        expect($validator->validate($property, 0, []))->toBeEmpty();
    });

    it('rejects a non-string option name', function () {
        $validator = new SelectOptionsPropertyValidator();
        $property = [
            'type' => 'select',
            'select' => [
                'options' => [
                    ['id' => 'opt1', 'name' => 123],
                ],
            ],
        ];

        $errors = $validator->validate($property, 0, []);
        expect($errors)->toHaveKey('select.options.0.name');
        expect($errors['select.options.0.name'])->toBe('The option name must be a string.');
    });
});

describe('SelectOptionsPropertyValidator image validation', function () {
    it('requires image when display mode is text_and_image', function () {
        $validator = new SelectOptionsPropertyValidator();
        $property = [
            'type' => 'select',
            'option_display_mode' => 'text_and_image',
            'select' => [
                'options' => [
                    ['id' => 'opt1', 'name' => 'Option 1'],
                ],
            ],
        ];
        $errors = $validator->validate($property, 0, []);
        expect($errors)->toHaveKey('select.options.0.image');
        expect($errors['select.options.0.image'])->toBe('The image field is required for select options.');
    });

    it('requires image when display mode is image_only', function () {
        $validator = new SelectOptionsPropertyValidator();
        $property = [
            'type' => 'select',
            'option_display_mode' => 'image_only',
            'select' => [
                'options' => [
                    ['id' => 'opt1', 'name' => 'Option 1'],
                ],
            ],
        ];
        $errors = $validator->validate($property, 0, []);
        expect($errors)->toHaveKey('select.options.0.image');
        expect($errors['select.options.0.image'])->toBe('The image field is required for select options.');
    });

    it('does not require image when display mode is text_only', function () {
        $validator = new SelectOptionsPropertyValidator();
        $property = [
            'type' => 'select',
            'option_display_mode' => 'text_only',
            'select' => [
                'options' => [
                    ['id' => 'opt1', 'name' => 'Option 1'],
                ],
            ],
        ];
        $errors = $validator->validate($property, 0, []);
        expect($errors)->toBeEmpty();
    });

    it('fails when image is not a valid URL', function () {
        $validator = new SelectOptionsPropertyValidator();
        $property = [
            'type' => 'select',
            'option_display_mode' => 'text_and_image',
            'select' => [
                'options' => [
                    ['id' => 'opt1', 'name' => 'Option 1', 'image' => 'not-a-url'],
                ],
            ],
        ];
        $errors = $validator->validate($property, 0, []);
        expect($errors)->toHaveKey('select.options.0.image');
        expect($errors['select.options.0.image'])->toBe('The image must be a valid URL.');
    });

    it('fails when image URL is malformed', function () {
        $validator = new SelectOptionsPropertyValidator();
        $property = [
            'type' => 'select',
            'option_display_mode' => 'text_and_image',
            'select' => [
                'options' => [
                    ['id' => 'opt1', 'name' => 'Option 1', 'image' => 'javascript:alert(1)'],
                ],
            ],
        ];
        $errors = $validator->validate($property, 0, []);
        expect($errors)->toHaveKey('select.options.0.image');
        expect($errors['select.options.0.image'])->toBe('The image must be a valid URL.');
    });

    it('passes with valid HTTPS image URL', function () {
        $validator = new SelectOptionsPropertyValidator();
        $property = [
            'type' => 'select',
            'option_display_mode' => 'text_and_image',
            'select' => [
                'options' => [
                    ['id' => 'opt1', 'name' => 'Option 1', 'image' => 'https://example.com/image.png'],
                ],
            ],
        ];
        $errors = $validator->validate($property, 0, []);
        expect($errors)->toBeEmpty();
    });

    it('passes with valid HTTP image URL', function () {
        $validator = new SelectOptionsPropertyValidator();
        $property = [
            'type' => 'select',
            'option_display_mode' => 'text_and_image',
            'select' => [
                'options' => [
                    ['id' => 'opt1', 'name' => 'Option 1', 'image' => 'http://example.com/image.jpg'],
                ],
            ],
        ];
        $errors = $validator->validate($property, 0, []);
        expect($errors)->toBeEmpty();
    });

    it('validates image URL in text_only mode when provided', function () {
        $validator = new SelectOptionsPropertyValidator();
        $property = [
            'type' => 'select',
            'option_display_mode' => 'text_only',
            'select' => [
                'options' => [
                    ['id' => 'opt1', 'name' => 'Option 1', 'image' => 'not-a-valid-url'],
                ],
            ],
        ];
        $errors = $validator->validate($property, 0, []);
        expect($errors)->toHaveKey('select.options.0.image');
        expect($errors['select.options.0.image'])->toBe('The image must be a valid URL.');
    });
});

describe('SelectOptionsPropertyValidator multi_select type', function () {
    it('validates multi_select options correctly', function () {
        $validator = new SelectOptionsPropertyValidator();
        $property = [
            'type' => 'multi_select',
            'option_display_mode' => 'text_and_image',
            'multi_select' => [
                'options' => [
                    ['id' => 'opt1', 'name' => 'Option 1', 'image' => 'https://example.com/1.png'],
                    ['id' => 'opt2', 'name' => 'Option 2', 'image' => 'https://example.com/2.png'],
                ],
            ],
        ];
        $errors = $validator->validate($property, 0, []);
        expect($errors)->toBeEmpty();
    });

    it('allows multi_select options without internal ids', function () {
        $validator = new SelectOptionsPropertyValidator();
        $property = [
            'type' => 'multi_select',
            'multi_select' => [
                'options' => [
                    ['name' => 'Missing ID'],
                ],
            ],
        ];
        $errors = $validator->validate($property, 0, []);
        expect($errors)->toBeEmpty();
    });

    it('fails when multi_select options is empty', function () {
        $validator = new SelectOptionsPropertyValidator();
        $property = [
            'type' => 'multi_select',
            'multi_select' => [
                'options' => [],
            ],
        ];
        $errors = $validator->validate($property, 0, []);
        expect($errors)->toHaveKey('multi_select.options');
        expect($errors['multi_select.options'])->toBe('At least one option is required.');
    });
});

describe('SelectOptionsPropertyValidator multiple options validation', function () {
    it('validates all options and collects all errors', function () {
        $validator = new SelectOptionsPropertyValidator();
        $property = [
            'type' => 'select',
            'option_display_mode' => 'text_and_image',
            'select' => [
                'options' => [
                    ['id' => 'opt1', 'name' => 'Option 1'], // Missing image
                    ['id' => 'opt2'], // Missing name and image
                    ['name' => 'Option 3', 'image' => 'https://example.com/3.png'], // Optional id omitted
                ],
            ],
        ];
        $errors = $validator->validate($property, 0, []);
        expect($errors)->toHaveKey('select.options.0.image');
        expect($errors)->toHaveKey('select.options.1.name');
        expect($errors)->toHaveKey('select.options.1.image');
        expect($errors)->not->toHaveKey('select.options.2.id');
    });

    it('passes with multiple valid options', function () {
        $validator = new SelectOptionsPropertyValidator();
        $property = [
            'type' => 'select',
            'option_display_mode' => 'image_only',
            'option_image_size' => 'lg',
            'select' => [
                'options' => [
                    ['id' => 'opt1', 'name' => 'Option 1', 'image' => 'https://example.com/1.png'],
                    ['id' => 'opt2', 'name' => 'Option 2', 'image' => 'https://example.com/2.png'],
                    ['id' => 'opt3', 'name' => 'Option 3', 'image' => 'https://example.com/3.png'],
                ],
            ],
        ];
        $errors = $validator->validate($property, 0, []);
        expect($errors)->toBeEmpty();
    });
});
