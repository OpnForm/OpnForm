<?php

use Illuminate\Testing\Fluent\AssertableJson;

it('create form with logic', function () {
    $user = $this->actingAsUser();
    $workspace = $this->createUserWorkspace($user);
    $form = $this->createForm($user, $workspace, [
        'properties' => [
            [
                'id' => 'title',
                'name' => 'Name',
                'type' => 'title',
                'hidden' => false,
                'required' => true,
                'logic' => [
                    'conditions' => [
                        'operatorIdentifier' => 'and',
                        'children' => [
                            [
                                'identifier' => 'email',
                                'value' => [
                                    'operator' => 'is_empty',
                                    'property_meta' => [
                                        'id' => '93ea3198-353f-440b-8dc9-2ac9a7bee124',
                                        'type' => 'email',
                                    ],
                                    'value' => true,
                                ],
                            ],
                        ],
                    ],
                    'actions' => ['make-it-optional'],
                ],
            ],
        ],
    ]);

    $this->getJson(route('forms.show', $form->slug))
        ->assertSuccessful()
        ->assertJson(function (AssertableJson $json) use ($form) {
            return $json->where('id', $form->id)
                ->where('properties', function ($values) {
                    return count($values[0]['logic']) > 0;
                })
                ->etc();
        });

    // Should submit form
    $forData = ['93ea3198-353f-440b-8dc9-2ac9a7bee124' => ''];
    $this->postJson(route('forms.answer', $form->slug), $forData)
        ->assertSuccessful()
        ->assertJson([
            'type' => 'success',
            'message' => 'Form submission saved.',
        ]);
});

it('create form with multi select logic', function () {
    $user = $this->actingAsUser();
    $workspace = $this->createUserWorkspace($user);
    $form = $this->createForm($user, $workspace, [
        'properties' => [
            [
                'id' => 'title',
                'name' => 'Name',
                'type' => 'title',
                'hidden' => false,
                'required' => false,
                'logic' => [
                    'conditions' => [
                        'operatorIdentifier' => 'and',
                        'children' => [
                            [
                                'identifier' => 'multi_select',
                                'value' => [
                                    'operator' => 'contains',
                                    'property_meta' => [
                                        'id' => '93ea3198-353f-440b-8dc9-2ac9a7bee124',
                                        'type' => 'multi_select',
                                    ],
                                    'value' => 'One',
                                ],
                            ],
                        ],
                    ],
                    'actions' => ['require-answer'],
                ],
            ],
        ],
    ]);

    $this->getJson(route('forms.show', $form->slug))
        ->assertSuccessful()
        ->assertJson(function (AssertableJson $json) use ($form) {
            return $json->where('id', $form->id)
                ->where('properties', function ($values) {
                    return count($values[0]['logic']) > 0;
                })
                ->etc();
        });

    // Should submit form
    $forData = ['93ea3198-353f-440b-8dc9-2ac9a7bee124' => ['One']];
    $this->postJson(route('forms.answer', $form->slug), $forData)
        ->assertStatus(422)
        ->assertJson([
            'message' => 'The Name field is required.',
            'errors' => [
                'title' => [
                    'The Name field is required.',
                ],
            ],
        ]);
});

it('preserves multi-select values during validation with logic conditions', function () {
    $user = $this->actingAsUser();
    $workspace = $this->createUserWorkspace($user);

    // Create a form with a multi-select field and a text field that has logic based on the multi-select
    $form = $this->createForm($user, $workspace, [
        'properties' => [
            [
                'id' => 'multi_select_field',
                'name' => 'Multi Select Field',
                'type' => 'multi_select',
                'hidden' => false,
                'required' => true,
                'multi_select' => [
                    'options' => [
                        ['id' => 'option1', 'name' => 'Option 1'],
                        ['id' => 'option2', 'name' => 'Option 2'],
                        ['id' => 'option3', 'name' => 'Option 3']
                    ]
                ]
            ],
            [
                'id' => 'text_field',
                'name' => 'Text Field',
                'type' => 'text',
                'hidden' => false,
                'required' => false,
                'logic' => [
                    'conditions' => [
                        'operatorIdentifier' => 'and',
                        'children' => [
                            [
                                'identifier' => 'multi_select',
                                'value' => [
                                    'operator' => 'contains',
                                    'property_meta' => [
                                        'id' => 'multi_select_field',
                                        'type' => 'multi_select'
                                    ],
                                    'value' => 'Option 1'
                                ]
                            ]
                        ]
                    ],
                    'actions' => ['require-answer']
                ]
            ]
        ]
    ]);

    // Submit form data with multi-select values
    $formData = [
        'multi_select_field' => ['Option 1', 'Option 2']
    ];

    $response = $this->postJson(route('forms.answer', $form->slug), $formData);

    // The validation should fail because text_field is required when Option 1 is selected
    $response->assertStatus(422)
        ->assertJson([
            'message' => 'The Text Field field is required.',
            'errors' => [
                'text_field' => ['The Text Field field is required.']
            ]
        ]);

    // Check that the multi-select values were preserved in the validation data
    expect($response->json('errors.multi_select_field'))->toBeNull();
});

