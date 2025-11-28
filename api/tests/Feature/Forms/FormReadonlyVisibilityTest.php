<?php

it('hides password for readonly members but shows for admins and users', function () {
    // Owner (admin)
    $owner = $this->actingAsProUser();
    $workspace = $this->createUserWorkspace($owner);
    $form = $this->createForm($owner, $workspace);
    $form->update(['password' => 'secret-pass', 'has_password' => true]);

    // Admin/owner can see password
    $this->actingAs($owner, 'api');
    $this->getJson(route('open.forms.show', $form->slug))
        ->assertSuccessful()
        ->assertJsonPath('id', $form->id)
        ->assertJsonPath('password', 'secret-pass')
        ->assertJsonPath('has_password', true);

    // Regular member (user) can see password
    $member = $this->createUser();
    $member->workspaces()->sync([$workspace->id => ['role' => 'user']], false);
    $this->actingAs($member, 'api');
    $this->getJson(route('open.forms.show', $form->slug))
        ->assertSuccessful()
        ->assertJsonPath('id', $form->id)
        ->assertJsonPath('has_password', true)
        ->assertJson(function ($json) {
            // Assert key exists and equals
            return $json->where('password', 'secret-pass')->etc();
        });

    // Readonly member should not receive the password field
    $readonly = $this->createUser();
    $readonly->workspaces()->sync([$workspace->id => ['role' => 'readonly']], false);
    $this->actingAs($readonly, 'api');
    $this->getJson(route('open.forms.show', $form->slug))
        ->assertSuccessful()
        ->assertJsonPath('id', $form->id)
        ->assertJsonPath('has_password', true)
        ->assertJsonMissingPath('password');
});
