<?php

use App\Models\Integration\FormIntegration;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\assertDatabaseCount;
use function Pest\Laravel\assertSoftDeleted;
use function Pest\Laravel\delete;
use function Pest\Laravel\post;
use function PHPUnit\Framework\assertEquals;

test('create an integration', function () {
    $user = User::factory()->create();
    $workspace = createUserWorkspace($user);

    $form = createForm($user, $workspace, ['title' => 'First form']);

    Sanctum::actingAs(
        $user,
        ['manage-integrations']
    );

    $this->withoutExceptionHandling();
    post(route('zapier.webhooks.store'), [
        'form_id' => $form->id,
        'hookUrl' => $hookUrl = 'https://zapier.com/hook/test'
    ])
        ->assertOk();

    assertDatabaseCount('form_integrations', 1);

    $integration = FormIntegration::first();

    assertEquals($form->id, $integration->form_id);
    assertEquals($hookUrl, $integration->data->hook_url);
});

test('cannot create an integration without a corresponding ability', function () {
    $user = User::factory()->create();
    $workspace = createUserWorkspace($user);

    $form = createForm($user, $workspace, ['title' => 'First form']);

    Sanctum::actingAs($user);

    post(route('zapier.webhooks.store'), [

        'form_id' => $form->id,
        'hookUrl' => 'https://zapier.com/hook/test'
    ])
        ->assertForbidden();

    assertDatabaseCount('form_integrations', 0);
});

test('cannot create an integration for other users form', function () {
    $user = User::factory()->create();

    $user2 = User::factory()->create();
    $workspace = createUserWorkspace($user2);

    $form = createForm($user2, $workspace, ['title' => 'First form']);

    Sanctum::actingAs($user);

    post(route('zapier.webhooks.store'), [

        'form_id' => $form->id,
        'hookUrl' => 'https://zapier.com/hook/test'
    ])
        ->assertForbidden();

    assertDatabaseCount('form_integrations', 0);
});

test('delete an integration', function () {
    $user = User::factory()->create();
    $workspace = createUserWorkspace($user);

    $form = createForm($user, $workspace, ['title' => 'First form']);

    Sanctum::actingAs(
        $user,
        ['manage-integrations']
    );

    $integration = FormIntegration::factory()
        ->for($form)
        ->create([
            'data' => [
                'hook_url' => $hookUrl = 'https://zapier.com/hook/test'
            ]
        ]);

    assertDatabaseCount('form_integrations', 1);

    delete(route('zapier.webhooks.destroy'), [
        'form_id' => $form->id,
        'hookUrl' => $hookUrl,
    ])
        ->assertOk();

    assertSoftDeleted('form_integrations', ['id' => $integration->id]);
});

test('cannot delete an integration with an incorrect hook url', function () {
    $user = User::factory()->create();
    $workspace = createUserWorkspace($user);

    $form = createForm($user, $workspace, ['title' => 'First form']);

    Sanctum::actingAs(
        $user,
        ['manage-integrations']
    );

    $integration = FormIntegration::factory()
        ->for($form)
        ->create([
            'data' => [
                'hook_url' => 'https://zapier.com/hook/test'
            ]
        ]);

    delete(route('zapier.webhooks.destroy'), [
        'form_id' => $form->id,
        'hookUrl' => 'https://google.com',
    ])
        ->assertOk();

    assertDatabaseCount('form_integrations', 1);
});

test('poll for the latest submission', function () {
    $user = User::factory()->create();
    $workspace = createUserWorkspace($user);
    $form = createForm($user, $workspace, [
        'properties' => [
            [
                'id' => 'title',
                'name' => 'Name',
                'type' => 'text',
                'hidden' => false,
                'required' => true,
                'logic' => [
                    'conditions' => null,
                    'actions' => [],
                ],
            ],
            [
                'id' => 'age',
                'name' => 'Age',
                'type' => 'number',
                'hidden' => false,
                'required' => true,
            ],
        ],
    ]);

    // Create a submission for the form
    $formData = $this->generateFormSubmissionData($form);

    $this->postJson(route('forms.answer', $form->slug), $formData)
        ->assertSuccessful()
        ->assertJson([
            'type' => 'success',
            'message' => 'Form submission saved.',
        ]);

    // Create a webhook integration for the form
    $integration = FormIntegration::factory()
        ->for($form)
        ->create([
            'data' => [
                'hook_url' => 'https://zapier.com/hook/test'
            ]
        ]);

    Sanctum::actingAs($user, ['view', 'manage-integrations']);

    // Call the poll endpoint
    $response = $this->getJson(route('zapier.webhooks.poll', ['form_id' => $form->id]));

    // Assert the response status is OK
    $response->assertOk();

    // Decode the response data
    $responseData = $response->json()[0];
    $receivedData = collect($responseData['data'])->values()->pluck('value')->toArray();

    $this->assertEmpty(array_diff(array_values($formData), $receivedData));
});

test('make up a submission when polling without any submission', function () {
    $user = User::factory()->create();
    $workspace = createUserWorkspace($user);
    $form = createForm($user, $workspace, [
        'properties' => [
            [
                'id' => 'title',
                'name' => 'Name',
                'type' => 'text',
                'hidden' => false,
                'required' => true,
                'logic' => [
                    'conditions' => null,
                    'actions' => [],
                ],
            ],
            [
                'id' => 'age',
                'name' => 'Age',
                'type' => 'number',
                'hidden' => false,
                'required' => true,
            ],
        ],
    ]);

    // Create a webhook integration for the form
    $integration = FormIntegration::factory()
        ->for($form)
        ->create([
            'data' => [
                'hook_url' => 'https://zapier.com/hook/test'
            ]
        ]);

    Sanctum::actingAs($user, ['view', 'manage-integrations']);

    // Call the poll endpoint
    $this->withoutExceptionHandling();
    $response = $this->getJson(route('zapier.webhooks.poll', ['form_id' => $form->id]));
    // Assert the response status is OK
    $response->assertOk();

    // Decode the response data
    $responseData = $response->json()[0];

    $this->assertNotEmpty($responseData['data']);
    $this->assertTrue(count($responseData['data']) == 2);
});