it('correctly handles multi-select values with complex form logic', function () {
    $user = $this->actingAsUser();
    $workspace = $this->createUserWorkspace($user);

    // Create form with the specific fields from your example
    $form = $this->createForm($user, $workspace, [
        'properties' => [
            [
                'id' => '93c8ebe9-b1ba-42ce-841c-bf3b9be1ca4b',
                'name' => 'Which event would you like to join?',
                'type' => 'multi_select',
                'hidden' => false,
                'required' => true,
                'multi_select' => [
                    'options' => [
                        ['id' => 'Ashkelon Run (March 21)', 'name' => 'Ashkelon Run (March 21)'],
                        ['id' => 'Jerusalem Marathon (April 4)', 'name' => 'Jerusalem Marathon (April 4)'],
                        ['id' => 'Neither', 'name' => 'Neither']
                    ]
                ]
            ],
            [
                'id' => '0ca51469-6bda-40f4-831c-084f066643d7',
                'name' => 'Jerusalem Marathon - Run Options',
                'type' => 'select',
                'hidden' => true,
                'required' => false,
                'select' => [
                    'options' => [
                        ['id' => '10km (Most popular)', 'name' => '10km (Most popular)']
                    ]
                ],
                'logic' => [
                    'conditions' => [
                        'operatorIdentifier' => 'and',
                        'children' => [
                            [
                                'identifier' => '93c8ebe9-b1ba-42ce-841c-bf3b9be1ca4b',
                                'value' => [
                                    'operator' => 'contains',
                                    'property_meta' => [
                                        'id' => '93c8ebe9-b1ba-42ce-841c-bf3b9be1ca4b',
                                        'type' => 'multi_select'
                                    ],
                                    'value' => 'Jerusalem Marathon (April 4)'
                                ]
                            ]
                        ]
                    ],
                    'actions' => ['require-answer', 'show-block']
                ]
            ]
        ]
    ]);

    // Submit form data matching your payload
    $formData = [
        '93c8ebe9-b1ba-42ce-841c-bf3b9be1ca4b' => ['Jerusalem Marathon (April 4)'],
        '0ca51469-6bda-40f4-831c-084f066643d7' => '10km (Most popular)'
    ];

    $response = $this->postJson(route('forms.answer', $form->slug), $formData);

    // Should be successful since all required fields are filled
    $response->assertSuccessful()
        ->assertJson([
            'type' => 'success',
            'message' => 'Form submission saved.'
        ]);

    // Now let's verify the saved submission data
    $submission = $form->submissions()->first();
    expect($submission->data['93c8ebe9-b1ba-42ce-841c-bf3b9be1ca4b'])->toBe(['Jerusalem Marathon (April 4)']);
});

it('preserves multi-select values when building validation rules', function () {
    $user = $this->actingAsUser();
    $workspace = $this->createUserWorkspace($user);

    // Create form with the exact fields from your real form
    $form = $this->createForm($user, $workspace, [
        'properties' => [
            [
                'id' => '93c8ebe9-b1ba-42ce-841c-bf3b9be1ca4b',
                'name' => 'Which event would you like to join?',
                'type' => 'multi_select',
                'required' => true,
                'multi_select' => [
                    'options' => [
                        ['id' => 'Jerusalem Marathon (April 4)', 'name' => 'Jerusalem Marathon (April 4)'],
                        ['id' => 'Ashkelon Run (March 21)', 'name' => 'Ashkelon Run (March 21)']
                    ]
                ]
            ],
            [
                'id' => '72565901-c345-427a-b988-0ce3de9ad9f4',
                'name' => 'Additional Days',
                'type' => 'multi_select',
                'required' => false,
                'multi_select' => [
                    'options' => [
                        ['id' => 'Thursday', 'name' => 'Thursday'],
                        ['id' => 'Sunday', 'name' => 'Sunday']
                    ]
                ],
                'logic' => [
                    'conditions' => [
                        'operatorIdentifier' => 'and',
                        'children' => [
                            [
                                'identifier' => '93c8ebe9-b1ba-42ce-841c-bf3b9be1ca4b',
                                'value' => [
                                    'operator' => 'contains',
                                    'property_meta' => [
                                        'id' => '93c8ebe9-b1ba-42ce-841c-bf3b9be1ca4b',
                                        'type' => 'multi_select'
                                    ],
                                    'value' => 'Ashkelon Run (March 21)'
                                ]
                            ]
                        ]
                    ],
                    'actions' => ['require-answer']
                ]
            ]
        ]
    ]);

    // Submit form data with Jerusalem Marathon
    $formData = [
        '93c8ebe9-b1ba-42ce-841c-bf3b9be1ca4b' => ['Jerusalem Marathon (April 4)']
    ];

    $response = $this->postJson(route('forms.answer', $form->slug), $formData);

    // Should be successful since Jerusalem Marathon doesn't require Additional Days
    $response->assertSuccessful();

    // Now try with Ashkelon Run which requires Additional Days
    $formData = [
        '93c8ebe9-b1ba-42ce-841c-bf3b9be1ca4b' => ['Ashkelon Run (March 21)']
    ];

    $response = $this->postJson(route('forms.answer', $form->slug), $formData);

    // Should fail because Additional Days is required when Ashkelon Run is selected
    $response->assertStatus(422)
        ->assertJson([
            'errors' => [
                '72565901-c345-427a-b988-0ce3de9ad9f4' => ['The Additional Days field is required.']
            ]
        ]);
});


