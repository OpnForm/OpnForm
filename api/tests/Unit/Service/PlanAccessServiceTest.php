<?php

use App\Exceptions\FeatureAccessDeniedException;
use App\Service\Billing\Feature;
use App\Service\Billing\PlanAccessService;

uses(\Tests\TestCase::class);

beforeEach(function () {
    $this->service = app(PlanAccessService::class);
});

it('grants pro features to a pro workspace', function () {
    $user = $this->createProUser();
    $workspace = $this->createUserWorkspace($user);

    expect($this->service->hasFeature($workspace, Feature::FORM_ANALYTICS))->toBeTrue();
    expect($this->service->hasFeature($workspace, Feature::FORM_VERSIONING))->toBeFalse();
});

it('grants overridden features even on a free workspace', function () {
    $user = $this->createUser();
    $workspace = $this->createUserWorkspace($user);
    $workspace->update([
        'plan_overrides' => ['features' => [Feature::FORM_VERSIONING]],
    ]);
    $workspace->flush();

    expect($this->service->hasFeature($workspace->fresh(), Feature::FORM_VERSIONING))->toBeTrue();
});

it('distinguishes workspace features from form features when names overlap', function () {
    $user = $this->createProUser();
    $workspace = $this->createUserWorkspace($user);

    expect($this->service->hasFeature($workspace, Feature::EDITABLE_SUBMISSIONS))->toBeTrue();
    expect($this->service->hasFormFeature($workspace, Feature::EDITABLE_SUBMISSIONS))->toBeFalse();
});

it('does not leak paid workspace or form features into a free workspace payload', function () {
    $user = $this->createUser();
    $workspace = $this->createUserWorkspace($user);

    $features = $this->service->getFeatures($workspace);

    expect($features)->toBe([]);
    expect($this->service->hasFeature($workspace, Feature::BRANDING_REMOVAL))->toBeFalse();
    expect($this->service->hasFormFeature($workspace, 'redirect_url'))->toBeFalse();
});

it('throws a feature exception when access is denied', function () {
    $user = $this->createUser();
    $workspace = $this->createUserWorkspace($user);

    $this->service->requireFeature($workspace, Feature::FORM_VERSIONING);
})->throws(FeatureAccessDeniedException::class);

it('fails closed for unknown workspace feature keys', function () {
    $user = $this->createBusinessUser();
    $workspace = $this->createUserWorkspace($user);

    expect($this->service->hasFeature($workspace, 'unknown.feature'))->toBeFalse();
    expect($this->service->userHasFeature($user, 'unknown.feature'))->toBeFalse();
});
