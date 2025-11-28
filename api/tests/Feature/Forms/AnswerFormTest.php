<?php

use App\Models\Forms\Form;

it('can answer a form', function () {
    $user = $this->actingAsUser();
    $workspace = $this->createUserWorkspace($user);
    $form = $this->createForm($user, $workspace);

    // TODO: generate random response given a form and un-skip
})->skip('Need to finish writing a class to generated random responses');

it('can submit form if close date is in future', function () {
    $user = $this->actingAsUser();
    $workspace = $this->createUserWorkspace($user);
    $form = $this->createForm($user, $workspace, [
        'closes_at' => \Carbon\Carbon::now()->addDays(1)->toDateTimeString(),
    ]);
    $formData = $this->generateFormSubmissionData($form);

    $this->postJson(route('forms.answer', $form->slug), $formData)
        ->assertSuccessful()
        ->assertJson([
            'type' => 'success',
            'message' => 'Form submission saved.',
        ]);
});

it('can not submit closed form', function () {
    $user = $this->actingAsUser();
    $workspace = $this->createUserWorkspace($user);
    $form = $this->createForm($user, $workspace, [
        'closes_at' => \Carbon\Carbon::now()->subDays(1)->toDateTimeString(),
    ]);
    $formData = $this->generateFormSubmissionData($form);

    $this->postJson(route('forms.answer', $form->slug), $formData)
        ->assertStatus(403);
});

it('can submit form till max submissions count is not reached at limit', function () {
    $user = $this->actingAsUser();
    $workspace = $this->createUserWorkspace($user);
    $form = $this->createForm($user, $workspace, [
        'max_submissions_count' => 3,
    ]);
    $formData = $this->generateFormSubmissionData($form);

    // Can submit form
    for ($i = 1; $i <= 3; $i++) {
        $this->postJson(route('forms.answer', $form->slug), $formData)
            ->assertSuccessful()
            ->assertJson([
                'type' => 'success',
                'message' => 'Form submission saved.',
            ]);
    }

    // Now, can not submit form, Because it's reached at submission limit
    $this->postJson(route('forms.answer', $form->slug), $formData)
        ->assertStatus(403);
});

it('can not open draft form', function () {
    $user = $this->actingAsUser();
    $workspace = $this->createUserWorkspace($user);
    $form = $this->createForm($user, $workspace, [
        'visibility' => 'draft',
    ]);

    $this->getJson(route('forms.show', $form->slug))
        ->assertStatus(404);
});

it('can not submit draft form', function () {
    $user = $this->actingAsUser();
    $workspace = $this->createUserWorkspace($user);
    $form = $this->createForm($user, $workspace, [
        'visibility' => 'draft',
    ]);
    $formData = $this->generateFormSubmissionData($form);

    $this->postJson(route('forms.answer', $form->slug), $formData)
        ->assertStatus(403);
});

it('can not submit visibility closed form', function () {
    $user = $this->actingAsUser();
    $workspace = $this->createUserWorkspace($user);
    $form = $this->createForm($user, $workspace, [
        'visibility' => 'closed',
    ]);
    $formData = $this->generateFormSubmissionData($form);

    $this->postJson(route('forms.answer', $form->slug), $formData)
        ->assertStatus(403);
});

it('can not submit form with past dates', function () {
    $user = $this->actingAsUser();
    $workspace = $this->createUserWorkspace($user);
    $form = $this->createForm($user, $workspace);

    $submissionData = [];
    $form->properties = collect($form->properties)->map(function ($property) use (&$submissionData) {
        if (in_array($property['type'], ['date'])) {
            $property['disable_past_dates'] = true;
            $submissionData[$property['id']] = now()->subDays(4)->format('Y-m-d');
        }

        return $property;
    })->toArray();
    $form->update();

    $formData = $this->generateFormSubmissionData($form, $submissionData);

    $this->postJson(route('forms.answer', $form->slug), $formData)
        ->assertStatus(422)
        ->assertJson([
            'message' => 'The Date must be a date after yesterday.',
        ]);
});

it('can not submit form with future dates', function () {
    $user = $this->actingAsUser();
    $workspace = $this->createUserWorkspace($user);
    $form = $this->createForm($user, $workspace);

    $submissionData = [];
    $form->properties = collect($form->properties)->map(function ($property) use (&$submissionData) {
        if (in_array($property['type'], ['date'])) {
            $property['disable_future_dates'] = true;
            $submissionData[$property['id']] = now()->addDays(4)->format('Y-m-d');
        }

        return $property;
    })->toArray();
    $form->update();

    $formData = $this->generateFormSubmissionData($form, $submissionData);

    $this->postJson(route('forms.answer', $form->slug), $formData)
        ->assertStatus(422)
        ->assertJson([
            'message' => 'The Date must be a date before tomorrow.',
        ]);
});