it('can submit form with passed regex validation condition', function () {
    $user = $this->actingAsUser();
    $workspace = $this->createUserWorkspace($user);
    $form = $this->createForm($user, $workspace);
    $targetField = collect($form->properties)->where('name', 'Email')->first();

    // Regex condition to check if email is from gmail.com domain
    $condition = [
        'actions' => [],
        'conditions' => [
            'operatorIdentifier' => 'and',
            'children' => [
                [
                    'identifier' => $targetField['id'],
                    'value' => [
                        'operator' => 'matches_regex',
                        'property_meta' => [
                            'id' => $targetField['id'],
                            'type' => 'text',
                        ],
                        'value' => '^[a-zA-Z0-9._%+-]+@gmail\.com$',
                    ],
                ],
            ],
        ],
    ];

    $submissionData = [];
    $validationMessage = 'Must be a Gmail address';

    $form->properties = collect($form->properties)->map(function ($property) use (&$submissionData, &$condition, &$validationMessage, $targetField) {
        if (in_array($property['name'], ['Name'])) {
            $property['validation'] = ['error_conditions' => $condition, 'error_message' => $validationMessage];
            $submissionData[$targetField['id']] = 'test@gmail.com';
        }
        return $property;
    })->toArray();

    $form->update();
    $formData = $this->generateFormSubmissionData($form, $submissionData);

    $response = $this->postJson(route('forms.answer', $form->slug), $formData);
    $response->assertSuccessful()
        ->assertJson([
            'type' => 'success',
            'message' => 'Form submission saved.',
        ]);
});

it('can not submit form with failed regex validation condition', function () {
    $user = $this->actingAsUser();
    $workspace = $this->createUserWorkspace($user);
    $form = $this->createForm($user, $workspace);
    $targetField = collect($form->properties)->where('name', 'Email')->first();

    // Regex condition to check if email is from gmail.com domain
    $condition = [
        'actions' => [],
        'conditions' => [
            'operatorIdentifier' => 'and',
            'children' => [
                [
                    'identifier' => $targetField['id'],
                    'value' => [
                        'operator' => 'matches_regex',
                        'property_meta' => [
                            'id' => $targetField['id'],
                            'type' => 'text',
                        ],
                        'value' => '^[a-zA-Z0-9._%+-]+@gmail\.com$',
                    ],
                ],
            ],
        ],
    ];

    $submissionData = [];
    $validationMessage = 'Must be a Gmail address';

    $form->properties = collect($form->properties)->map(function ($property) use (&$submissionData, &$condition, &$validationMessage, $targetField) {
        if (in_array($property['name'], ['Name'])) {
            $property['validation'] = ['error_conditions' => $condition, 'error_message' => $validationMessage];
            $submissionData[$targetField['id']] = 'test@yahoo.com'; // Non-Gmail address should fail
        }
        return $property;
    })->toArray();

    $form->update();
    $formData = $this->generateFormSubmissionData($form, $submissionData);

    $this->postJson(route('forms.answer', $form->slug), $formData)
        ->assertStatus(422)
        ->assertJson([
            'message' => $validationMessage,
        ]);
});

