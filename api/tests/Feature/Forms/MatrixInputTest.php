<?php



it('can submit form with valid matrix input', function () {
    $user = $this->actingAsUser();
    $workspace = $this->createUserWorkspace($user);
    $form = $this->createForm($user, $workspace);

    $matrixProperty = [
        'id' => 'matrix_field',
        'name' => 'Matrix Question',
        'type' => 'matrix',
        'rows' => ['Row 1', 'Row 2', 'Row 3'],
        'columns' => ['Column A', 'Column B', 'Column C'],
        'required' => true
    ];

    $form->properties = array_merge($form->properties, [$matrixProperty]);
    $form->update();

    $submissionData = [
        'matrix_field' => [
            'Row 1' => 'Column A',
            'Row 2' => 'Column B',
            'Row 3' => 'Column C'
        ]
    ];

    $formData = $this->generateFormSubmissionData($form, $submissionData);

    $this->postJson(route('forms.answer', $form->slug), $formData)
        ->assertSuccessful()
        ->assertJson([
            'type' => 'success',
            'message' => 'Form submission saved.',
        ]);
});

it('cannot submit form with invalid matrix input', function () {
    $user = $this->actingAsUser();
    $workspace = $this->createUserWorkspace($user);
    $form = $this->createForm($user, $workspace);

    $matrixProperty = [
        'id' => 'matrix_field',
        'name' => 'Matrix Question',
        'type' => 'matrix',
        'rows' => ['Row 1', 'Row 2', 'Row 3'],
        'columns' => ['Column A', 'Column B', 'Column C'],
        'required' => true
    ];

    $form->properties = array_merge($form->properties, [$matrixProperty]);
    $form->update();

    $submissionData = [
        'matrix_field' => [
            'Row 1' => 'Column A',
            'Row 2' => 'Invalid Column',
            'Row 3' => 'Column C'
        ]
    ];

    $formData = $this->generateFormSubmissionData($form, $submissionData);

    $this->postJson(route('forms.answer', $form->slug), $formData)
        ->assertStatus(422)
        ->assertJson([
            'message' => "Invalid value 'Invalid Column' for row 'Row 2'.",
            'errors' => [
                'matrix_field' => [
                    "Invalid value 'Invalid Column' for row 'Row 2'."
                ]
            ]
        ]);
});

it('can submit form with optional matrix input left empty', function () {
    $user = $this->actingAsUser();
    $workspace = $this->createUserWorkspace($user);
    $form = $this->createForm($user, $workspace);

    $matrixProperty = [
        'id' => 'matrix_field',
        'name' => 'Matrix Question',
        'type' => 'matrix',
        'rows' => ['Row 1', 'Row 2', 'Row 3'],
        'columns' => ['Column A', 'Column B', 'Column C'],
        'required' => false
    ];

    $form->properties = array_merge($form->properties, [$matrixProperty]);
    $form->update();

    $submissionData = [
        'matrix_field' => []
    ];

    $formData = $this->generateFormSubmissionData($form, $submissionData);

    $this->postJson(route('forms.answer', $form->slug), $formData)
        ->assertSuccessful()
        ->assertJson([
            'type' => 'success',
            'message' => 'Form submission saved.',
        ]);
});

it('cannot submit form with required matrix input left empty', function () {
    $user = $this->actingAsUser();
    $workspace = $this->createUserWorkspace($user);
    $form = $this->createForm($user, $workspace);

    $matrixProperty = [
        'id' => 'matrix_field',
        'name' => 'Matrix Question',
        'type' => 'matrix',
        'rows' => ['Row 1', 'Row 2', 'Row 3'],
        'columns' => ['Column A', 'Column B', 'Column C'],
        'required' => true
    ];

    $form->properties = array_merge($form->properties, [$matrixProperty]);
    $form->update();

    $submissionData = [
        'matrix_field' => []
    ];

    $formData = $this->generateFormSubmissionData($form, $submissionData);

    $this->postJson(route('forms.answer', $form->slug), $formData)
        ->assertStatus(422)
        ->assertJson([
            'message' => 'The Matrix Question field is required.',
            'errors' => [
                'matrix_field' => [
                    'The Matrix Question field is required.'
                ]
            ]
        ]);
});

