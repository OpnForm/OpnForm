<?php

uses(\Tests\TestCase::class);

it('can create pro user who are subscribed', function () {
    $user = $this->actingAsProUser();
    expect($user->is_subscribed)->toBeTrue();
});

it('can create business user who are subscribed', function () {
    $user = $this->actingAsBusinessUser();
    expect($user->is_subscribed)->toBeTrue();
});

it('can create enterprise user who are subscribed', function () {
    $user = $this->actingAsEnterpriseUser();
    expect($user->is_subscribed)->toBeTrue();
});

it('can create test workspace', function () {
    $user = $this->actingAsProUser();
    $this->createUserWorkspace($user);
    expect($user->workspaces()->count())->toBe(1);
});

it('can make a form for a database', function () {
    $user = $this->actingAsProUser();
    $workspace = $this->createUserWorkspace($user);
    $form = $this->makeForm($user, $workspace);
    expect($form->title)->not()->toBeNull();
    expect(count($form->properties))->not()->toBe(0);
});

it('pro user workspace has pro plan tier', function () {
    $user = $this->createProUser();
    $workspace = $this->createUserWorkspace($user);
    expect($workspace->plan_tier)->toBe('pro');
    expect($workspace->is_pro)->toBeTrue();
});

it('business user workspace has business plan tier', function () {
    $user = $this->createBusinessUser();
    $workspace = $this->createUserWorkspace($user);
    expect($workspace->plan_tier)->toBe('business');
    expect($workspace->is_pro)->toBeTrue();
});

it('enterprise user workspace has enterprise plan tier', function () {
    $user = $this->createEnterpriseUser();
    $workspace = $this->createUserWorkspace($user);
    expect($workspace->plan_tier)->toBe('enterprise');
    expect($workspace->is_pro)->toBeTrue();
    expect($workspace->is_enterprise)->toBeTrue();
});

it('free user workspace has free plan tier', function () {
    $user = $this->createUser();
    $workspace = $this->createUserWorkspace($user);
    expect($workspace->plan_tier)->toBe('free');
    expect($workspace->is_pro)->toBeFalse();
});