it('can submit form with does not match regex validation condition', function () {
    $user = $this->actingAsUser();
    $workspace = $this->createUserWorkspace($user);
    $form = $this->createForm($user, $workspace);
    $targetField = collect($form->properties)->where('name', 'Email')->first();

    // Regex condition to check if email is NOT from gmail.com domain
    $condition = [
        'actions' => [],
        'conditions' => [
            'operatorIdentifier' => 'and',
            'children' => [
                [
                    'identifier' => $targetField['id'],
                    'value' => [
                        'operator' => 'does_not_match_regex',
                        'property_meta' => [
                            'id' => $targetField['id'],
                            'type' => 'text',
                        ],
                        'value' => '^[a-zA-Z0-9._%+-]+@gmail\.com$',
                    ],
                ],
            ],
        ],
    ];

    $submissionData = [];
    $validationMessage = 'Gmail addresses not allowed';

    $form->properties = collect($form->properties)->map(function ($property) use (&$submissionData, &$condition, &$validationMessage, $targetField) {
        if (in_array($property['name'], ['Name'])) {
            $property['validation'] = ['error_conditions' => $condition, 'error_message' => $validationMessage];
            $submissionData[$targetField['id']] = 'test@yahoo.com'; // Non-Gmail address should pass
        }
        return $property;
    })->toArray();

    $form->update();
    $formData = $this->generateFormSubmissionData($form, $submissionData);

    $response = $this->postJson(route('forms.answer', $form->slug), $formData);
    $response->assertSuccessful()
        ->assertJson([
            'type' => 'success',
            'message' => 'Form submission saved.',
        ]);
});

it('handles invalid regex patterns gracefully', function () {
    $user = $this->actingAsUser();
    $workspace = $this->createUserWorkspace($user);
    $form = $this->createForm($user, $workspace);
    $targetField = collect($form->properties)->where('name', 'Email')->first();

    // Invalid regex pattern
    $condition = [
        'actions' => [],
        'conditions' => [
            'operatorIdentifier' => 'and',
            'children' => [
                [
                    'identifier' => $targetField['id'],
                    'value' => [
                        'operator' => 'matches_regex',
                        'property_meta' => [
                            'id' => $targetField['id'],
                            'type' => 'text',
                        ],
                        'value' => '[Invalid Regex)', // Invalid regex pattern
                    ],
                ],
            ],
        ],
    ];

    $submissionData = [];
    $validationMessage = 'Invalid regex pattern';

    $form->properties = collect($form->properties)->map(function ($property) use (&$submissionData, &$condition, &$validationMessage, $targetField) {
        if (in_array($property['name'], ['Name'])) {
            $property['validation'] = ['error_conditions' => $condition, 'error_message' => $validationMessage];
            $submissionData[$targetField['id']] = 'test@gmail.com';
        }
        return $property;
    })->toArray();

    $form->update();
    $formData = $this->generateFormSubmissionData($form, $submissionData);

    $this->postJson(route('forms.answer', $form->slug), $formData)
        ->assertStatus(422)
        ->assertJson([
            'message' => $validationMessage,
        ]);
});

it('skips validation for fields hidden by logic conditions', function () {
    $user = $this->actingAsUser();
    $workspace = $this->createUserWorkspace($user);
    $form = $this->createForm($user, $workspace, [
        'properties' => [
            [
                'id' => 'title',
                'name' => 'Name',
                'type' => 'title',
                'hidden' => false,
                'required' => true,
                'logic' => [
                    'conditions' => [
                        'operatorIdentifier' => 'and',
                        'children' => [
                            [
                                'identifier' => 'email',
                                'value' => [
                                    'operator' => 'is_empty',
                                    'property_meta' => [
                                        'id' => 'email_field',
                                        'type' => 'email',
                                    ],
                                    'value' => true,
                                ],
                            ],
                        ],
                    ],
                    'actions' => ['hide-block'],
                ],
            ],
            [
                'id' => 'email_field',
                'name' => 'Email',
                'type' => 'email',
                'hidden' => false,
                'required' => false,
            ],
        ],
    ]);

    // Test when field should be hidden (email is empty)
    $formData = ['email_field' => '']; // Empty email
    $this->postJson(route('forms.answer', $form->slug), $formData)
        ->assertSuccessful()
        ->assertJson([
            'type' => 'success',
            'message' => 'Form submission saved.',
        ]);

    // Test when field should be visible (email is not empty)
    $formData = ['email_field' => 'test@example.com'];
    $this->postJson(route('forms.answer', $form->slug), $formData)
        ->assertStatus(422)
        ->assertJson([
            'message' => 'The Name field is required.',
            'errors' => [
                'title' => [
                    'The Name field is required.',
                ],
            ],
        ]);
});

