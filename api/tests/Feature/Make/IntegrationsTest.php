<?php

use App\Models\Integration\FormIntegration;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\assertDatabaseCount;
use function Pest\Laravel\assertSoftDeleted;
use function Pest\Laravel\delete;
use function Pest\Laravel\post;
use function PHPUnit\Framework\assertEquals;

function makeIntegrationTokenAbilities(): array
{
    return ['manage-integrations'];
}

test('create a make integration', function () {
    $user = User::factory()->create();
    $workspace = createUserWorkspace($user);
    $form = createForm($user, $workspace, ['title' => 'First form']);

    Sanctum::actingAs($user, makeIntegrationTokenAbilities());

    $this->withoutExceptionHandling();
    post(route('make.webhooks.store'), [
        'form_id' => $form->id,
        'hookUrl' => $hookUrl = 'https://example.com/make-hook/test123',
    ])->assertOk();

    assertDatabaseCount('form_integrations', 1);

    $integration = FormIntegration::first();

    assertEquals($form->id, $integration->form_id);
    assertEquals('make', $integration->integration_id);
    assertEquals($hookUrl, $integration->data->webhook_url);
});

test('writable workspace members can create make integrations', function () {
    $owner = User::factory()->create();
    $workspace = createUserWorkspace($owner);
    $form = createForm($owner, $workspace, ['title' => 'First form']);

    $member = User::factory()->create();
    $member->workspaces()->sync([$workspace->id => ['role' => 'user']], false);

    Sanctum::actingAs($member, makeIntegrationTokenAbilities());

    post(route('make.webhooks.store'), [
        'form_id' => $form->id,
        'hookUrl' => $hookUrl = 'https://example.com/make-hook/test123',
    ])->assertOk();

    assertDatabaseCount('form_integrations', 1);

    $integration = FormIntegration::first();

    assertEquals($form->id, $integration->form_id);
    assertEquals($hookUrl, $integration->data->webhook_url);
});

test('readonly workspace members cannot create make integrations', function () {
    $owner = User::factory()->create();
    $workspace = createUserWorkspace($owner);
    $form = createForm($owner, $workspace, ['title' => 'First form']);

    $readonly = User::factory()->create();
    $readonly->workspaces()->sync([$workspace->id => ['role' => 'readonly']], false);

    Sanctum::actingAs($readonly, makeIntegrationTokenAbilities());

    post(route('make.webhooks.store'), [
        'form_id' => $form->id,
        'hookUrl' => 'https://example.com/make-hook/test123',
    ])->assertForbidden();

    assertDatabaseCount('form_integrations', 0);
});

test('cannot create a make integration without a corresponding ability', function () {
    $user = User::factory()->create();
    $workspace = createUserWorkspace($user);
    $form = createForm($user, $workspace, ['title' => 'First form']);

    Sanctum::actingAs($user);

    post(route('make.webhooks.store'), [
        'form_id' => $form->id,
        'hookUrl' => 'https://example.com/make-hook/test123',
    ])->assertForbidden();

    assertDatabaseCount('form_integrations', 0);
});

