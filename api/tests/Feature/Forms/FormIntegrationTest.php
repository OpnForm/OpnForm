<?php

it('can CRUD form integration', function () {
    $user = $this->actingAsProUser();
    $workspace = $this->createUserWorkspace($user);
    $form = $this->createForm($user, $workspace);

    $data = [
        'status' => 'active',
        'integration_id' => 'email',
        'logic' => null,
        'data' => [
            'send_to' => 'test@test.com',
            'sender_name' => 'OpnForm',
            'subject' => 'New form submission',
            'email_content' => 'Hello there 👋 <br>New form submission received.',
            'include_submission_data' => true,
            'include_hidden_fields_submission_data' => false,
            'reply_to' => null
        ]
    ];

    $response = $this->postJson(route('open.forms.integrations.create', $form), $data)
        ->assertSuccessful()
        ->assertJson([
            'type' => 'success',
            'message' => 'Form Integration was created.'
        ]);

    $this->getJson(route('open.forms.integrations.index', $form))
        ->assertSuccessful()
        ->assertJsonCount(1);

    $this->putJson(route('open.forms.integrations.update', [$form, $response->json('form_integration.id')]), $data)
        ->assertSuccessful()
        ->assertJson([
            'type' => 'success',
            'message' => 'Form Integration was updated.'
        ]);

    $this->deleteJson(route('open.forms.integrations.destroy', [$form, $response->json('form_integration.id')]), $data)
        ->assertSuccessful()
        ->assertJson([
            'type' => 'success',
            'message' => 'Form Integration was deleted.'
        ]);
});

it('forbids non-admin users from viewing integrations and events', function () {
    $owner = $this->actingAsProUser();
    $workspace = $this->createUserWorkspace($owner);
    $form = $this->createForm($owner, $workspace);

    // Create one integration as owner
    $this->postJson(route('open.forms.integrations.create', $form), [
        'status' => 'active',
        'integration_id' => 'email',
        'logic' => null,
        'data' => [
            'send_to' => 'test@test.com',
            'sender_name' => 'OpnForm',
            'subject' => 'Subject',
            'email_content' => 'Content',
            'include_submission_data' => false,
            'include_hidden_fields_submission_data' => false,
            'reply_to' => null
        ]
    ])->assertSuccessful();

    // Attach viewer and try to read
    $viewer = $this->createUser();
    $viewer->workspaces()->sync([$workspace->id => ['role' => 'readonly']], false);
    $this->actingAs($viewer, 'api');

    $this->getJson(route('open.forms.integrations.index', $form))
        ->assertStatus(403);

    // Events require an integration id; fetch as owner first
    $this->actingAs($owner, 'api');
    $integrationId = \App\Models\Integration\FormIntegration::where('form_id', $form->id)->value('id');

    // Now as viewer, events should be forbidden as well
    $this->actingAs($viewer, 'api');
    $this->getJson(route('open.forms.integrations.events', [$form, $integrationId]))
        ->assertStatus(403);
});

it('can create form integration with checkbox logic', function () {
    $user = $this->actingAsProUser();
    $workspace = $this->createUserWorkspace($user);
    $form = $this->createForm($user, $workspace, [
        'properties' => [
            [
                'id' => 'checkbox_field',
                'name' => 'Checkbox Field',
                'type' => 'checkbox'
            ],
            [
                'id' => 'text_field',
                'name' => 'Text Field',
                'type' => 'text',
            ],
        ],
    ]);

    $data = [
        'status' => 'active',
        'integration_id' => 'email',
        'logic' => [
            'operatorIdentifier' => 'and',
            'children' => [
                [
                    'identifier' => 'checkbox_field',
                    'value' => [
                        'operator' => 'is_checked',
                        'property_meta' => [
                            'id' => 'checkbox_field',
                            'type' => 'checkbox',
                        ]
                    ],
                ],
            ],
        ],
        'data' => [
            'send_to' => 'test@test.com',
            'sender_name' => 'OpnForm',
            'subject' => 'New form submission with checkbox logic',
            'email_content' => 'Checkbox logic triggered.',
            'include_submission_data' => true,
            'include_hidden_fields_submission_data' => false,
            'reply_to' => null
        ]
    ];

    $this->postJson(route('open.forms.integrations.create', $form), $data)
        ->assertSuccessful()
        ->assertJson([
            'type' => 'success',
            'message' => 'Form Integration was created.'
        ]);
});

