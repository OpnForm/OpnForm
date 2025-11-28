<?php

it('can not create more than 1 workspace for free user', function () {
    $user = $this->actingAsUser();

    $this->postJson(route('open.workspaces.create'), [
        'name' => 'Workspace Test',
        'icon' => '🧪',
    ])
        ->assertSuccessful()
        ->assertJson([
            'type' => 'success',
            'message' => 'Workspace created.',
        ]);

    expect($user->workspaces()->count())->toBe(1);

    // Try to create another workspace
    $this->postJson(route('open.workspaces.create'), [
        'name' => 'Workspace Test 2',
        'icon' => '🧪',
    ])
        ->assertStatus(403)
        ->assertJson([
            'message' => 'You have reached the limit for free workspaces. Upgrade to Pro to create additional workspaces.',
        ]);
});

it('can create and delete Workspace', function () {
    $user = $this->actingAsProUser();

    for ($i = 1; $i <= 3; $i++) {
        $this->postJson(route('open.workspaces.create'), [
            'name' => 'Workspace Test - ' . $i,
            'icon' => '🧪',
        ])
            ->assertSuccessful()
            ->assertJson([
                'type' => 'success',
                'message' => 'Workspace created.',
            ]);
    }

    expect($user->workspaces()->count())->toBe(3);

    $i = 0;
    foreach ($user->workspaces as $workspace) {
        $i++;
        if ($i !== 3) {
            $this->deleteJson(route('open.workspaces.delete', $workspace))
                ->assertSuccessful()
                ->assertJson([
                    'type' => 'success',
                    'message' => 'Workspace successfully deleted.',
                ]);
        } else {
            // Last workspace can not delete
            $this->deleteJson(route('open.workspaces.delete', $workspace))
                ->assertStatus(403);
        }
    }
});

it('can update workspace', function () {
    $user = $this->actingAsUser();

    $this->postJson(route('open.workspaces.create'), [
        'name' => 'Workspace Test',
        'icon' => '🧪',
    ])
        ->assertSuccessful()
        ->assertJson([
            'type' => 'success',
            'message' => 'Workspace created.',
        ]);

    expect($user->workspaces()->count())->toBe(1);

    $workspace = $user->workspaces()->first();
    $this->putJson(route('open.workspaces.update', $workspace), [
        'name' => 'Workspace Test Updated',
        'icon' => '🔬',
    ])
        ->assertSuccessful()
        ->assertJson([
            'type' => 'success',
            'message' => 'Workspace updated.',
        ]);
});

it('can save custom domain for workspace', function () {
    $user = $this->actingAsProUser();
    $workspace = $this->createUserWorkspace($user);

    $this->putJson(route('open.workspaces.save-custom-domains', $workspace), [
        'custom_domains' => ['example.com']
    ])
        ->assertSuccessful();

    $workspace->refresh();
    expect($workspace->custom_domains)->toBe(['example.com']);
});

it('can set custom domain to null', function () {
    $user = $this->actingAsProUser();
    $workspace = $this->createUserWorkspace($user);
    $workspace->update(['custom_domains' => ['example.com']]);

    $this->putJson(route('open.workspaces.save-custom-domains', $workspace), [
        'custom_domains' => []
    ])
        ->assertSuccessful();

    $workspace->refresh();
    expect($workspace->custom_domains)->toBe([]);
});

it('validates custom domain format', function () {
    $user = $this->actingAsProUser();
    $workspace = $this->createUserWorkspace($user);

    $this->putJson(route('open.workspaces.save-custom-domains', $workspace), [
        'custom_domains' => ['invalid-domain']
    ])
        ->assertStatus(422)
        ->assertJson([
            'message' => 'Invalid domain: invalid-domain',
        ]);

    $this->putJson(route('open.workspaces.save-custom-domains', $workspace), [
        'custom_domains' => ['https://example.com']
    ])
        ->assertStatus(422)
        ->assertJson([
            'message' => 'Invalid domain: https://example.com',
        ]);
});

it('prevents duplicate custom domains across workspaces', function () {
    $user = $this->actingAsProUser();
    $workspace1 = $this->createUserWorkspace($user);
    $workspace2 = $this->createUserWorkspace($user);

    // Set domain for first workspace
    $this->putJson(route('open.workspaces.save-custom-domains', $workspace1), [
        'custom_domains' => ['example.com']
    ])
        ->assertSuccessful();

    // Try to set same domain for second workspace
    $this->putJson(route('open.workspaces.save-custom-domains', $workspace2), [
        'custom_domains' => ['example.com']
    ])
        ->assertStatus(422)
        ->assertJson([
            'message' => 'The domain example.com is already in use by another workspace.',
        ]);
});

it('allows same workspace to update its own custom domain', function () {
    $user = $this->actingAsProUser();
    $workspace = $this->createUserWorkspace($user);
    $workspace->update(['custom_domains' => ['example.com']]);

    // Same workspace should be able to "update" to the same domain
    $this->putJson(route('open.workspaces.save-custom-domains', $workspace), [
        'custom_domains' => ['example.com']
    ])
        ->assertSuccessful();

    $workspace->refresh();
    expect($workspace->custom_domains)->toBe(['example.com']);
});

it('accepts multi-part TLD domains like .co.uk', function () {
    $user = $this->actingAsProUser();
    $workspace = $this->createUserWorkspace($user);

    // Test .co.uk domain
    $this->putJson(route('open.workspaces.save-custom-domains', $workspace), [
        'custom_domains' => ['example.co.uk']
    ])
        ->assertSuccessful();

    $workspace->refresh();
    expect($workspace->custom_domains)->toBe(['example.co.uk']);

    // Test .co.nz domain
    $this->putJson(route('open.workspaces.save-custom-domains', $workspace), [
        'custom_domains' => ['test.co.nz']
    ])
        ->assertSuccessful();

    $workspace->refresh();
    expect($workspace->custom_domains)->toBe(['test.co.nz']);

    // Test .com.au domain
    $this->putJson(route('open.workspaces.save-custom-domains', $workspace), [
        'custom_domains' => ['domain.com.au']
    ])
        ->assertSuccessful();

    $workspace->refresh();
    expect($workspace->custom_domains)->toBe(['domain.com.au']);

    // Test subdomain with multi-part TLD
    $this->putJson(route('open.workspaces.save-custom-domains', $workspace), [
        'custom_domains' => ['subdomain.example.co.uk']
    ])
        ->assertSuccessful();

    $workspace->refresh();
    expect($workspace->custom_domains)->toBe(['subdomain.example.co.uk']);
});

it('includes users_count attribute', function () {
    $user = $this->actingAsUser();
    $workspace = $this->createUserWorkspace($user);

    // Initially should have 1 user (the creator)
    expect($workspace->users_count)->toBe(1);

    // Add another user to the workspace
    $user2 = \App\Models\User::factory()->create();
    $workspace->users()->attach($user2, ['role' => 'admin']);

    // Clear cache and check count
    $workspace->flush();
    expect($workspace->fresh()->users_count)->toBe(2);
});