test('cannot create a make integration with a private hook url', function () {
    $user = User::factory()->create();
    $workspace = createUserWorkspace($user);
    $form = createForm($user, $workspace, ['title' => 'First form']);

    Sanctum::actingAs($user, makeIntegrationTokenAbilities());

    post(route('make.webhooks.store'), [
        'form_id' => $form->id,
        'hookUrl' => 'https://169.254.169.254/latest/meta-data/',
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('hookUrl');

    assertDatabaseCount('form_integrations', 0);
});

test('cannot create a make integration for other users form', function () {
    $user = User::factory()->create();

    $user2 = User::factory()->create();
    $workspace = createUserWorkspace($user2);
    $form = createForm($user2, $workspace, ['title' => 'First form']);

    Sanctum::actingAs($user, makeIntegrationTokenAbilities());

    post(route('make.webhooks.store'), [
        'form_id' => $form->id,
        'hookUrl' => 'https://example.com/make-hook/test123',
    ])->assertForbidden();

    assertDatabaseCount('form_integrations', 0);
});

test('delete a make integration', function () {
    $user = User::factory()->create();
    $workspace = createUserWorkspace($user);
    $form = createForm($user, $workspace, ['title' => 'First form']);

    Sanctum::actingAs($user, makeIntegrationTokenAbilities());

    $integration = FormIntegration::factory()
        ->for($form)
        ->create([
            'integration_id' => 'make',
            'data' => [
                'webhook_url' => $hookUrl = 'https://example.com/make-hook/test123',
            ],
        ]);

    assertDatabaseCount('form_integrations', 1);

    delete(route('make.webhooks.destroy'), [
        'form_id' => $form->id,
        'hookUrl' => $hookUrl,
    ])->assertOk();

    assertSoftDeleted('form_integrations', ['id' => $integration->id]);
});

test('cannot delete a make integration with an incorrect hook url', function () {
    $user = User::factory()->create();
    $workspace = createUserWorkspace($user);
    $form = createForm($user, $workspace, ['title' => 'First form']);

    Sanctum::actingAs($user, makeIntegrationTokenAbilities());

    FormIntegration::factory()
        ->for($form)
        ->create([
            'integration_id' => 'make',
            'data' => [
                'webhook_url' => 'https://example.com/make-hook/test123',
            ],
        ]);

    delete(route('make.webhooks.destroy'), [
        'form_id' => $form->id,
        'hookUrl' => 'https://google.com',
    ])->assertOk();

    assertDatabaseCount('form_integrations', 1);
});

test('readonly members cannot delete make integrations', function () {
    $owner = User::factory()->create();
    $workspace = createUserWorkspace($owner);
    $form = createForm($owner, $workspace, ['title' => 'First form']);

    FormIntegration::factory()
        ->for($form)
        ->create([
            'integration_id' => 'make',
            'data' => [
                'webhook_url' => $hookUrl = 'https://example.com/make-hook/test123',
            ],
        ]);

    $readonly = User::factory()->create();
    $readonly->workspaces()->sync([$workspace->id => ['role' => 'readonly']], false);

    Sanctum::actingAs($readonly, makeIntegrationTokenAbilities());

    delete(route('make.webhooks.destroy'), [
        'form_id' => $form->id,
        'hookUrl' => $hookUrl,
    ])->assertForbidden();

    assertDatabaseCount('form_integrations', 1);
});

test('poll for the latest submission via make', function () {
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

    $formData = $this->generateFormSubmissionData($form);

    $this->postJson(route('forms.answer', $form->slug), $formData)
        ->assertSuccessful();

    Sanctum::actingAs($user, makeIntegrationTokenAbilities());

    $response = $this->getJson(route('make.webhooks.poll', ['form_id' => $form->id]));

    $response->assertOk();

    $responseData = $response->json()[0];

    expect($responseData)->toHaveKey('data');
    expect($responseData)->toHaveKey('form_id');
    expect($responseData)->toHaveKey('form_title');
});

test('poll generates fake data when no submissions exist via make', function () {
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
            ],
        ],
    ]);

    Sanctum::actingAs($user, makeIntegrationTokenAbilities());

    $this->withoutExceptionHandling();
    $response = $this->getJson(route('make.webhooks.poll', ['form_id' => $form->id]));

    $response->assertOk();

    $responseData = $response->json()[0];

    expect($responseData['data'])->not->toBeEmpty();
});

test('readonly members cannot poll make submissions', function () {
    $owner = User::factory()->create();
    $workspace = createUserWorkspace($owner);
    $form = createForm($owner, $workspace, ['title' => 'First form']);

    $readonly = User::factory()->create();
    $readonly->workspaces()->sync([$workspace->id => ['role' => 'readonly']], false);

    Sanctum::actingAs($readonly, makeIntegrationTokenAbilities());

    $this->getJson(route('make.webhooks.poll', ['form_id' => $form->id]))
        ->assertForbidden();
});

test('validate make auth endpoint returns user info', function () {
    $user = User::factory()->create([
        'name' => 'Test User',
        'email' => 'test@opnform.com',
    ]);

    Sanctum::actingAs($user);

    $response = $this->getJson(route('make.validate'));

    $response->assertOk()
        ->assertJson([
            'name' => 'Test User',
            'email' => 'test@opnform.com',
        ]);
});

test('list workspaces via make endpoint', function () {
    $user = User::factory()->create();
    $workspace = createUserWorkspace($user);

    Sanctum::actingAs($user, ['workspaces-read']);

    $response = $this->getJson(route('make.workspaces'));

    $response->assertOk();
    $response->assertJsonFragment([
        'id' => $workspace->id,
        'name' => $workspace->name,
    ]);
});

test('list forms via make endpoint', function () {
    $user = User::factory()->create();
    $workspace = createUserWorkspace($user);
    $form = createForm($user, $workspace, ['title' => 'Test Form']);

    Sanctum::actingAs($user, ['forms-read']);

    $response = $this->getJson(route('make.forms', ['workspace_id' => $workspace->id]));

    $response->assertOk();
    $response->assertJsonFragment([
        'id' => $form->id,
        'name' => 'Test Form',
    ]);
});
