<?php

it('can see form without counting view for form owner', function () {
    $user = $this->actingAsUser();
    $workspace = $this->createUserWorkspace($user);
    $form = $this->createForm($user, $workspace);

    $this->getJson(route('forms.view', $form->slug))
        ->assertSuccessful();

    expect($form->views()->count())->toBe(0);
});

it('can see form and count view for guest', function () {
    $user = $this->actingAsUser();
    $workspace = $this->createUserWorkspace($user);
    $form = $this->createForm($user, $workspace);

    $this->actingAsGuest();

    $this->getJson(route('forms.view', $form->slug))
        ->assertSuccessful();

    expect($form->views()->count())->toBe(1);
});
