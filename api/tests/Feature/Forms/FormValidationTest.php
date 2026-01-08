<?php

it('cannot submit form with duplicate value when does_not_exist_in_submissions validation is used for select field', function () {
    $user = $this->actingAsUser();
    $workspace = $this->createUserWorkspace($user);
    $form = $this->createForm($user, $workspace, [
        'properties' => [
            [
                'id' => 'select_field',
                'name' => 'Country',
                'type' => 'select',
                'hidden' => false,
                'required' => false,
                'select' => [
                    'options' => [
                        ['id' => 'United States', 'name' => 'United States'],
                        ['id' => 'United Kingdom', 'name' => 'United Kingdom'],
                    ],
                ],
                'validation' => [
                    'error_conditions' => [
                        'actions' => [],
                        'conditions' => [
                            'operatorIdentifier' => 'and',
                            'children' => [
                                [
                                    'identifier' => 'select_field',
                                    'value' => [
                                        // Use does_not_exist to ensure uniqueness
                                        // Validation passes if value doesn't exist, fails if it does
                                        'operator' => 'does_not_exist_in_submissions',
                                        'property_meta' => [
                                            'id' => 'select_field',
                                            'type' => 'select',
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                    'error_message' => 'This country has already been selected',
                ],
            ],
            [
                'id' => 'title',
                'name' => 'Name',
                'type' => 'title',
                'hidden' => false,
                'required' => false
            ],
        ],
    ]);

    // First, create an existing submission with the same value
    $form->submissions()->create([
        'data' => ['select_field' => 'United States'],
    ]);

    // Now try to submit the same value - should fail validation because value already exists
    $formData = [
        'select_field' => 'United States'
    ];
    $this->postJson(route('forms.answer', $form->slug), $formData)
        ->assertStatus(422)
        ->assertJson([
            'message' => 'This country has already been selected',
        ]);
});

it('cannot submit form with duplicate value when does_not_exist_in_submissions validation is used for date field', function () {
    $user = $this->actingAsUser();
    $workspace = $this->createUserWorkspace($user);
    $form = $this->createForm($user, $workspace, [
        'properties' => [
            [
                'id' => 'date_field',
                'name' => 'Event Date',
                'type' => 'date',
                'hidden' => false,
                'required' => false,
                'validation' => [
                    'error_conditions' => [
                        'actions' => [],
                        'conditions' => [
                            'operatorIdentifier' => 'and',
                            'children' => [
                                [
                                    'identifier' => 'date_field',
                                    'value' => [
                                        'operator' => 'does_not_exist_in_submissions',
                                        'property_meta' => [
                                            'id' => 'date_field',
                                            'type' => 'date',
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                    'error_message' => 'This date has already been used',
                ],
            ],
            [
                'id' => 'title',
                'name' => 'Name',
                'type' => 'title',
                'hidden' => false,
                'required' => false
            ],
        ],
    ]);

    $testDate = now()->format('Y-m-d');

    // First, create an existing submission with the same date
    $form->submissions()->create([
        'data' => ['date_field' => $testDate],
    ]);

    // Now try to submit the same date - should fail validation because date already exists
    $formData = ['date_field' => $testDate];
    $this->postJson(route('forms.answer', $form->slug), $formData)
        ->assertStatus(422)
        ->assertJson([
            'message' => 'This date has already been used',
        ]);
});

it('cannot submit form with duplicate value when does_not_exist_in_submissions validation is used for multi_select field', function () {
    $user = $this->actingAsUser();
    $workspace = $this->createUserWorkspace($user);
    $form = $this->createForm($user, $workspace, [
        'properties' => [
            [
                'id' => 'multi_select_field',
                'name' => 'Interests',
                'type' => 'multi_select',
                'hidden' => false,
                'required' => false,
                'multi_select' => [
                    'options' => [
                        ['id' => 'sports', 'name' => 'Sports'],
                        ['id' => 'music', 'name' => 'Music'],
                        ['id' => 'reading', 'name' => 'Reading'],
                    ],
                ],
                'validation' => [
                    'error_conditions' => [
                        'actions' => [],
                        'conditions' => [
                            'operatorIdentifier' => 'and',
                            'children' => [
                                [
                                    'identifier' => 'multi_select_field',
                                    'value' => [
                                        'operator' => 'does_not_exist_in_submissions',
                                        'property_meta' => [
                                            'id' => 'multi_select_field',
                                            'type' => 'multi_select',
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                    'error_message' => 'These interests have already been selected',
                ],
            ],
            [
                'id' => 'title',
                'name' => 'Name',
                'type' => 'title',
                'hidden' => false,
                'required' => false
            ],
        ],
    ]);

    // First, create an existing submission with the same values
    $form->submissions()->create([
        'data' => ['multi_select_field' => ['Sports', 'Music']],
    ]);

    // Now try to submit the same values - should fail validation because values already exist
    $formData = ['multi_select_field' => ['Sports', 'Music']];
    $this->postJson(route('forms.answer', $form->slug), $formData)
        ->assertStatus(422)
        ->assertJson([
            'message' => 'These interests have already been selected',
        ]);
});

it('cannot submit form with duplicate value when does_not_exist_in_submissions validation is used for rating field', function () {
    $user = $this->actingAsUser();
    $workspace = $this->createUserWorkspace($user);
    $form = $this->createForm($user, $workspace, [
        'properties' => [
            [
                'id' => 'rating_field',
                'name' => 'Rating',
                'type' => 'rating',
                'hidden' => false,
                'required' => false,
                'validation' => [
                    'error_conditions' => [
                        'actions' => [],
                        'conditions' => [
                            'operatorIdentifier' => 'and',
                            'children' => [
                                [
                                    'identifier' => 'rating_field',
                                    'value' => [
                                        'operator' => 'does_not_exist_in_submissions',
                                        'property_meta' => [
                                            'id' => 'rating_field',
                                            'type' => 'rating',
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                    'error_message' => 'This rating value already exists',
                ]
            ],
            [
                'id' => 'title',
                'name' => 'Name',
                'type' => 'title',
                'hidden' => false,
                'required' => false
            ],
        ],
    ]);

    // First, create an existing submission with the same rating
    $form->submissions()->create([
        'data' => ['rating_field' => 5],
    ]);

    // Now try to submit the same rating - should fail validation because rating already exists
    $formData = ['rating_field' => 5];
    $this->postJson(route('forms.answer', $form->slug), $formData)
        ->assertStatus(422)
        ->assertJson([
            'message' => 'This rating value already exists',
        ]);
});

it('cannot submit form with duplicate value when does_not_exist_in_submissions validation is used for scale field', function () {
    $user = $this->actingAsUser();
    $workspace = $this->createUserWorkspace($user);
    $form = $this->createForm($user, $workspace, [
        'properties' => [
            [
                'id' => 'scale_field',
                'name' => 'Satisfaction Scale',
                'type' => 'scale',
                'hidden' => false,
                'required' => false,
                'validation' => [
                    'error_conditions' => [
                        'actions' => [],
                        'conditions' => [
                            'operatorIdentifier' => 'and',
                            'children' => [
                                [
                                    'identifier' => 'scale_field',
                                    'value' => [
                                        'operator' => 'does_not_exist_in_submissions',
                                        'property_meta' => [
                                            'id' => 'scale_field',
                                            'type' => 'scale',
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                    'error_message' => 'This scale value already exists',
                ]
            ],
            [
                'id' => 'title',
                'name' => 'Name',
                'type' => 'title',
                'hidden' => false,
                'required' => false
            ],
        ],
    ]);

    // First, create an existing submission with the same scale value
    $form->submissions()->create([
        'data' => ['scale_field' => 7],
    ]);

    // Now try to submit the same scale value - should fail validation because it already exists
    $formData = ['scale_field' => 7];
    $this->postJson(route('forms.answer', $form->slug), $formData)
        ->assertStatus(422)
        ->assertJson([
            'message' => 'This scale value already exists',
        ]);
});

it('cannot submit form with duplicate value when does_not_exist_in_submissions validation is used for slider field', function () {
    $user = $this->actingAsUser();
    $workspace = $this->createUserWorkspace($user);
    $form = $this->createForm($user, $workspace, [
        'properties' => [
            [
                'id' => 'slider_field',
                'name' => 'Slider Value',
                'type' => 'slider',
                'hidden' => false,
                'required' => false,
                'validation' => [
                    'error_conditions' => [
                        'actions' => [],
                        'conditions' => [
                            'operatorIdentifier' => 'and',
                            'children' => [
                                [
                                    'identifier' => 'slider_field',
                                    'value' => [
                                        'operator' => 'does_not_exist_in_submissions',
                                        'property_meta' => [
                                            'id' => 'slider_field',
                                            'type' => 'slider',
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                    'error_message' => 'This slider value already exists',
                ],
            ],
            [
                'id' => 'title',
                'name' => 'Name',
                'type' => 'title',
                'hidden' => false,
                'required' => false
            ],
        ],
    ]);

    // First, create an existing submission with the same slider value
    $form->submissions()->create([
        'data' => ['slider_field' => 50],
    ]);

    // Now try to submit the same slider value - should fail validation because it already exists
    $formData = ['slider_field' => 50];
    $this->postJson(route('forms.answer', $form->slug), $formData)
        ->assertStatus(422)
        ->assertJson([
            'message' => 'This slider value already exists',
        ]);
});

it('can submit form with does_not_exist_in_submissions validation condition for select field', function () {
    $user = $this->actingAsUser();
    $workspace = $this->createUserWorkspace($user);
    $form = $this->createForm($user, $workspace, [
        'properties' => [
            [
                'id' => 'select_field',
                'name' => 'Country',
                'type' => 'select',
                'hidden' => false,
                'required' => false,
                'select' => [
                    'options' => [
                        ['id' => 'United States', 'name' => 'United States'],
                        ['id' => 'United Kingdom', 'name' => 'United Kingdom'],
                    ],
                ],
                'validation' => [
                    'error_conditions' => [
                        'actions' => [],
                        'conditions' => [
                            'operatorIdentifier' => 'and',
                            'children' => [
                                [
                                    'identifier' => 'select_field',
                                    'value' => [
                                        'operator' => 'does_not_exist_in_submissions',
                                        'property_meta' => [
                                            'id' => 'select_field',
                                            'type' => 'select',
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                    'error_message' => 'This country has already been selected',
                ],
            ],
            [
                'id' => 'title',
                'name' => 'Name',
                'type' => 'title',
                'hidden' => false,
                'required' => false
            ],
        ],
    ]);

    $formData = ['select_field' => 'United States'];
    $this->postJson(route('forms.answer', $form->slug), $formData)
        ->assertSuccessful()
        ->assertJson([
            'type' => 'success',
            'message' => 'Form submission saved.',
        ]);
});

it('cannot submit form with duplicate value when does_not_exist_in_submissions validation is used for matrix field', function () {
    $user = $this->actingAsUser();
    $workspace = $this->createUserWorkspace($user);
    $form = $this->createForm($user, $workspace, [
        'properties' => [
            [
                'id' => 'matrix_field',
                'name' => 'Matrix',
                'type' => 'matrix',
                'hidden' => false,
                'required' => false,
                'rows' => ['Row 1', 'Row 2'],
                'columns' => ['Column 1', 'Column 2'],
                'validation' => [
                    'error_conditions' => [
                        'actions' => [],
                        'conditions' => [
                            'operatorIdentifier' => 'and',
                            'children' => [
                                [
                                    'identifier' => 'matrix_field',
                                    'value' => [
                                        'operator' => 'does_not_exist_in_submissions',
                                        'property_meta' => [
                                            'id' => 'matrix_field',
                                            'type' => 'matrix',
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                    'error_message' => 'This matrix value already exists',
                ],
            ],
            [
                'id' => 'title',
                'name' => 'Name',
                'type' => 'title',
                'hidden' => false,
                'required' => false
            ],
        ],
    ]);

    // First, create an existing submission with the same matrix values
    $form->submissions()->create([
        'data' => [
            'matrix_field' => [
                'Row 1' => 'Column 1',
                'Row 2' => 'Column 2',
            ]
        ],
    ]);

    // Now try to submit the same values - should fail validation because values already exist
    $submissionData = [
        'matrix_field' => [
            'Row 1' => 'Column 1',
            'Row 2' => 'Column 2',
        ]
    ];
    $formData = $this->generateFormSubmissionData($form, $submissionData);
    $this->postJson(route('forms.answer', $form->slug), $formData)
        ->assertStatus(422)
        ->assertJson([
            'message' => 'This matrix value already exists',
        ]);
});
