<?php

use App\Models\User;

it('prevents the last administrator from leaving, being removed or being demoted', function () {
    $user = $this->actingAsProUser();
    $workspace = $this->createUserWorkspace($user);
    $this->postJson(route('open.workspaces.leave', $workspace))->assertStatus(422);
    $this->deleteJson(route('open.workspaces.users.remove', [$workspace, $user]))->assertStatus(422);
    $this->putJson(route('open.workspaces.users.update-role', [$workspace, $user]), ['role' => User::ROLE_READONLY])->assertStatus(422);
    expect($workspace->users()->whereKey($user->id)->wherePivot('role', User::ROLE_ADMIN)->exists())->toBeTrue();
});