it('cannot submit form with failed exists_in_submissions validation condition', function () {
    $user = $this->actingAsUser();
    $workspace = $this->createUserWorkspace($user);
    $form = $this->createForm($user, $workspace);
    $targetField = collect($form->properties)->where('name', 'Email')->first();

    // First set up the validation condition
    $condition = [
        'actions' => [],
        'conditions' => [
            'operatorIdentifier' => 'and',
            'children' => [
                [
                    'identifier' => $targetField['id'],
                    'value' => [
                        'operator' => 'exists_in_submissions',
                        'property_meta' => [
                            'id' => $targetField['id'],
                            'type' => 'text',
                        ],
                    ],
                ],
            ],
        ],
    ];

    $validationMessage = 'Email already exists in previous submissions';

    $form->properties = collect($form->properties)->map(function ($property) use ($condition, $validationMessage, $targetField) {
        if (in_array($property['name'], ['Name'])) {
            $property['validation'] = ['error_conditions' => $condition, 'error_message' => $validationMessage];
        }
        return $property;
    })->toArray();

    $form->update();

    $formData = [$targetField['id'] => 'existing@test.com'];

    $this->postJson(route('forms.answer', $form->slug), $formData)
        ->assertStatus(422)
        ->assertJson([
            'message' => $validationMessage,
        ]);
});

it('cannot submit form with failed does_not_exist_in_submissions validation condition', function () {
    $user = $this->actingAsUser();
    $workspace = $this->createUserWorkspace($user);
    $form = $this->createForm($user, $workspace);
    $targetField = collect($form->properties)->where('name', 'Email')->first();

    // First set up the validation condition
    $condition = [
        'actions' => [],
        'conditions' => [
            'operatorIdentifier' => 'and',
            'children' => [
                [
                    'identifier' => $targetField['id'],
                    'value' => [
                        'operator' => 'does_not_exist_in_submissions',
                        'property_meta' => [
                            'id' => $targetField['id'],
                            'type' => 'text',
                        ],
                    ],
                ],
            ],
        ],
    ];

    $validationMessage = 'Email already exists in previous submissions';

    $form->properties = collect($form->properties)->map(function ($property) use ($condition, $validationMessage, $targetField) {
        if (in_array($property['name'], ['Name'])) {
            $property['validation'] = ['error_conditions' => $condition, 'error_message' => $validationMessage];
        }
        return $property;
    })->toArray();

    $form->update();

    $formData = [$targetField['id'] => 'existing@test.com'];

    $this->postJson(route('forms.answer', $form->slug), $formData)
        ->assertSuccessful()
        ->assertJson([
            'type' => 'success',
            'message' => 'Form submission saved.',
        ]);

    $this->postJson(route('forms.answer', $form->slug), $formData)
        ->assertStatus(422)
        ->assertJson([
            'message' => $validationMessage,
        ]);
});

it('can submit form with at_least_x_days_ago date logic condition when date is within range', function () {
    $user = $this->actingAsUser();
    $workspace = $this->createUserWorkspace($user);
    $form = $this->createForm($user, $workspace, [
        'properties' => [
            [
                'id' => 'date_field',
                'name' => 'Event Date',
                'type' => 'date',
                'hidden' => false,
                'required' => true,
            ],
            [
                'id' => 'title',
                'name' => 'Name',
                'type' => 'title',
                'hidden' => false,
                'required' => false,
                'logic' => [
                    'conditions' => [
                        'operatorIdentifier' => 'and',
                        'children' => [
                            [
                                'identifier' => 'date_field',
                                'value' => [
                                    'operator' => 'at_least_x_days_ago',
                                    'property_meta' => [
                                        'id' => 'date_field',
                                        'type' => 'date',
                                    ],
                                    'value' => '5',
                                ],
                            ],
                        ],
                    ],
                    'actions' => ['require-answer'],
                ],
            ],
        ],
    ]);

    // Test with a date that is 7 days before today (should trigger the logic)
    $testDate = now()->subDays(7)->format('Y-m-d');
    $formData = [
        'date_field' => $testDate,
        'title' => 'Required field filled',
    ];

    $this->postJson(route('forms.answer', $form->slug), $formData)
        ->assertSuccessful()
        ->assertJson([
            'type' => 'success',
            'message' => 'Form submission saved.',
        ]);
});