it('can validate matrix input with precognition', function () {
    $user = $this->actingAsUser();
    $workspace = $this->createUserWorkspace($user);
    $form = $this->createForm($user, $workspace);

    $matrixProperty = [
        'id' => 'matrix_field',
        'name' => 'Matrix Question',
        'type' => 'matrix',
        'rows' => ['Row 1', 'Row 2', 'Row 3'],
        'columns' => ['Column A', 'Column B', 'Column C'],
        'required' => true
    ];

    $form->properties = array_merge($form->properties, [$matrixProperty]);
    $form->update();

    $submissionData = [
        'matrix_field' => [
            'Row 1' => 'Column A',
            'Row 2' => 'Invalid Column',
            'Row 3' => 'Column C'
        ]
    ];

    $formData = $this->generateFormSubmissionData($form, $submissionData);

    $response = $this->withPrecognition()->withHeaders([
        'Precognition-Validate-Only' => 'matrix_field'
    ])
        ->postJson(route('forms.answer', $form->slug), $formData);

    $response->assertStatus(422)
        ->assertJson([
            'errors' => [
                'matrix_field' => [
                    'Invalid value \'Invalid Column\' for row \'Row 2\'.'
                ]
            ]
        ]);
});

it('can submit form with matrix logic condition when matrix has partial data', function () {
    // Reproduces issue #1026: Matrix logic condition throws "Undefined array key"
    // when checking conditions against partial matrix data
    $user = $this->actingAsUser();
    $workspace = $this->createUserWorkspace($user);
    $form = $this->createForm($user, $workspace);

    // Create a matrix property with German row names (as in the original bug report)
    $matrixProperty = [
        'id' => 'matrix_field',
        'name' => 'Experience Matrix',
        'type' => 'matrix',
        'rows' => ['0-5 Jahre', '5-10 Jahre', '10-15 Jahre', '15+ Jahre'],
        'columns' => ['Keine', 'Wenig', 'Mittel', 'Viel'],
        'required' => true
    ];

    // Create a text field that is conditionally shown based on matrix value
    $conditionalTextField = [
        'id' => 'conditional_text',
        'name' => 'Additional Details',
        'type' => 'text',
        'required' => false,
        'logic' => [
            'conditions' => [
                'operatorIdentifier' => 'and',
                'children' => [
                    [
                        'value' => [
                            'property_meta' => [
                                'id' => 'matrix_field',
                                'type' => 'matrix'
                            ],
                            'operator' => 'equals',
                            'value' => ['15+ Jahre' => 'Viel']
                        ]
                    ]
                ]
            ],
            'actions' => ['require-answer']
        ]
    ];

    $form->properties = array_merge($form->properties, [$matrixProperty, $conditionalTextField]);
    $form->update();

    // Submit with only some rows filled (not including '15+ Jahre')
    // This should NOT throw "Undefined array key" error
    $submissionData = [
        'matrix_field' => [
            '0-5 Jahre' => 'Wenig',
            '5-10 Jahre' => 'Mittel',
            '10-15 Jahre' => 'Viel',
            '15+ Jahre' => 'Keine'
        ],
        'conditional_text' => ''
    ];

    $formData = $this->generateFormSubmissionData($form, $submissionData);

    $this->postJson(route('forms.answer', $form->slug), $formData)
        ->assertSuccessful()
        ->assertJson([
            'type' => 'success',
            'message' => 'Form submission saved.',
        ]);
});

it('can submit form when matrix logic condition has missing rows in submission', function () {
    // Additional test for issue #1026: Ensure no error when matrix field value
    // doesn't have all rows that the condition checks for
    $user = $this->actingAsUser();
    $workspace = $this->createUserWorkspace($user);
    $form = $this->createForm($user, $workspace);

    $matrixProperty = [
        'id' => 'matrix_field',
        'name' => 'Experience Matrix',
        'type' => 'matrix',
        'rows' => ['Row A', 'Row B', 'Row C'],
        'columns' => ['Option 1', 'Option 2', 'Option 3'],
        'required' => false  // Make it optional so we can submit partial data
    ];

    // Conditional field that checks for a specific matrix row/column combination
    $conditionalTextField = [
        'id' => 'conditional_text',
        'name' => 'Conditional Field',
        'type' => 'text',
        'required' => false,
        'logic' => [
            'conditions' => [
                'operatorIdentifier' => 'and',
                'children' => [
                    [
                        'value' => [
                            'property_meta' => [
                                'id' => 'matrix_field',
                                'type' => 'matrix'
                            ],
                            'operator' => 'equals',
                            'value' => ['Row C' => 'Option 3']  // Check for Row C
                        ]
                    ]
                ]
            ],
            'actions' => ['require-answer']
        ]
    ];

    $form->properties = array_merge($form->properties, [$matrixProperty, $conditionalTextField]);
    $form->update();

    // Submit with empty matrix (no rows filled)
    // The condition checks for 'Row C' but our submission doesn't have it
    // This should NOT throw "Undefined array key" error
    $submissionData = [
        'matrix_field' => [],
        'conditional_text' => ''
    ];

    $formData = $this->generateFormSubmissionData($form, $submissionData);

    $this->postJson(route('forms.answer', $form->slug), $formData)
        ->assertSuccessful()
        ->assertJson([
            'type' => 'success',
            'message' => 'Form submission saved.',
        ]);
});
