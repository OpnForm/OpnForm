<?php

use App\Models\Forms\Form;
use App\Models\Template;

it('can create template', function () {
    $user = $this->createUser([
        'email' => 'admin@opnform.com',
    ]);
    $this->actingAsUser($user);

    // Create Form
    $workspace = $this->createUserWorkspace($user);
    $form = $this->makeForm($user, $workspace);

    // Create Template
    $templateData = [
        'name' => 'Demo Template',
        'slug' => 'demo_template',
        'short_description' => 'Short description here...',
        'description' => 'Some long description here...',
        'image_url' => 'https://d3ietpyl4f2d18.cloudfront.net/6c35a864-ee3a-4039-80a4-040b6c20ac60/img/pages/welcome/product_cover.jpg',
        'publicly_listed' => true,
        'form' => $form->getAttributes(),
        'questions' => [['question' => 'Question 1', 'answer' => 'Answer 1 will be here...']],
    ];
    $this->postJson(route('templates.create', $templateData))
        ->assertSuccessful()
        ->assertJson([
            'type' => 'success',
            'message' => 'Template was created.',
        ]);
});

it('returns single template object when fetching by slug', function () {
    $user = $this->createUser([
        'email' => 'admin@opnform.com',
    ]);
    $this->actingAsUser($user);

    // Create a workspace and form for the template
    $workspace = $this->createUserWorkspace($user);
    $form = $this->makeForm($user, $workspace);

    // Create a template directly in the database
    $template = Template::create([
        'name' => 'Test Template for Show',
        'slug' => 'test-template-for-show',
        'short_description' => 'A test template',
        'description' => 'A test template description',
        'image_url' => 'https://example.com/image.jpg',
        'publicly_listed' => true,
        'structure' => $form->getAttributes(),
        'questions' => [],
        'industries' => [],
        'types' => [],
    ]);
    $template->creator_id = $user->id;
    $template->save();

    // Fetch the template by slug - should return a single object, not an array
    $response = $this->getJson(route('templates.show', ['slug' => $template->slug]));

    $response->assertSuccessful();

    // Verify response is a single template object with expected properties
    $responseData = $response->json();

    // The response should be an object (associative array), not a sequential array
    expect($responseData)->toBeArray();
    expect($responseData)->toHaveKey('slug');
    expect($responseData)->toHaveKey('name');
    expect($responseData)->toHaveKey('structure');
    expect($responseData['slug'])->toBe('test-template-for-show');
    expect($responseData['name'])->toBe('Test Template for Show');

    // Ensure it's not wrapped in an array (the bug we fixed)
    // If it were an array of templates, $responseData[0] would exist
    expect(isset($responseData[0]))->toBeFalse();
});

it('returns empty response when template slug does not exist', function () {
    // Clear cache to ensure fresh state
    \Cache::forget('prod_templates');

    // Mock the config to disable prod templates for this test
    config(['app.self_hosted' => false]);

    $response = $this->getJson(route('templates.show', ['slug' => 'non-existent-template-slug-xyz-123']));

    $response->assertSuccessful();

    // Should return null/empty when template not found
    // Laravel returns empty string for null responses
    expect($response->getContent())->toBeEmpty();
});

it('sanitizes FAQ answers and strips tags from FAQ questions', function () {
    $user = $this->createUser([
        'email' => 'admin@opnform.com',
    ]);
    $this->actingAsUser($user);

    $workspace = $this->createUserWorkspace($user);
    $form = $this->makeForm($user, $workspace);

    $templateData = [
        'name' => 'Sanitized FAQ Template',
        'slug' => 'sanitized-faq-template',
        'short_description' => 'Template with sanitized FAQ data',
        'description' => '<p>Safe description</p>',
        'image_url' => 'https://example.com/image.jpg',
        'publicly_listed' => true,
        'form' => $form->getAttributes(),
        'questions' => [[
            'question' => '<img src=x onerror=alert(1)>What is this?',
            'answer' => '<p onclick=alert(1)><script>alert(1)</script><strong>Safe</strong></p>',
        ]],
    ];

    $response = $this->postJson(route('templates.create'), $templateData)
        ->assertSuccessful();

    $templateId = $response->json('template_id');
    $template = Template::findOrFail($templateId);
    $faq = $template->questions[0] ?? [];

    expect($faq['question'] ?? '')->toBe('What is this?')
        ->and($faq['answer'] ?? '')->toContain('<strong>Safe</strong>')
        ->and($faq['answer'] ?? '')->not->toContain('<script')
        ->and($faq['answer'] ?? '')->not->toContain('onclick=');
});

function templateRequestPayload(Form $form, array $overrides = []): array
{
    return array_merge([
        'name' => 'Updated Template',
        'slug' => 'updated-template',
        'short_description' => 'Updated short description',
        'description' => 'Updated description',
        'image_url' => 'https://example.com/image.jpg',
        'publicly_listed' => true,
        'form' => $form->getAttributes(),
        'types' => [],
        'industries' => [],
        'related_templates' => [],
        'questions' => [],
    ], $overrides);
}