it('cannot submit form with at_least_x_days_ago date logic when required field is missing', function () {
    $user = $this->actingAsUser();
    $workspace = $this->createUserWorkspace($user);
    $form = $this->createForm($user, $workspace, [
        'properties' => [
            [
                'id' => 'date_field',
                'name' => 'Event Date',
                'type' => 'date',
                'hidden' => false,
                'required' => true,
            ],
            [
                'id' => 'title',
                'name' => 'Name',
                'type' => 'title',
                'hidden' => false,
                'required' => false,
                'logic' => [
                    'conditions' => [
                        'operatorIdentifier' => 'and',
                        'children' => [
                            [
                                'identifier' => 'date_field',
                                'value' => [
                                    'operator' => 'at_least_x_days_ago',
                                    'property_meta' => [
                                        'id' => 'date_field',
                                        'type' => 'date',
                                    ],
                                    'value' => '5',
                                ],
                            ],
                        ],
                    ],
                    'actions' => ['require-answer'],
                ],
            ],
        ],
    ]);

    // Test with a date that is 7 days before today but missing the required field
    $testDate = now()->subDays(7)->format('Y-m-d');
    $formData = [
        'date_field' => $testDate,
        // Missing 'title' field which should be required due to logic
    ];

    $this->postJson(route('forms.answer', $form->slug), $formData)
        ->assertStatus(422)
        ->assertJson([
            'message' => 'The Name field is required.',
            'errors' => [
                'title' => [
                    'The Name field is required.',
                ],
            ],
        ]);
});

it('can submit form with at_least_x_days_from_now date logic condition when date is within range', function () {
    $user = $this->actingAsUser();
    $workspace = $this->createUserWorkspace($user);
    $form = $this->createForm($user, $workspace, [
        'properties' => [
            [
                'id' => 'date_field',
                'name' => 'Event Date',
                'type' => 'date',
                'hidden' => false,
                'required' => true,
            ],
            [
                'id' => 'title',
                'name' => 'Name',
                'type' => 'title',
                'hidden' => false,
                'required' => false,
                'logic' => [
                    'conditions' => [
                        'operatorIdentifier' => 'and',
                        'children' => [
                            [
                                'identifier' => 'date_field',
                                'value' => [
                                    'operator' => 'at_least_x_days_from_now',
                                    'property_meta' => [
                                        'id' => 'date_field',
                                        'type' => 'date',
                                    ],
                                    'value' => '5',
                                ],
                            ],
                        ],
                    ],
                    'actions' => ['require-answer'],
                ],
            ],
        ],
    ]);

    // Test with a date that is 7 days after today (should trigger the logic)
    $testDate = now()->addDays(7)->format('Y-m-d');
    $formData = [
        'date_field' => $testDate,
        'title' => 'Required field filled',
    ];

    $this->postJson(route('forms.answer', $form->slug), $formData)
        ->assertSuccessful()
        ->assertJson([
            'type' => 'success',
            'message' => 'Form submission saved.',
        ]);
});

it('cannot submit form with at_least_x_days_from_now date logic when required field is missing', function () {
    $user = $this->actingAsUser();
    $workspace = $this->createUserWorkspace($user);
    $form = $this->createForm($user, $workspace, [
        'properties' => [
            [
                'id' => 'date_field',
                'name' => 'Event Date',
                'type' => 'date',
                'hidden' => false,
                'required' => true,
            ],
            [
                'id' => 'title',
                'name' => 'Name',
                'type' => 'title',
                'hidden' => false,
                'required' => false,
                'logic' => [
                    'conditions' => [
                        'operatorIdentifier' => 'and',
                        'children' => [
                            [
                                'identifier' => 'date_field',
                                'value' => [
                                    'operator' => 'at_least_x_days_from_now',
                                    'property_meta' => [
                                        'id' => 'date_field',
                                        'type' => 'date',
                                    ],
                                    'value' => '5',
                                ],
                            ],
                        ],
                    ],
                    'actions' => ['require-answer'],
                ],
            ],
        ],
    ]);

    // Test with a date that is 7 days after today but missing the required field
    $testDate = now()->addDays(7)->format('Y-m-d');
    $formData = [
        'date_field' => $testDate,
        // Missing 'title' field which should be required due to logic
    ];

    $this->postJson(route('forms.answer', $form->slug), $formData)
        ->assertStatus(422)
        ->assertJson([
            'message' => 'The Name field is required.',
            'errors' => [
                'title' => [
                    'The Name field is required.',
                ],
            ],
        ]);
});

