<?php

it('does not expose a public form users endpoint', function (bool $selfHosted) {
    config(['app.self_hosted' => $selfHosted]);

    $user = $this->createUser();
    $workspace = $this->createUserWorkspace($user);
    $form = $this->createForm($user, $workspace, ['visibility' => 'public']);

    $this->getJson('/forms/'.$form->slug.'/users')->assertNotFound();
    $this->getJson('/forms/'.$form->id.'/users?has_user_field=1')->assertNotFound();

    $this->actingAsUser($user);
    $this->getJson('/forms/'.$form->slug.'/users')->assertNotFound();
})->with([
    'cloud' => [false],
    'self-hosted' => [true],
]);
