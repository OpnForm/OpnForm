<?php


describe('Workspace admin-only endpoints', function () {
    it('forbids non-admin users from updating custom domains', function () {
        $owner = $this->actingAsProUser();
        $workspace = $this->createUserWorkspace($owner);

        // Create a non-admin member
        $viewer = $this->createUser();
        $viewer->workspaces()->sync([$workspace->id => ['role' => 'user']], false);
        $this->actingAs($viewer, 'api');

        $this->putJson(route('open.workspaces.save-custom-domains', $workspace), [
            'custom_domains' => ['example.com']
        ])->assertStatus(403);
    });

    it('allows admins to update custom domains', function () {
        $owner = $this->actingAsProUser();
        $workspace = $this->createUserWorkspace($owner);

        $this->putJson(route('open.workspaces.save-custom-domains', $workspace), [
            'custom_domains' => ['example.com']
        ])->assertSuccessful();
    });

    it('forbids non-admin users from updating email settings', function () {
        $owner = $this->actingAsProUser();
        $workspace = $this->createUserWorkspace($owner);

        $viewer = $this->createUser();
        $viewer->workspaces()->sync([$workspace->id => ['role' => 'user']], false);
        $this->actingAs($viewer, 'api');

        $this->putJson(route('open.workspaces.save-email-settings', $workspace), [
            'host' => 'smtp.example.com',
            'port' => '587',
            'username' => 'user',
            'password' => 'secret',
        ])->assertStatus(403);
    });

    it('allows admins to update email settings', function () {
        $owner = $this->actingAsProUser();
        $workspace = $this->createUserWorkspace($owner);

        $this->putJson(route('open.workspaces.save-email-settings', $workspace), [
            'host' => 'smtp.example.com',
            'port' => '587',
            'username' => 'user',
            'password' => 'secret',
        ])->assertSuccessful();
    });
});
