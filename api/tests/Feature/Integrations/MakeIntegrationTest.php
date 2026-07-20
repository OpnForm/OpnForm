<?php

use App\Models\Integration\FormIntegration;
use Illuminate\Support\Facades\Http;

test('make integration fires webhook on form submission with correct payload', function () {
    Http::fake([
        'https://example.com/*' => Http::response(['ok' => true], 200),
    ]);

    $user = $this->actingAsProUser();
    $workspace = $this->createUserWorkspace($user);
    $form = $this->createForm($user, $workspace, [
        'properties' => [
            [
                'id' => 'name',
                'name' => 'Name',
                'type' => 'text',
                'hidden' => false,
                'required' => true,
            ],
        ],
    ]);

    $this->postJson(route('open.forms.integrations.create', $form), [
        'status' => 'active',
        'integration_id' => 'make',
        'logic' => null,
        'data' => [
            'webhook_url' => 'https://example.com/make-hook/test123',
        ],
    ])->assertSuccessful();

    $submissionData = $this->generateFormSubmissionData($form);

    $this->postJson(route('forms.answer', $form->slug), $submissionData)
        ->assertSuccessful();

    Http::assertSent(function ($request) use ($form) {
        $payload = json_decode($request->body(), true);

        return $request->url() === 'https://example.com/make-hook/test123'
            && $payload['form_id'] === $form->id
            && isset($payload['data'])
            && isset($payload['form_title'])
            && !isset($payload['submission'])
            && !isset($payload['message']);
    });
});

test('make integration rejects private webhook urls', function () {
    $user = $this->actingAsProUser();
    $workspace = $this->createUserWorkspace($user);
    $form = $this->createForm($user, $workspace);

    foreach ([
        'http://169.254.169.254/latest/meta-data/',
        'https://127.0.0.1/webhook',
        'https://localhost/webhook',
        'https://10.0.0.5/webhook',
    ] as $url) {
        $this->postJson(route('open.forms.integrations.create', $form), [
            'status' => 'active',
            'integration_id' => 'make',
            'logic' => null,
            'data' => [
                'webhook_url' => $url,
            ],
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['data.webhook_url']);
    }
});

test('make integration stores and returns provider_url', function () {
    $user = $this->actingAsProUser();
    $workspace = $this->createUserWorkspace($user);
    $form = $this->createForm($user, $workspace);

    $response = $this->postJson(route('open.forms.integrations.create', $form), [
        'status' => 'active',
        'integration_id' => 'make',
        'logic' => null,
        'data' => [
            'webhook_url' => 'https://example.com/make-hook/test123',
            'provider_url' => 'https://www.make.com/scenario/12345/edit',
        ],
    ])->assertSuccessful();

    expect($response->json('form_integration.data.webhook_url'))
        ->toBe('https://example.com/make-hook/test123');
    expect($response->json('form_integration.data.provider_url'))
        ->toBe('https://www.make.com/scenario/12345/edit');
});

test('make integration provider_url is optional', function () {
    $user = $this->actingAsProUser();
    $workspace = $this->createUserWorkspace($user);
    $form = $this->createForm($user, $workspace);

    $response = $this->postJson(route('open.forms.integrations.create', $form), [
        'status' => 'active',
        'integration_id' => 'make',
        'logic' => null,
        'data' => [
            'webhook_url' => 'https://example.com/make-hook/test123',
        ],
    ])->assertSuccessful();

    expect($response->json('form_integration.data.webhook_url'))
        ->toBe('https://example.com/make-hook/test123');
});

test('make integration logs success event after webhook fires', function () {
    Http::fake([
        'https://example.com/*' => Http::response(['ok' => true], 200),
    ]);

    $user = $this->actingAsProUser();
    $workspace = $this->createUserWorkspace($user);
    $form = $this->createForm($user, $workspace, [
        'properties' => [
            [
                'id' => 'name',
                'name' => 'Name',
                'type' => 'text',
                'hidden' => false,
                'required' => true,
            ],
        ],
    ]);

    $this->postJson(route('open.forms.integrations.create', $form), [
        'status' => 'active',
        'integration_id' => 'make',
        'logic' => null,
        'data' => [
            'webhook_url' => 'https://example.com/make-hook/test123',
        ],
    ])->assertSuccessful();

    $submissionData = $this->generateFormSubmissionData($form);

    $this->postJson(route('forms.answer', $form->slug), $submissionData)
        ->assertSuccessful();

    $integration = FormIntegration::where('integration_id', 'make')->first();

    $this->assertDatabaseHas('form_integrations_events', [
        'integration_id' => $integration->id,
        'status' => 'success',
    ]);
});
