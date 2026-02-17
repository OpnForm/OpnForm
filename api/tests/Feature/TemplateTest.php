<?php

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
        'creator_id' => $user->id,
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