describe('Webhook Integration', function () {
    it('can create webhook with secret and custom headers', function () {
        $user = $this->actingAsProUser();
        $workspace = $this->createUserWorkspace($user);
        $form = $this->createForm($user, $workspace);

        $data = [
            'status' => 'active',
            'integration_id' => 'webhook',
            'logic' => null,
            'data' => [
                'webhook_url' => 'https://example.com/webhook',
                'webhook_secret' => 'whsec_1234567890abcdefghijklmnop',
                'webhook_headers' => [
                    'X-API-Key' => 'my-api-key',
                    'X-Custom-ID' => 'custom-value'
                ]
            ]
        ];

        $response = $this->postJson(route('open.forms.integrations.create', $form), $data)
            ->assertSuccessful()
            ->assertJson([
                'type' => 'success',
                'message' => 'Form Integration was created.'
            ]);

        expect($response->json('form_integration.data.webhook_url'))
            ->toBe('https://example.com/webhook');
        expect($response->json('form_integration.data.webhook_secret'))
            ->toBe('whsec_1234567890abcdefghijklmnop');
        expect($response->json('form_integration.data.webhook_headers'))
            ->toHaveKeys(['X-API-Key', 'X-Custom-ID']);
    });

    it('can create webhook with secret only', function () {
        $user = $this->actingAsProUser();
        $workspace = $this->createUserWorkspace($user);
        $form = $this->createForm($user, $workspace);

        $data = [
            'status' => 'active',
            'integration_id' => 'webhook',
            'logic' => null,
            'data' => [
                'webhook_url' => 'https://example.com/webhook',
                'webhook_secret' => 'whsec_verylongsecretstring123456'
            ]
        ];

        $response = $this->postJson(route('open.forms.integrations.create', $form), $data)
            ->assertSuccessful();

        expect($response->json('form_integration.data.webhook_secret'))
            ->toBe('whsec_verylongsecretstring123456');
        expect($response->json('form_integration.data.webhook_headers'))
            ->toBeNull();
    });

    it('can create webhook with headers only', function () {
        $user = $this->actingAsProUser();
        $workspace = $this->createUserWorkspace($user);
        $form = $this->createForm($user, $workspace);

        $data = [
            'status' => 'active',
            'integration_id' => 'webhook',
            'logic' => null,
            'data' => [
                'webhook_url' => 'https://example.com/webhook',
                'webhook_headers' => [
                    'X-Event-Type' => 'submission',
                    'X-User-Agent' => 'my-app/1.0'
                ]
            ]
        ];

        $response = $this->postJson(route('open.forms.integrations.create', $form), $data)
            ->assertSuccessful();

        expect($response->json('form_integration.data.webhook_headers'))
            ->toHaveKeys(['X-Event-Type', 'X-User-Agent']);
        expect($response->json('form_integration.data.webhook_secret'))
            ->toBeNull();
    });

    it('can create webhook without secret or headers', function () {
        $user = $this->actingAsProUser();
        $workspace = $this->createUserWorkspace($user);
        $form = $this->createForm($user, $workspace);

        $data = [
            'status' => 'active',
            'integration_id' => 'webhook',
            'logic' => null,
            'data' => [
                'webhook_url' => 'https://example.com/webhook'
            ]
        ];

        $response = $this->postJson(route('open.forms.integrations.create', $form), $data)
            ->assertSuccessful();

        expect($response->json('form_integration.data.webhook_url'))
            ->toBe('https://example.com/webhook');
    });

    it('rejects webhook with secret shorter than 12 characters', function () {
        $user = $this->actingAsProUser();
        $workspace = $this->createUserWorkspace($user);
        $form = $this->createForm($user, $workspace);

        $data = [
            'status' => 'active',
            'integration_id' => 'webhook',
            'logic' => null,
            'data' => [
                'webhook_url' => 'https://example.com/webhook',
                'webhook_secret' => 'short'
            ]
        ];

        $this->postJson(route('open.forms.integrations.create', $form), $data)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['data.webhook_secret']);
    });

    it('rejects webhook with blocked Authorization header', function () {
        $user = $this->actingAsProUser();
        $workspace = $this->createUserWorkspace($user);
        $form = $this->createForm($user, $workspace);

        $data = [
            'status' => 'active',
            'integration_id' => 'webhook',
            'logic' => null,
            'data' => [
                'webhook_url' => 'https://example.com/webhook',
                'webhook_headers' => [
                    'Authorization' => 'Bearer token123'
                ]
            ]
        ];

        $this->postJson(route('open.forms.integrations.create', $form), $data)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['data.webhook_headers']);
    });

    it('rejects webhook with blocked Cookie header', function () {
        $user = $this->actingAsProUser();
        $workspace = $this->createUserWorkspace($user);
        $form = $this->createForm($user, $workspace);

        $data = [
            'status' => 'active',
            'integration_id' => 'webhook',
            'logic' => null,
            'data' => [
                'webhook_url' => 'https://example.com/webhook',
                'webhook_headers' => [
                    'Cookie' => 'session=abc123'
                ]
            ]
        ];

        $this->postJson(route('open.forms.integrations.create', $form), $data)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['data.webhook_headers']);
    });

    it('rejects webhook with blocked X-CSRF-Token header (case-insensitive)', function () {
        $user = $this->actingAsProUser();
        $workspace = $this->createUserWorkspace($user);
        $form = $this->createForm($user, $workspace);

        $data = [
            'status' => 'active',
            'integration_id' => 'webhook',
            'logic' => null,
            'data' => [
                'webhook_url' => 'https://example.com/webhook',
                'webhook_headers' => [
                    'x-csrf-token' => 'token123'
                ]
            ]
        ];

        $this->postJson(route('open.forms.integrations.create', $form), $data)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['data.webhook_headers']);
    });

    it('rejects webhook with more than 10 custom headers', function () {
        $user = $this->actingAsProUser();
        $workspace = $this->createUserWorkspace($user);
        $form = $this->createForm($user, $workspace);

        $tooManyHeaders = [
            'X-Header-1' => 'value1',
            'X-Header-2' => 'value2',
            'X-Header-3' => 'value3',
            'X-Header-4' => 'value4',
            'X-Header-5' => 'value5',
            'X-Header-6' => 'value6',
            'X-Header-7' => 'value7',
            'X-Header-8' => 'value8',
            'X-Header-9' => 'value9',
            'X-Header-10' => 'value10',
            'X-Header-11' => 'value11'
        ];

        $data = [
            'status' => 'active',
            'integration_id' => 'webhook',
            'logic' => null,
            'data' => [
                'webhook_url' => 'https://example.com/webhook',
                'webhook_headers' => $tooManyHeaders
            ]
        ];

        $this->postJson(route('open.forms.integrations.create', $form), $data)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['data.webhook_headers']);
    });

    it('rejects webhook with header name exceeding 255 characters', function () {
        $user = $this->actingAsProUser();
        $workspace = $this->createUserWorkspace($user);
        $form = $this->createForm($user, $workspace);

        $longHeaderName = str_repeat('X', 256);

        $data = [
            'status' => 'active',
            'integration_id' => 'webhook',
            'logic' => null,
            'data' => [
                'webhook_url' => 'https://example.com/webhook',
                'webhook_headers' => [
                    $longHeaderName => 'value'
                ]
            ]
        ];

        $this->postJson(route('open.forms.integrations.create', $form), $data)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['data.webhook_headers']);
    });

    it('rejects webhook with header value exceeding 255 characters', function () {
        $user = $this->actingAsProUser();
        $workspace = $this->createUserWorkspace($user);
        $form = $this->createForm($user, $workspace);

        $longHeaderValue = str_repeat('x', 256);

        $data = [
            'status' => 'active',
            'integration_id' => 'webhook',
            'logic' => null,
            'data' => [
                'webhook_url' => 'https://example.com/webhook',
                'webhook_headers' => [
                    'X-API-Key' => $longHeaderValue
                ]
            ]
        ];

        $this->postJson(route('open.forms.integrations.create', $form), $data)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['data.webhook_headers']);
    });

    it('can update webhook to rotate secret', function () {
        $user = $this->actingAsProUser();
        $workspace = $this->createUserWorkspace($user);
        $form = $this->createForm($user, $workspace);

        // Create webhook with initial secret
        $createData = [
            'status' => 'active',
            'integration_id' => 'webhook',
            'logic' => null,
            'data' => [
                'webhook_url' => 'https://example.com/webhook',
                'webhook_secret' => 'whsec_oldsecret1234567890abc'
            ]
        ];

        $createResponse = $this->postJson(route('open.forms.integrations.create', $form), $createData)
            ->assertSuccessful();

        $integrationId = $createResponse->json('form_integration.id');

        // Update with new secret
        $updateData = [
            'status' => 'active',
            'integration_id' => 'webhook',
            'logic' => null,
            'data' => [
                'webhook_url' => 'https://example.com/webhook',
                'webhook_secret' => 'whsec_newsecret1234567890xyz'
            ]
        ];

        $updateResponse = $this->putJson(route('open.forms.integrations.update', [$form, $integrationId]), $updateData)
            ->assertSuccessful();

        expect($updateResponse->json('form_integration.data.webhook_secret'))
            ->toBe('whsec_newsecret1234567890xyz');
    });

    it('can update webhook headers', function () {
        $user = $this->actingAsProUser();
        $workspace = $this->createUserWorkspace($user);
        $form = $this->createForm($user, $workspace);

        // Create webhook with initial headers
        $createData = [
            'status' => 'active',
            'integration_id' => 'webhook',
            'logic' => null,
            'data' => [
                'webhook_url' => 'https://example.com/webhook',
                'webhook_headers' => [
                    'X-API-Key' => 'old-key'
                ]
            ]
        ];

        $createResponse = $this->postJson(route('open.forms.integrations.create', $form), $createData)
            ->assertSuccessful();

        $integrationId = $createResponse->json('form_integration.id');

        // Update with new headers
        $updateData = [
            'status' => 'active',
            'integration_id' => 'webhook',
            'logic' => null,
            'data' => [
                'webhook_url' => 'https://example.com/webhook',
                'webhook_headers' => [
                    'X-API-Key' => 'new-key',
                    'X-Request-ID' => 'req-123'
                ]
            ]
        ];

        $updateResponse = $this->putJson(route('open.forms.integrations.update', [$form, $integrationId]), $updateData)
            ->assertSuccessful();

        $headers = $updateResponse->json('form_integration.data.webhook_headers');
        expect($headers['X-API-Key'])->toBe('new-key');
        expect($headers['X-Request-ID'])->toBe('req-123');
    });
});
