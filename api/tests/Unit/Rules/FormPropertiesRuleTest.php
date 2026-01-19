<?php

use App\Rules\FormPropertiesRule;
use Tests\TestCase;

uses(TestCase::class);

describe('FormPropertiesRule', function () {
    describe('basic validation', function () {
        it('passes with valid properties', function () {
            $rules = [
                'properties' => ['required', 'array', new FormPropertiesRule()],
            ];

            $data = [
                'properties' => [
                    [
                        'id' => 'title',
                        'name' => 'Title',
                        'type' => 'text',
                    ],
                    [
                        'id' => 'email',
                        'name' => 'Email',
                        'type' => 'email',
                    ],
                ],
            ];

            $validator = $this->app['validator']->make($data, $rules);
            expect($validator->passes())->toBeTrue();
        });

        it('fails when properties is not an array', function () {
            $rules = [
                'properties' => ['required', 'array', new FormPropertiesRule()],
            ];

            $data = [
                'properties' => 'not-an-array',
            ];

            $validator = $this->app['validator']->make($data, $rules);
            expect($validator->passes())->toBeFalse();
        });

        it('fails when a property is not an array', function () {
            $rules = [
                'properties' => ['required', 'array', new FormPropertiesRule()],
            ];

            $data = [
                'properties' => [
                    'not-an-array',
                    [
                        'id' => 'title',
                        'name' => 'Title',
                        'type' => 'text',
                    ],
                ],
            ];

            $validator = $this->app['validator']->make($data, $rules);
            expect($validator->passes())->toBeFalse();
            expect($validator->errors()->has('properties.0'))->toBeTrue();
        });

        it('handles empty properties array per Laravel required rule', function () {
            // Note: Laravel's 'required' rule considers empty arrays as failing validation
            // This is expected behavior - forms should have at least one property
            $rules = [
                'properties' => ['required', 'array', new FormPropertiesRule()],
            ];

            $data = [
                'properties' => [],
            ];

            $validator = $this->app['validator']->make($data, $rules);
            // Empty array fails 'required' validation in Laravel
            expect($validator->passes())->toBeFalse();
        });
    });

    describe('core property validation', function () {
        it('fails when id is missing', function () {
            $rules = [
                'properties' => ['required', 'array', new FormPropertiesRule()],
            ];

            $data = [
                'properties' => [
                    [
                        'name' => 'Title',
                        'type' => 'text',
                    ],
                ],
            ];

            $validator = $this->app['validator']->make($data, $rules);
            expect($validator->passes())->toBeFalse();
            expect($validator->errors()->has('properties.0.id'))->toBeTrue();
        });

        it('fails when name is missing', function () {
            $rules = [
                'properties' => ['required', 'array', new FormPropertiesRule()],
            ];

            $data = [
                'properties' => [
                    [
                        'id' => 'title',
                        'type' => 'text',
                    ],
                ],
            ];

            $validator = $this->app['validator']->make($data, $rules);
            expect($validator->passes())->toBeFalse();
            expect($validator->errors()->has('properties.0.name'))->toBeTrue();
        });

        it('fails when type is missing', function () {
            $rules = [
                'properties' => ['required', 'array', new FormPropertiesRule()],
            ];

            $data = [
                'properties' => [
                    [
                        'id' => 'title',
                        'name' => 'Title',
                    ],
                ],
            ];

            $validator = $this->app['validator']->make($data, $rules);
            expect($validator->passes())->toBeFalse();
            expect($validator->errors()->has('properties.0.type'))->toBeTrue();
        });

        it('collects multiple missing field errors', function () {
            $rules = [
                'properties' => ['required', 'array', new FormPropertiesRule()],
            ];

            $data = [
                'properties' => [
                    [
                        // Missing all required fields
                    ],
                ],
            ];

            $validator = $this->app['validator']->make($data, $rules);
            expect($validator->passes())->toBeFalse();
            expect($validator->errors()->has('properties.0.id'))->toBeTrue();
            expect($validator->errors()->has('properties.0.name'))->toBeTrue();
            expect($validator->errors()->has('properties.0.type'))->toBeTrue();
        });

        it('validates width enum values', function () {
            $rules = [
                'properties' => ['required', 'array', new FormPropertiesRule()],
            ];

            $data = [
                'properties' => [
                    [
                        'id' => 'title',
                        'name' => 'Title',
                        'type' => 'text',
                        'width' => 'invalid-width',
                    ],
                ],
            ];

            $validator = $this->app['validator']->make($data, $rules);
            expect($validator->passes())->toBeFalse();
            expect($validator->errors()->has('properties.0.width'))->toBeTrue();
        });

        it('passes with valid width value', function () {
            $rules = [
                'properties' => ['required', 'array', new FormPropertiesRule()],
            ];

            $data = [
                'properties' => [
                    [
                        'id' => 'title',
                        'name' => 'Title',
                        'type' => 'text',
                        'width' => '1/2',
                    ],
                ],
            ];

            $validator = $this->app['validator']->make($data, $rules);
            expect($validator->passes())->toBeTrue();
        });

        it('validates help_position enum values', function () {
            $rules = [
                'properties' => ['required', 'array', new FormPropertiesRule()],
            ];

            $data = [
                'properties' => [
                    [
                        'id' => 'title',
                        'name' => 'Title',
                        'type' => 'text',
                        'help_position' => 'invalid',
                    ],
                ],
            ];

            $validator = $this->app['validator']->make($data, $rules);
            expect($validator->passes())->toBeFalse();
            expect($validator->errors()->has('properties.0.help_position'))->toBeTrue();
        });

        it('validates boolean fields accept valid boolean-like values', function () {
            $rules = [
                'properties' => ['required', 'array', new FormPropertiesRule()],
            ];

            $data = [
                'properties' => [
                    [
                        'id' => 'title',
                        'name' => 'Title',
                        'type' => 'text',
                        'hidden' => true,
                        'required' => false,
                    ],
                ],
            ];

            $validator = $this->app['validator']->make($data, $rules);
            expect($validator->passes())->toBeTrue();
        });
    });

    describe('image validation', function () {
        it('validates image URL format', function () {
            $rules = [
                'properties' => ['required', 'array', new FormPropertiesRule()],
            ];

            $data = [
                'properties' => [
                    [
                        'id' => 'title',
                        'name' => 'Title',
                        'type' => 'text',
                        'image' => [
                            'url' => 'not-a-valid-url',
                        ],
                    ],
                ],
            ];

            $validator = $this->app['validator']->make($data, $rules);
            expect($validator->passes())->toBeFalse();
            expect($validator->errors()->has('properties.0.image.url'))->toBeTrue();
        });

        it('passes with valid image URL', function () {
            $rules = [
                'properties' => ['required', 'array', new FormPropertiesRule()],
            ];

            $data = [
                'properties' => [
                    [
                        'id' => 'title',
                        'name' => 'Title',
                        'type' => 'text',
                        'image' => [
                            'url' => 'https://example.com/image.png',
                            'alt' => 'Example image',
                            'layout' => 'between',
                        ],
                    ],
                ],
            ];

            $validator = $this->app['validator']->make($data, $rules);
            expect($validator->passes())->toBeTrue();
        });

        it('validates image layout enum', function () {
            $rules = [
                'properties' => ['required', 'array', new FormPropertiesRule()],
            ];

            $data = [
                'properties' => [
                    [
                        'id' => 'title',
                        'name' => 'Title',
                        'type' => 'text',
                        'image' => [
                            'layout' => 'invalid-layout',
                        ],
                    ],
                ],
            ];

            $validator = $this->app['validator']->make($data, $rules);
            expect($validator->passes())->toBeFalse();
            expect($validator->errors()->has('properties.0.image.layout'))->toBeTrue();
        });

        it('validates focal point values are between 0 and 100', function () {
            $rules = [
                'properties' => ['required', 'array', new FormPropertiesRule()],
            ];

            $data = [
                'properties' => [
                    [
                        'id' => 'title',
                        'name' => 'Title',
                        'type' => 'text',
                        'image' => [
                            'focal_point' => [
                                'x' => 150,
                                'y' => 50,
                            ],
                        ],
                    ],
                ],
            ];

            $validator = $this->app['validator']->make($data, $rules);
            expect($validator->passes())->toBeFalse();
            expect($validator->errors()->has('properties.0.image.focal_point.x'))->toBeTrue();
        });
    });

    describe('type-specific validation', function () {
        it('validates text field max_char_limit must be integer', function () {
            $rules = [
                'properties' => ['required', 'array', new FormPropertiesRule()],
            ];

            $data = [
                'properties' => [
                    [
                        'id' => 'text',
                        'name' => 'Text Field',
                        'type' => 'text',
                        'max_char_limit' => 'not-an-integer',
                    ],
                ],
            ];

            $validator = $this->app['validator']->make($data, $rules);
            expect($validator->passes())->toBeFalse();
            expect($validator->errors()->has('properties.0.max_char_limit'))->toBeTrue();
        });

        it('validates text field max_char_limit minimum value', function () {
            $rules = [
                'properties' => ['required', 'array', new FormPropertiesRule()],
            ];

            $data = [
                'properties' => [
                    [
                        'id' => 'text',
                        'name' => 'Text Field',
                        'type' => 'text',
                        'max_char_limit' => 0,
                    ],
                ],
            ];

            $validator = $this->app['validator']->make($data, $rules);
            expect($validator->passes())->toBeFalse();
            expect($validator->errors()->has('properties.0.max_char_limit'))->toBeTrue();
        });

        it('passes with valid text field configuration', function () {
            $rules = [
                'properties' => ['required', 'array', new FormPropertiesRule()],
            ];

            $data = [
                'properties' => [
                    [
                        'id' => 'text',
                        'name' => 'Text Field',
                        'type' => 'text',
                        'max_char_limit' => 100,
                        'multi_lines' => true,
                    ],
                ],
            ];

            $validator = $this->app['validator']->make($data, $rules);
            expect($validator->passes())->toBeTrue();
        });

        it('validates date field boolean options', function () {
            $rules = [
                'properties' => ['required', 'array', new FormPropertiesRule()],
            ];

            $data = [
                'properties' => [
                    [
                        'id' => 'date',
                        'name' => 'Date Field',
                        'type' => 'date',
                        'with_time' => true,
                        'date_range' => false,
                        'prefill_today' => true,
                    ],
                ],
            ];

            $validator = $this->app['validator']->make($data, $rules);
            expect($validator->passes())->toBeTrue();
        });

        it('validates select field min_selection must be at least 0', function () {
            $rules = [
                'properties' => ['required', 'array', new FormPropertiesRule()],
            ];

            $data = [
                'properties' => [
                    [
                        'id' => 'select',
                        'name' => 'Select Field',
                        'type' => 'select',
                        'min_selection' => -1,
                    ],
                ],
            ];

            $validator = $this->app['validator']->make($data, $rules);
            expect($validator->passes())->toBeFalse();
            expect($validator->errors()->has('properties.0.min_selection'))->toBeTrue();
        });

        it('validates files max_file_size must be at least 1', function () {
            $rules = [
                'properties' => ['required', 'array', new FormPropertiesRule()],
            ];

            $data = [
                'properties' => [
                    [
                        'id' => 'files',
                        'name' => 'Files Field',
                        'type' => 'files',
                        'max_file_size' => 0.5,
                    ],
                ],
            ];

            $validator = $this->app['validator']->make($data, $rules);
            expect($validator->passes())->toBeFalse();
            expect($validator->errors()->has('properties.0.max_file_size'))->toBeTrue();
        });
    });

    describe('layout blocks', function () {
        it('passes for layout blocks with minimal fields', function () {
            $rules = [
                'properties' => ['required', 'array', new FormPropertiesRule()],
            ];

            $data = [
                'properties' => [
                    [
                        'id' => 'text-block',
                        'name' => 'Text Block',
                        'type' => 'nf-text',
                    ],
                    [
                        'id' => 'divider',
                        'name' => 'Divider',
                        'type' => 'nf-divider',
                    ],
                    [
                        'id' => 'page-break',
                        'name' => 'Page Break',
                        'type' => 'nf-page-break',
                    ],
                ],
            ];

            $validator = $this->app['validator']->make($data, $rules);
            expect($validator->passes())->toBeTrue();
        });
    });

    describe('multiple properties validation', function () {
        it('validates all properties and collects errors from each', function () {
            $rules = [
                'properties' => ['required', 'array', new FormPropertiesRule()],
            ];

            $data = [
                'properties' => [
                    [
                        'id' => 'title',
                        // Missing name
                        'type' => 'text',
                    ],
                    [
                        'id' => 'email',
                        'name' => 'Email',
                        // Missing type
                    ],
                    [
                        'id' => 'text',
                        'name' => 'Text',
                        'type' => 'text',
                        'width' => 'invalid',
                    ],
                ],
            ];

            $validator = $this->app['validator']->make($data, $rules);
            expect($validator->passes())->toBeFalse();
            expect($validator->errors()->has('properties.0.name'))->toBeTrue();
            expect($validator->errors()->has('properties.1.type'))->toBeTrue();
            expect($validator->errors()->has('properties.2.width'))->toBeTrue();
        });

        it('passes with multiple valid properties of different types', function () {
            $rules = [
                'properties' => ['required', 'array', new FormPropertiesRule()],
            ];

            $data = [
                'properties' => [
                    [
                        'id' => 'title',
                        'name' => 'Title',
                        'type' => 'text',
                    ],
                    [
                        'id' => 'text',
                        'name' => 'Description',
                        'type' => 'text',
                        'multi_lines' => true,
                        'max_char_limit' => 500,
                    ],
                    [
                        'id' => 'date',
                        'name' => 'Due Date',
                        'type' => 'date',
                        'with_time' => true,
                    ],
                    [
                        'id' => 'select',
                        'name' => 'Priority',
                        'type' => 'select',
                        'allow_creation' => false,
                    ],
                    [
                        'id' => 'divider',
                        'name' => 'Divider',
                        'type' => 'nf-divider',
                    ],
                ],
            ];

            $validator = $this->app['validator']->make($data, $rules);
            expect($validator->passes())->toBeTrue();
        });
    });

    describe('logic validation integration', function () {
        it('validates logic conditions through the rule', function () {
            $rules = [
                'properties' => ['required', 'array', new FormPropertiesRule()],
            ];

            $data = [
                'properties' => [
                    [
                        'id' => 'title',
                        'name' => 'Title',
                        'type' => 'text',
                        'hidden' => false,
                        'required' => false,
                        'logic' => [
                            'conditions' => [
                                'operatorIdentifier' => 'and',
                                'children' => [
                                    [
                                        'identifier' => 'title',
                                        'value' => [
                                            'operator' => 'equals',
                                            'property_meta' => [
                                                'id' => 'title',
                                                'type' => 'text',
                                            ],
                                            'value' => 'TEST',
                                        ],
                                    ],
                                ],
                            ],
                            'actions' => ['hide-block'],
                        ],
                    ],
                ],
            ];

            $validator = $this->app['validator']->make($data, $rules);
            expect($validator->passes())->toBeTrue();
        });

        it('fails with invalid logic actions', function () {
            $rules = [
                'properties' => ['required', 'array', new FormPropertiesRule()],
            ];

            $data = [
                'properties' => [
                    [
                        'id' => 'text',
                        'name' => 'Custom Text',
                        'type' => 'nf-text',
                        'logic' => [
                            'conditions' => [
                                'operatorIdentifier' => 'and',
                                'children' => [
                                    [
                                        'identifier' => 'title',
                                        'value' => [
                                            'operator' => 'equals',
                                            'property_meta' => [
                                                'id' => 'title',
                                                'type' => 'text',
                                            ],
                                            'value' => 'TEST',
                                        ],
                                    ],
                                ],
                            ],
                            'actions' => ['require-answer'], // Invalid for layout blocks
                        ],
                    ],
                ],
            ];

            $validator = $this->app['validator']->make($data, $rules);
            expect($validator->passes())->toBeFalse();
            expect($validator->errors()->has('properties.0.logic'))->toBeTrue();
        });
    });
});