it('can submit form with within_past_x_days date logic condition when date is within range', function () {
    $user = $this->actingAsUser();
    $workspace = $this->createUserWorkspace($user);
    $form = $this->createForm($user, $workspace, [
        'properties' => [
            [
                'id' => 'date_field',
                'name' => 'Event Date',
                'type' => 'date',
                'hidden' => false,
                'required' => true,
            ],
            [
                'id' => 'title',
                'name' => 'Name',
                'type' => 'title',
                'hidden' => false,
                'required' => false,
                'logic' => [
                    'conditions' => [
                        'operatorIdentifier' => 'and',
                        'children' => [
                            [
                                'identifier' => 'date_field',
                                'value' => [
                                    'operator' => 'within_past_x_days',
                                    'property_meta' => [
                                        'id' => 'date_field',
                                        'type' => 'date',
                                    ],
                                    'value' => '7',
                                ],
                            ],
                        ],
                    ],
                    'actions' => ['require-answer'],
                ],
            ],
        ],
    ]);

    // Test with a date that is 3 days before today (within the 7 day range - should trigger)
    $testDate = now()->subDays(3)->toDateString();
    $formData = [
        'date_field' => $testDate,
        'title' => 'Required field filled',
    ];

    $this->postJson(route('forms.answer', $form->slug), $formData)
        ->assertSuccessful()
        ->assertJson([
            'type' => 'success',
            'message' => 'Form submission saved.',
        ]);
});

it('cannot submit form with within_past_x_days date logic when date is outside range', function () {
    $user = $this->actingAsUser();
    $workspace = $this->createUserWorkspace($user);
    $form = $this->createForm($user, $workspace, [
        'properties' => [
            [
                'id' => 'date_field',
                'name' => 'Event Date',
                'type' => 'date',
                'hidden' => false,
                'required' => true,
            ],
            [
                'id' => 'title',
                'name' => 'Name',
                'type' => 'title',
                'hidden' => false,
                'required' => false,
                'logic' => [
                    'conditions' => [
                        'operatorIdentifier' => 'and',
                        'children' => [
                            [
                                'identifier' => 'date_field',
                                'value' => [
                                    'operator' => 'within_past_x_days',
                                    'property_meta' => [
                                        'id' => 'date_field',
                                        'type' => 'date',
                                    ],
                                    'value' => '7',
                                ],
                            ],
                        ],
                    ],
                    'actions' => ['require-answer'],
                ],
            ],
        ],
    ]);

    // Test with a date that is 10 days before today (outside the 7 day range - should NOT trigger)
    $testDate = now()->subDays(10)->toDateString();
    $formData = [
        'date_field' => $testDate,
        // No title field since logic should not trigger
    ];

    $this->postJson(route('forms.answer', $form->slug), $formData)
        ->assertSuccessful()
        ->assertJson([
            'type' => 'success',
            'message' => 'Form submission saved.',
        ]);
});


it('can submit form with within_next_x_days date logic condition when date is within range', function () {
    $user = $this->actingAsUser();
    $workspace = $this->createUserWorkspace($user);
    $form = $this->createForm($user, $workspace, [
        'properties' => [
            [
                'id' => 'date_field',
                'name' => 'Event Date',
                'type' => 'date',
                'hidden' => false,
                'required' => true,
            ],
            [
                'id' => 'title',
                'name' => 'Name',
                'type' => 'title',
                'hidden' => false,
                'required' => false,
                'logic' => [
                    'conditions' => [
                        'operatorIdentifier' => 'and',
                        'children' => [
                            [
                                'identifier' => 'date_field',
                                'value' => [
                                    'operator' => 'within_next_x_days',
                                    'property_meta' => [
                                        'id' => 'date_field',
                                        'type' => 'date',
                                    ],
                                    'value' => '7',
                                ],
                            ],
                        ],
                    ],
                    'actions' => ['require-answer'],
                ],
            ],
        ],
    ]);

    // Test with a date that is 3 days after today (within the 7 day range - should trigger)
    $testDate = now()->addDays(3)->toDateString();
    $formData = [
        'date_field' => $testDate,
        'title' => 'Required field filled',
    ];

    $this->postJson(route('forms.answer', $form->slug), $formData)
        ->assertSuccessful()
        ->assertJson([
            'type' => 'success',
            'message' => 'Form submission saved.',
        ]);
});

it('cannot submit form with within_next_x_days date logic when date is outside range', function () {
    $user = $this->actingAsUser();
    $workspace = $this->createUserWorkspace($user);
    $form = $this->createForm($user, $workspace, [
        'properties' => [
            [
                'id' => 'date_field',
                'name' => 'Event Date',
                'type' => 'date',
                'hidden' => false,
                'required' => true,
            ],
            [
                'id' => 'title',
                'name' => 'Name',
                'type' => 'title',
                'hidden' => false,
                'required' => false,
                'logic' => [
                    'conditions' => [
                        'operatorIdentifier' => 'and',
                        'children' => [
                            [
                                'identifier' => 'date_field',
                                'value' => [
                                    'operator' => 'within_next_x_days',
                                    'property_meta' => [
                                        'id' => 'date_field',
                                        'type' => 'date',
                                    ],
                                    'value' => '7',
                                ],
                            ],
                        ],
                    ],
                    'actions' => ['require-answer'],
                ],
            ],
        ],
    ]);

    // Test with a date that is 10 days after today (outside the 7 day range - should NOT trigger)
    $testDate = now()->addDays(10)->toDateString();
    $formData = [
        'date_field' => $testDate,
        // No title field since logic should not trigger
    ];

    $this->postJson(route('forms.answer', $form->slug), $formData)
        ->assertSuccessful()
        ->assertJson([
            'type' => 'success',
            'message' => 'Form submission saved.',
        ]);
});

