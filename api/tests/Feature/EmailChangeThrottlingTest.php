<?php

use App\Models\User;

it('allows first two email changes within an hour', function () {
    $user = User::factory()->create([
        'email' => 'original@example.com'
    ]);

    $this->actingAs($user);

    // First email change - should succeed
    $response = $this->patchJson('/settings/profile', [
        'name' => 'Test User',
        'email' => 'first@example.com',
        'current_password' => 'Abcd@1234',
    ]);
    $response->assertSuccessful();

    // Second email change - should succeed
    $response = $this->patchJson('/settings/profile', [
        'name' => 'Test User',
        'email' => 'second@example.com',
        'current_password' => 'Abcd@1234',
    ]);
    $response->assertSuccessful();
});

it('blocks third email change within an hour', function () {
    $user = User::factory()->create([
        'email' => 'original@example.com'
    ]);

    $this->actingAs($user);

    // First email change - should succeed
    $response = $this->patchJson('/settings/profile', [
        'name' => 'Test User',
        'email' => 'first@example.com',
        'current_password' => 'Abcd@1234',
    ]);
    $response->assertSuccessful();

    // Second email change - should succeed
    $response = $this->patchJson('/settings/profile', [
        'name' => 'Test User',
        'email' => 'second@example.com',
        'current_password' => 'Abcd@1234',
    ]);
    $response->assertSuccessful();

    // Third email change - should be blocked
    $response = $this->patchJson('/settings/profile', [
        'name' => 'Test User',
        'email' => 'third@example.com',
        'current_password' => 'Abcd@1234',
    ]);
    $response->assertStatus(429)
        ->assertJson([
            'message' => 'Too Many Attempts.'
        ]);
});

it('does not throttle when email is not changed', function () {
    $user = User::factory()->create([
        'email' => 'test@example.com'
    ]);

    $this->actingAs($user);

    // Update name only - should not be throttled
    $response = $this->patchJson('/settings/profile', [
        'name' => 'Updated Name',
        'email' => 'test@example.com', // Same email
    ]);
    $response->assertSuccessful();

    // Update name again - should still work
    $response = $this->patchJson('/settings/profile', [
        'name' => 'Another Name',
        'email' => 'test@example.com', // Same email
    ]);
    $response->assertSuccessful();
});

it('resets throttle counter after one hour', function () {
    $user = User::factory()->create([
        'email' => 'original@example.com'
    ]);

    $this->actingAs($user);

    // First email change
    $response = $this->patchJson('/settings/profile', [
        'name' => 'Test User',
        'email' => 'first@example.com',
        'current_password' => 'Abcd@1234',
    ]);
    $response->assertSuccessful();

    // Second email change
    $response = $this->patchJson('/settings/profile', [
        'name' => 'Test User',
        'email' => 'second@example.com',
        'current_password' => 'Abcd@1234',
    ]);
    $response->assertSuccessful();

    // Third email change - should be blocked
    $response = $this->patchJson('/settings/profile', [
        'name' => 'Test User',
        'email' => 'third@example.com',
        'current_password' => 'Abcd@1234',
    ]);
    $response->assertStatus(429);

    $this->travel(1)->hours();

    // Should work again after cache is cleared
    $response = $this->patchJson('/settings/profile', [
        'name' => 'Test User',
        'email' => 'third@example.com',
        'current_password' => 'Abcd@1234',
    ]);
    $response->assertSuccessful();
});