it('can submit form with passed custom validation condition', function () {
    $user = $this->actingAsUser();
    $workspace = $this->createUserWorkspace($user);
    $form = $this->createForm($user, $workspace);
    $targetField = collect($form->properties)->where('name', 'Number')->first();
    $condition = [
        'actions' => [],
        'conditions' => [
            'operatorIdentifier' => 'or',
            'children' => [
                [
                    'identifier' => $targetField['id'],
                    'value' => [
                        'operator' => 'greater_than',
                        'property_meta' => [
                            'id' => $targetField['id'],
                            'type' => 'number',
                        ],
                        'value' => 20,
                    ],
                ],
            ],
        ],
    ];
    $submissionData = [];
    $validationMessage = 'Number too low';
    $form->properties = collect($form->properties)->map(function ($property) use (&$submissionData, &$condition, &$validationMessage, $targetField) {
        if (in_array($property['name'], ['Name'])) {
            $property['validation'] = ['error_conditions' => $condition, 'error_message' => $validationMessage];
            $submissionData[$targetField['id']] = 100;
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

it('can not submit form with failed custom validation condition', function () {
    $user = $this->actingAsUser();
    $workspace = $this->createUserWorkspace($user);
    $form = $this->createForm($user, $workspace);
    $targetField = collect($form->properties)->where('name', 'Email')->first();
    $condition = [
        'actions' => [],
        'conditions' => [
            'operatorIdentifier' => 'and',
            'children' => [
                [
                    'identifier' => $targetField['id'],
                    'value' => [
                        'operator' => 'equals',
                        'property_meta' => [
                            'id' => $targetField['id'],
                            'type' => 'email',
                        ],
                        'value' => 'test@gmail.com',
                    ],
                ],
            ],
        ],
    ];
    $submissionData = [];
    $validationMessage = 'Can only use test@gmail.com';
    $form->properties = collect($form->properties)->map(function ($property) use (&$submissionData, &$condition, &$validationMessage, &$targetField) {
        if (in_array($property['name'], ['Name'])) {
            $property['validation'] = ['error_conditions' => $condition, 'error_message' => $validationMessage];
            $submissionData[$targetField['id']] = 'fail@gmail.com';
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


it('can validate form answer with precognition', function () {
    $user = $this->actingAsUser();
    $workspace = $this->createUserWorkspace($user);
    $form = $this->createForm($user, $workspace);
    $properties = $form->properties;
    $properties[0]['required'] = true;
    $properties[3]['required'] = true;
    $properties[6]['required'] = true;
    $properties[9]['required'] = true;

    $form->properties = $properties;
    $form->update();

    // Empty submission data should fail validation, with all 4 required fields
    $response = $this->postJson(route('forms.answer', $form->slug), []);
    $errors = $response->json()['errors'];
    $this->assertEquals(sizeof($errors), 4);
    $response->assertStatus(422);

    // Fill in data for only Name.
    $submissionData = [];
    foreach ($properties as $property) {
        if ($property['name'] == 'Name') {
            $submissionData[$property['id']] = 'Name';
        } else {
            $submissionData[$property['id']] = null;
        }
    }

    // Select only first 3 fields for precognition validation
    $validateOnlyFields = [
        $properties[0]['id'],
        $properties[1]['id'],
        $properties[2]['id']
    ];

    $precognitionValidateOnly = implode(',', $validateOnlyFields);

    // Partial submission data should pass validation for the precognition only fields.
    $response = $this->withPrecognition()->withHeaders([
        'Precognition-Validate-Only' => $precognitionValidateOnly
    ])
        ->postJson(route('forms.answer', $form->slug), $submissionData);

    $response->assertSuccessfulPrecognition();


    // Select only next fields for precognition validation
    $validateOnlyFields = $validateOnlyFields = [
        $properties[3]['id'],
        $properties[4]['id'],
        $properties[5]['id']
    ];
    $precognitionValidateOnly = implode(',', $validateOnlyFields);

    // Partial submission data should fail validation, but for only one required field specified for precognition validation.
    $response = $this->withPrecognition()->withHeaders([
        'Precognition-Validate-Only' => $precognitionValidateOnly
    ])
        ->postJson(route('forms.answer', $form->slug), $submissionData);
    $errors = $response->json()['errors'];
    $this->assertEquals(sizeof($errors), 1);
    $response->assertStatus(422);
});


it('executes custom validation before required field validation', function () {
    $user = $this->actingAsUser();
    $workspace = $this->createUserWorkspace($user);
    $form = $this->createForm($user, $workspace);

    $emailField = collect($form->properties)->where('name', 'Email')->first();
    $condition = [
        'actions' => [],
        'conditions' => [
            'operatorIdentifier' => 'and',
            'children' => [
                [
                    'identifier' => $emailField['id'],
                    'value' => [
                        'operator' => 'contains',
                        'property_meta' => [
                            'id' => $emailField['id'],
                            'type' => 'email',
                        ],
                        'value' => '@company.com',
                    ],
                ],
            ],
        ],
    ];

    $submissionData = [];
    $validationMessage = 'Must use company email';
    $form->properties = collect($form->properties)->map(function ($property) use (&$submissionData, &$condition, &$validationMessage, $emailField) {
        if (in_array($property['name'], ['Name'])) {
            $property['required'] = true;
            $property['validation'] = ['error_conditions' => $condition, 'error_message' => $validationMessage];
            $submissionData[$emailField['id']] = 'test@other.com';
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

it('can update form submission if database fields update is set', function () {
    $user = $this->actingAsProUser();
    $workspace = $this->createUserWorkspace($user);
    $form = $this->createForm($user, $workspace);

    $emailField = collect($form->properties)->where('type', 'email')->first();
    $nameField = collect($form->properties)->where('name', 'Name')->first();
    $form->database_fields_update = [$emailField['id']];
    $form->update();

    $this->postJson(route('forms.answer', $form->slug), [
        $emailField['id'] => 'test@test.com',
        $nameField['id'] => 'Test',
    ])
        ->assertSuccessful()
        ->assertJson([
            'type' => 'success',
            'message' => 'Form submission saved.',
        ]);

    // Update submission
    $this->postJson(route('forms.answer', $form->slug), [
        $emailField['id'] => 'test@test.com',
        $nameField['id'] => 'Test 1',
    ])
        ->assertSuccessful()
        ->assertJson([
            'type' => 'success',
            'message' => 'Form submission saved.',
        ]);

    $submission = $form->submissions()->first();
    $this->assertEquals('test@test.com', $submission->data[$emailField['id']]);
    $this->assertEquals('Test 1', $submission->data[$nameField['id']]);
});

it('can submit form with valid multi select min/max constraints', function () {
    $user = $this->actingAsUser();
    $workspace = $this->createUserWorkspace($user);
    $form = $this->createForm($user, $workspace);

    // Add multi_select field with constraints
    $multiSelectField = [
        'id' => 'test_multi_select',
        'name' => 'Test Multi Select',
        'type' => 'multi_select',
        'required' => true,
        'min_selection' => 2,
        'max_selection' => 3,
        'multi_select' => [
            'options' => [
                ['id' => 'option1', 'name' => 'Option 1'],
                ['id' => 'option2', 'name' => 'Option 2'],
                ['id' => 'option3', 'name' => 'Option 3'],
                ['id' => 'option4', 'name' => 'Option 4'],
            ]
        ]
    ];

    $form->properties = array_merge($form->properties, [$multiSelectField]);
    $form->save();

    // Submit valid data (2 selections, within min=2, max=3)
    $formData = $this->generateFormSubmissionData($form, [
        'test_multi_select' => ['Option 1', 'Option 2']
    ]);

    $this->postJson(route('forms.answer', $form->slug), $formData)
        ->assertSuccessful()
        ->assertJson([
            'type' => 'success',
            'message' => 'Form submission saved.',
        ]);
});

it('rejects multi select submission below minimum selection', function () {
    $user = $this->actingAsUser();
    $workspace = $this->createUserWorkspace($user);
    $form = $this->createForm($user, $workspace);

    // Add multi_select field with min constraint
    $multiSelectField = [
        'id' => 'test_multi_select_min',
        'name' => 'Test Multi Select Min',
        'type' => 'multi_select',
        'required' => true,
        'min_selection' => 3,
        'multi_select' => [
            'options' => [
                ['id' => 'option1', 'name' => 'Option A'],
                ['id' => 'option2', 'name' => 'Option B'],
                ['id' => 'option3', 'name' => 'Option C'],
                ['id' => 'option4', 'name' => 'Option D'],
            ]
        ]
    ];

    $form->properties = array_merge($form->properties, [$multiSelectField]);
    $form->save();

    // Submit invalid data (only 2 selections, but min=3)
    $formData = $this->generateFormSubmissionData($form, [
        'test_multi_select_min' => ['Option A', 'Option B']
    ]);

    $this->postJson(route('forms.answer', $form->slug), $formData)
        ->assertStatus(422)
        ->assertJson([
            'message' => 'Please select at least 3 options',
        ]);
});

it('rejects multi select submission above maximum selection', function () {
    $user = $this->actingAsUser();
    $workspace = $this->createUserWorkspace($user);
    $form = $this->createForm($user, $workspace);

    // Add multi_select field with max constraint
    $multiSelectField = [
        'id' => 'test_multi_select_max',
        'name' => 'Test Multi Select Max',
        'type' => 'multi_select',
        'required' => true,
        'max_selection' => 2,
        'multi_select' => [
            'options' => [
                ['id' => 'option1', 'name' => 'Choice 1'],
                ['id' => 'option2', 'name' => 'Choice 2'],
                ['id' => 'option3', 'name' => 'Choice 3'],
                ['id' => 'option4', 'name' => 'Choice 4'],
            ]
        ]
    ];

    $form->properties = array_merge($form->properties, [$multiSelectField]);
    $form->save();

    // Submit invalid data (3 selections, but max=2)
    $formData = $this->generateFormSubmissionData($form, [
        'test_multi_select_max' => ['Choice 1', 'Choice 2', 'Choice 3']
    ]);

    $this->postJson(route('forms.answer', $form->slug), $formData)
        ->assertStatus(422)
        ->assertJson([
            'message' => 'Please select no more than 2 options',
        ]);
});

it('accepts multi select submission at exact min/max boundaries', function () {
    $user = $this->actingAsUser();
    $workspace = $this->createUserWorkspace($user);
    $form = $this->createForm($user, $workspace);

    // Add multi_select field with both constraints (exact boundary)
    $multiSelectField = [
        'id' => 'test_multi_select_boundary',
        'name' => 'Test Multi Select Boundary',
        'type' => 'multi_select',
        'required' => true,
        'min_selection' => 2,
        'max_selection' => 2,  // Exact constraint - must select exactly 2
        'multi_select' => [
            'options' => [
                ['id' => 'option1', 'name' => 'First'],
                ['id' => 'option2', 'name' => 'Second'],
                ['id' => 'option3', 'name' => 'Third'],
            ]
        ]
    ];

    $form->properties = array_merge($form->properties, [$multiSelectField]);
    $form->save();

    // Submit valid data (exactly 2 selections)
    $formData = $this->generateFormSubmissionData($form, [
        'test_multi_select_boundary' => ['First', 'Second']
    ]);

    $this->postJson(route('forms.answer', $form->slug), $formData)
        ->assertSuccessful()
        ->assertJson([
            'type' => 'success',
            'message' => 'Form submission saved.',
        ]);
});

it('accepts multi select with only min constraint', function () {
    $user = $this->actingAsUser();
    $workspace = $this->createUserWorkspace($user);
    $form = $this->createForm($user, $workspace);

    // Add multi_select field with only min constraint (no max limit)
    $multiSelectField = [
        'id' => 'test_multi_select_min_only',
        'name' => 'Test Multi Select Min Only',
        'type' => 'multi_select',
        'required' => true,
        'min_selection' => 2,
        // No max_selection - unlimited selections allowed
        'multi_select' => [
            'options' => [
                ['id' => 'option1', 'name' => 'One'],
                ['id' => 'option2', 'name' => 'Two'],
                ['id' => 'option3', 'name' => 'Three'],
                ['id' => 'option4', 'name' => 'Four'],
                ['id' => 'option5', 'name' => 'Five'],
            ]
        ]
    ];

    $form->properties = array_merge($form->properties, [$multiSelectField]);
    $form->save();

    // Submit valid data (5 selections, min=2, no max limit)
    $formData = $this->generateFormSubmissionData($form, [
        'test_multi_select_min_only' => ['One', 'Two', 'Three', 'Four', 'Five']
    ]);

    $this->postJson(route('forms.answer', $form->slug), $formData)
        ->assertSuccessful()
        ->assertJson([
            'type' => 'success',
            'message' => 'Form submission saved.',
        ]);
});
