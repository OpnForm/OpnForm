<?php

use App\Models\User;

it('checks email uniqueness using the normalized address', function () {
    $user = $this->actingAsUser();
    User::factory()->create(['email' => 'existing@example.test']);
    $this->patchJson('/settings/profile', [
        'name' => $user->name,
        'email' => 'EXISTING@example.test',
        'current_password' => 'Abcd@1234',
    ])->assertUnprocessable()->assertJsonValidationErrors('email');
    expect($user->fresh()->email)->not->toBe('existing@example.test');
});