it('validates within_past_x_days includes today', function () {
    $user = $this->actingAsUser();
    $workspace = $this->createUserWorkspace($user);
    $form = $this->createForm($user, $workspace, [
        'properties' => [
            [
                'id' => 'date_field',
                'name' => 'Event Date',
                'type' => 'date',
                'hidden' => false,
                'required' => true,
            ],
            [
                'id' => 'title',
                'name' => 'Name',
                'type' => 'title',
                'hidden' => false,
                'required' => false,
                'logic' => [
                    'conditions' => [
                        'operatorIdentifier' => 'and',
                        'children' => [
                            [
                                'identifier' => 'date_field',
                                'value' => [
                                    'operator' => 'within_past_x_days',
                                    'property_meta' => [
                                        'id' => 'date_field',
                                        'type' => 'date',
                                    ],
                                    'value' => '7',
                                ],
                            ],
                        ],
                    ],
                    'actions' => ['require-answer'],
                ],
            ],
        ],
    ]);

    // Test with today's date (should trigger since range includes today)
    $testDate = now()->toDateString();
    $formData = [
        'date_field' => $testDate,
        'title' => 'Required field filled',
    ];

    $this->postJson(route('forms.answer', $form->slug), $formData)
        ->assertSuccessful()
        ->assertJson([
            'type' => 'success',
            'message' => 'Form submission saved.',
        ]);
});

it('validates within_next_x_days includes today', function () {
    $user = $this->actingAsUser();
    $workspace = $this->createUserWorkspace($user);
    $form = $this->createForm($user, $workspace, [
        'properties' => [
            [
                'id' => 'date_field',
                'name' => 'Event Date',
                'type' => 'date',
                'hidden' => false,
                'required' => true,
            ],
            [
                'id' => 'title',
                'name' => 'Name',
                'type' => 'title',
                'hidden' => false,
                'required' => false,
                'logic' => [
                    'conditions' => [
                        'operatorIdentifier' => 'and',
                        'children' => [
                            [
                                'identifier' => 'date_field',
                                'value' => [
                                    'operator' => 'within_next_x_days',
                                    'property_meta' => [
                                        'id' => 'date_field',
                                        'type' => 'date',
                                    ],
                                    'value' => '7',
                                ],
                            ],
                        ],
                    ],
                    'actions' => ['require-answer'],
                ],
            ],
        ],
    ]);

    // Test with today's date (should trigger since range includes today)
    $testDate = now()->toDateString();
    $formData = [
        'date_field' => $testDate,
        'title' => 'Required field filled',
    ];

    $this->postJson(route('forms.answer', $form->slug), $formData)
        ->assertSuccessful()
        ->assertJson([
            'type' => 'success',
            'message' => 'Form submission saved.',
        ]);
});

it('validates date logic properly rejects invalid numeric condition values', function () {
    $user = $this->actingAsUser();
    $workspace = $this->createUserWorkspace($user);
    $form = $this->createForm($user, $workspace, [
        'properties' => [
            [
                'id' => 'date_field',
                'name' => 'Event Date',
                'type' => 'date',
                'hidden' => false,
                'required' => true,
            ],
            [
                'id' => 'title',
                'name' => 'Name',
                'type' => 'title',
                'hidden' => false,
                'required' => false,
                'logic' => [
                    'conditions' => [
                        'operatorIdentifier' => 'and',
                        'children' => [
                            [
                                'identifier' => 'date_field',
                                'value' => [
                                    'operator' => 'within_past_x_days',
                                    'property_meta' => [
                                        'id' => 'date_field',
                                        'type' => 'date',
                                    ],
                                    'value' => 'abc',
                                ],
                            ],
                        ],
                    ],
                    'actions' => ['require-answer'],
                ],
            ],
        ],
    ]);

    // With invalid condition value, logic should not trigger (returns false)
    // So the title field should NOT be required
    $testDate = now()->subDays(3)->toDateString();
    $formData = [
        'date_field' => $testDate,
        // No title field - logic should not trigger for invalid condition values
    ];

    $this->postJson(route('forms.answer', $form->slug), $formData)
        ->assertSuccessful()
        ->assertJson([
            'type' => 'success',
            'message' => 'Form submission saved.',
        ]);
});