it('does not allow changing creator_id on template update', function () {
    $owner = $this->createUser(['email' => 'owner@example.com']);
    $attacker = $this->createUser(['email' => 'attacker@example.com']);
    $this->actingAsUser($attacker);

    $workspace = $this->createUserWorkspace($owner);
    $form = $this->makeForm($owner, $workspace);

    $template = Template::create([
        'name' => 'Owned Template',
        'slug' => 'owned-template',
        'short_description' => 'Short description',
        'description' => 'Description',
        'image_url' => 'https://example.com/image.jpg',
        'publicly_listed' => false,
        'structure' => $form->getAttributes(),
        'questions' => [],
        'industries' => [],
        'types' => [],
    ]);
    $template->creator_id = $owner->id;
    $template->save();

    config(['opnform.admin_emails' => [$attacker->email]]);

    $this->putJson(route('templates.update', ['id' => $template->id]), templateRequestPayload($form, [
        'creator_id' => $attacker->id,
        'name' => 'Hijacked Template',
    ]))->assertSuccessful();

    $template->refresh();

    expect($template->creator_id)->toBe($owner->id)
        ->and($template->name)->toBe('Hijacked Template');
});

it('does not allow regular users to list templates publicly on update', function () {
    $user = $this->createUser(['email' => 'creator@example.com']);
    $this->actingAsUser($user);

    $workspace = $this->createUserWorkspace($user);
    $form = $this->makeForm($user, $workspace);

    $template = Template::create([
        'name' => 'Private Template',
        'slug' => 'private-template',
        'short_description' => 'Short description',
        'description' => 'Description',
        'image_url' => 'https://example.com/image.jpg',
        'publicly_listed' => false,
        'structure' => $form->getAttributes(),
        'questions' => [],
        'industries' => [],
        'types' => [],
    ]);
    $template->creator_id = $user->id;
    $template->save();

    $this->putJson(route('templates.update', ['id' => $template->id]), templateRequestPayload($form, [
        'slug' => 'private-template',
        'publicly_listed' => true,
    ]))->assertSuccessful();

    expect($template->fresh()->publicly_listed)->toBeFalse();
});

it('allows template editors to update publicly_listed on update', function () {
    config(['opnform.template_editor_emails' => ['editor@example.com']]);

    $user = $this->createUser(['email' => 'editor@example.com']);
    $this->actingAsUser($user);

    $workspace = $this->createUserWorkspace($user);
    $form = $this->makeForm($user, $workspace);

    $template = Template::create([
        'name' => 'Editor Template',
        'slug' => 'editor-template',
        'short_description' => 'Short description',
        'description' => 'Description',
        'image_url' => 'https://example.com/image.jpg',
        'publicly_listed' => false,
        'structure' => $form->getAttributes(),
        'questions' => [],
        'industries' => [],
        'types' => [],
    ]);
    $template->creator_id = $user->id;
    $template->save();

    $this->putJson(route('templates.update', ['id' => $template->id]), templateRequestPayload($form, [
        'slug' => 'editor-template',
        'publicly_listed' => true,
    ]))->assertSuccessful();

    expect($template->fresh()->publicly_listed)->toBeTrue();
});

it('does not change publicly_listed when editors omit the field on update', function () {
    config(['opnform.template_editor_emails' => ['editor@example.com']]);

    $user = $this->createUser(['email' => 'editor@example.com']);
    $this->actingAsUser($user);

    $workspace = $this->createUserWorkspace($user);
    $form = $this->makeForm($user, $workspace);

    $template = Template::create([
        'name' => 'Listed Editor Template',
        'slug' => 'listed-editor-template',
        'short_description' => 'Short description',
        'description' => 'Description',
        'image_url' => 'https://example.com/image.jpg',
        'publicly_listed' => true,
        'structure' => $form->getAttributes(),
        'questions' => [],
        'industries' => [],
        'types' => [],
    ]);
    $template->creator_id = $user->id;
    $template->save();

    $payload = templateRequestPayload($form, [
        'slug' => 'listed-editor-template',
        'name' => 'Renamed Editor Template',
    ]);
    unset($payload['publicly_listed']);

    $this->putJson(route('templates.update', ['id' => $template->id]), $payload)
        ->assertSuccessful();

    $template->refresh();

    expect($template->name)->toBe('Renamed Editor Template')
        ->and($template->publicly_listed)->toBeTrue();
});

it('stores empty arrays instead of null for template list fields on update', function () {
    config(['opnform.template_editor_emails' => ['editor@example.com']]);

    $user = $this->createUser(['email' => 'editor@example.com']);
    $this->actingAsUser($user);

    $workspace = $this->createUserWorkspace($user);
    $form = $this->makeForm($user, $workspace);

    $template = Template::create([
        'name' => 'Array Template',
        'slug' => 'array-template',
        'short_description' => 'Short description',
        'description' => 'Description',
        'image_url' => 'https://example.com/image.jpg',
        'publicly_listed' => false,
        'structure' => $form->getAttributes(),
        'questions' => [['question' => 'Q1', 'answer' => 'A1']],
        'industries' => ['healthcare'],
        'types' => ['survey'],
        'related_templates' => ['other-template'],
    ]);
    $template->creator_id = $user->id;
    $template->save();

    $payload = templateRequestPayload($form, [
        'slug' => 'array-template',
        'types' => null,
        'industries' => null,
        'related_templates' => null,
        'questions' => null,
    ]);

    $this->putJson(route('templates.update', ['id' => $template->id]), $payload)
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['types', 'industries', 'related_templates', 'questions']);

    $template->refresh();

    expect($template->types)->toBe(['survey'])
        ->and($template->industries)->toBe(['healthcare'])
        ->and($template->related_templates)->toBe(['other-template'])
        ->and($template->questions)->toBe([['question' => 'Q1', 'answer' => 'A1']]);
});
