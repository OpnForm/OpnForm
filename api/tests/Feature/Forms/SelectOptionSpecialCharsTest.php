<?php


describe('select options with special characters', function () {
    beforeEach(function () {
        $this->user = $this->actingAsUser();
        $this->workspace = $this->createUserWorkspace($this->user);
        $this->form = $this->createForm($this->user, $this->workspace);
    });

    it('can submit select option containing a tab character', function () {
        $selectField = [
            'id' => 'select_tab',
            'name' => 'Choose an Option',
            'type' => 'select',
            'required' => true,
            'select' => [
                'options' => [
                    ['id' => 'opt1', 'name' => "Architect\t— Архитектор"],
                    ['id' => 'opt2', 'name' => 'Option B'],
                ],
            ],
        ];

        $this->form->properties = array_merge($this->form->properties, [$selectField]);
        $this->form->save();

        $formData = $this->generateFormSubmissionData($this->form, [
            'select_tab' => "Architect\t— Архитектор",
        ]);

        $this->postJson(route('forms.answer', $this->form->slug), $formData)
            ->assertSuccessful()
            ->assertJson([
                'type' => 'success',
                'message' => 'Form submission saved.',
            ]);

        $submission = $this->form->submissions()->first();
        expect($submission->data['select_tab'])->toBe("Architect\t— Архитектор");
    });

    it('can submit select option containing a backslash', function () {
        $selectField = [
            'id' => 'select_backslash',
            'name' => 'Choose an Option',
            'type' => 'select',
            'required' => true,
            'select' => [
                'options' => [
                    ['id' => 'opt1', 'name' => 'C:\\Users\\test'],
                    ['id' => 'opt2', 'name' => 'Option B'],
                ],
            ],
        ];

        $this->form->properties = array_merge($this->form->properties, [$selectField]);
        $this->form->save();

        $formData = $this->generateFormSubmissionData($this->form, [
            'select_backslash' => 'C:\\Users\\test',
        ]);

        $this->postJson(route('forms.answer', $this->form->slug), $formData)
            ->assertSuccessful()
            ->assertJson([
                'type' => 'success',
                'message' => 'Form submission saved.',
            ]);

        $submission = $this->form->submissions()->first();
        expect($submission->data['select_backslash'])->toBe('C:\\Users\\test');
    });

    it('can submit select option containing newline characters', function () {
        $selectField = [
            'id' => 'select_newline',
            'name' => 'Choose an Option',
            'type' => 'select',
            'required' => true,
            'select' => [
                'options' => [
                    ['id' => 'opt1', 'name' => "Line1\nLine2"],
                    ['id' => 'opt2', 'name' => 'Option B'],
                ],
            ],
        ];

        $this->form->properties = array_merge($this->form->properties, [$selectField]);
        $this->form->save();

        $formData = $this->generateFormSubmissionData($this->form, [
            'select_newline' => "Line1\nLine2",
        ]);

        $this->postJson(route('forms.answer', $this->form->slug), $formData)
            ->assertSuccessful()
            ->assertJson([
                'type' => 'success',
                'message' => 'Form submission saved.',
            ]);

        $submission = $this->form->submissions()->first();
        expect($submission->data['select_newline'])->toBe("Line1\nLine2");
    });

    it('can submit select option containing unicode and em dash', function () {
        $selectField = [
            'id' => 'select_unicode',
            'name' => 'Choose an Option',
            'type' => 'select',
            'required' => true,
            'select' => [
                'options' => [
                    ['id' => 'opt1', 'name' => 'Archaeologist — Археолог'],
                    ['id' => 'opt2', 'name' => 'Биолог — Biologist'],
                ],
            ],
        ];

        $this->form->properties = array_merge($this->form->properties, [$selectField]);
        $this->form->save();

        $formData = $this->generateFormSubmissionData($this->form, [
            'select_unicode' => 'Archaeologist — Археолог',
        ]);

        $this->postJson(route('forms.answer', $this->form->slug), $formData)
            ->assertSuccessful()
            ->assertJson([
                'type' => 'success',
                'message' => 'Form submission saved.',
            ]);

        $submission = $this->form->submissions()->first();
        expect($submission->data['select_unicode'])->toBe('Archaeologist — Археолог');
    });

    it('can submit select option containing commas', function () {
        $selectField = [
            'id' => 'select_comma',
            'name' => 'Choose an Option',
            'type' => 'select',
            'required' => true,
            'select' => [
                'options' => [
                    ['id' => 'opt1', 'name' => 'Smith, John'],
                    ['id' => 'opt2', 'name' => 'Doe, Jane'],
                ],
            ],
        ];

        $this->form->properties = array_merge($this->form->properties, [$selectField]);
        $this->form->save();

        $formData = $this->generateFormSubmissionData($this->form, [
            'select_comma' => 'Smith, John',
        ]);

        $this->postJson(route('forms.answer', $this->form->slug), $formData)
            ->assertSuccessful()
            ->assertJson([
                'type' => 'success',
                'message' => 'Form submission saved.',
            ]);

        $submission = $this->form->submissions()->first();
        expect($submission->data['select_comma'])->toBe('Smith, John');
    });

    it('can submit select option containing double quotes', function () {
        $selectField = [
            'id' => 'select_quotes',
            'name' => 'Choose an Option',
            'type' => 'select',
            'required' => true,
            'select' => [
                'options' => [
                    ['id' => 'opt1', 'name' => 'The "Best" Option'],
                    ['id' => 'opt2', 'name' => 'Option B'],
                ],
            ],
        ];

        $this->form->properties = array_merge($this->form->properties, [$selectField]);
        $this->form->save();

        $formData = $this->generateFormSubmissionData($this->form, [
            'select_quotes' => 'The "Best" Option',
        ]);

        $this->postJson(route('forms.answer', $this->form->slug), $formData)
            ->assertSuccessful()
            ->assertJson([
                'type' => 'success',
                'message' => 'Form submission saved.',
            ]);

        $submission = $this->form->submissions()->first();
        expect($submission->data['select_quotes'])->toBe('The "Best" Option');
    });

    it('can submit multi_select options containing special characters', function () {
        $multiSelectField = [
            'id' => 'multi_special',
            'name' => 'Choose Options',
            'type' => 'multi_select',
            'required' => true,
            'multi_select' => [
                'options' => [
                    ['id' => 'opt1', 'name' => "Tab\there"],
                    ['id' => 'opt2', 'name' => 'Back\\slash'],
                    ['id' => 'opt3', 'name' => 'Comma, inside'],
                    ['id' => 'opt4', 'name' => 'Normal option'],
                ],
            ],
        ];

        $this->form->properties = array_merge($this->form->properties, [$multiSelectField]);
        $this->form->save();

        $formData = $this->generateFormSubmissionData($this->form, [
            'multi_special' => ["Tab\there", 'Back\\slash', 'Comma, inside'],
        ]);

        $this->postJson(route('forms.answer', $this->form->slug), $formData)
            ->assertSuccessful()
            ->assertJson([
                'type' => 'success',
                'message' => 'Form submission saved.',
            ]);

        $submission = $this->form->submissions()->first();
        expect($submission->data['multi_special'])->toBe(["Tab\there", 'Back\\slash', 'Comma, inside']);
    });

    it('can submit select option containing mixed tab and unicode characters', function () {
        $selectField = [
            'id' => 'select_mixed',
            'name' => 'Choose an Option',
            'type' => 'select',
            'required' => true,
            'select' => [
                'options' => [
                    ['id' => 'opt1', 'name' => "Special\tOption — Опция"],
                    ['id' => 'opt2', 'name' => 'Normal'],
                ],
            ],
        ];

        $this->form->properties = array_merge($this->form->properties, [$selectField]);
        $this->form->save();

        $formData = $this->generateFormSubmissionData($this->form, [
            'select_mixed' => "Special\tOption — Опция",
        ]);

        $this->postJson(route('forms.answer', $this->form->slug), $formData)
            ->assertSuccessful()
            ->assertJson([
                'type' => 'success',
                'message' => 'Form submission saved.',
            ]);

        $submission = $this->form->submissions()->first();
        expect($submission->data['select_mixed'])->toBe("Special\tOption — Опция");
    });

    it('rejects invalid select option even with special characters in valid options', function () {
        $selectField = [
            'id' => 'select_invalid',
            'name' => 'Choose an Option',
            'type' => 'select',
            'required' => true,
            'select' => [
                'options' => [
                    ['id' => 'opt1', 'name' => "Valid\tOption"],
                    ['id' => 'opt2', 'name' => 'Also Valid'],
                ],
            ],
        ];

        $this->form->properties = array_merge($this->form->properties, [$selectField]);
        $this->form->save();

        $formData = $this->generateFormSubmissionData($this->form, [
            'select_invalid' => 'Not A Valid Option',
        ]);

        $this->postJson(route('forms.answer', $this->form->slug), $formData)
            ->assertStatus(422);
    });

    it('can submit select option containing carriage return', function () {
        $selectField = [
            'id' => 'select_cr',
            'name' => 'Choose an Option',
            'type' => 'select',
            'required' => true,
            'select' => [
                'options' => [
                    ['id' => 'opt1', 'name' => "Option\r\nwith CRLF"],
                    ['id' => 'opt2', 'name' => 'Option B'],
                ],
            ],
        ];

        $this->form->properties = array_merge($this->form->properties, [$selectField]);
        $this->form->save();

        $formData = $this->generateFormSubmissionData($this->form, [
            'select_cr' => "Option\r\nwith CRLF",
        ]);

        $this->postJson(route('forms.answer', $this->form->slug), $formData)
            ->assertSuccessful()
            ->assertJson([
                'type' => 'success',
                'message' => 'Form submission saved.',
            ]);

        $submission = $this->form->submissions()->first();
        expect($submission->data['select_cr'])->toBe("Option\r\nwith CRLF");
    });

    it('stores original value without escape sequences in submission data', function () {
        $optionWithTab = "Architect\t— Архитектор";
        $optionWithBackslash = 'Path\\to\\file';

        $selectField = [
            'id' => 'select_stored',
            'name' => 'Choose an Option',
            'type' => 'select',
            'required' => false,
            'select' => [
                'options' => [
                    ['id' => 'opt1', 'name' => $optionWithTab],
                    ['id' => 'opt2', 'name' => $optionWithBackslash],
                ],
            ],
        ];

        $this->form->properties = array_merge($this->form->properties, [$selectField]);
        $this->form->save();

        // Submit tab option
        $formData = $this->generateFormSubmissionData($this->form, [
            'select_stored' => $optionWithTab,
        ]);

        $this->postJson(route('forms.answer', $this->form->slug), $formData)
            ->assertSuccessful();

        $submission = $this->form->submissions()->first();
        expect($submission->data['select_stored'])->toBe($optionWithTab);
        expect($submission->data['select_stored'])->not->toContain('\\t');
        expect($submission->data['select_stored'])->toContain("\t");
    });

    it('can submit select option with a trailing backslash', function () {
        $selectField = [
            'id' => 'select_trailing_bs',
            'name' => 'Choose an Option',
            'type' => 'select',
            'required' => true,
            'select' => [
                'options' => [
                    ['id' => 'opt1', 'name' => 'Ends\\'],
                    ['id' => 'opt2', 'name' => 'Normal'],
                ],
            ],
        ];

        $this->form->properties = array_merge($this->form->properties, [$selectField]);
        $this->form->save();

        $formData = $this->generateFormSubmissionData($this->form, [
            'select_trailing_bs' => 'Ends\\',
        ]);

        $this->postJson(route('forms.answer', $this->form->slug), $formData)
            ->assertSuccessful()
            ->assertJson([
                'type' => 'success',
                'message' => 'Form submission saved.',
            ]);

        $submission = $this->form->submissions()->first();
        expect($submission->data['select_trailing_bs'])->toBe('Ends\\');
    });

    it('can submit multi_select option with trailing backslashes', function () {
        $multiSelectField = [
            'id' => 'multi_trailing_bs',
            'name' => 'Choose Options',
            'type' => 'multi_select',
            'required' => true,
            'multi_select' => [
                'options' => [
                    ['id' => 'opt1', 'name' => 'Path\\'],
                    ['id' => 'opt2', 'name' => 'Dir\\subdir\\'],
                    ['id' => 'opt3', 'name' => 'Normal'],
                ],
            ],
        ];

        $this->form->properties = array_merge($this->form->properties, [$multiSelectField]);
        $this->form->save();

        $formData = $this->generateFormSubmissionData($this->form, [
            'multi_trailing_bs' => ['Path\\', 'Dir\\subdir\\'],
        ]);

        $this->postJson(route('forms.answer', $this->form->slug), $formData)
            ->assertSuccessful()
            ->assertJson([
                'type' => 'success',
                'message' => 'Form submission saved.',
            ]);

        $submission = $this->form->submissions()->first();
        expect($submission->data['multi_trailing_bs'])->toBe(['Path\\', 'Dir\\subdir\\']);
    });
});
