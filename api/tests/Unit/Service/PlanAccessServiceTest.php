<?php

use App\Exceptions\FeatureAccessDeniedException;
use App\Service\Billing\Feature;
use App\Service\Billing\PlanAccessService;
use App\Service\License\LicenseCheckResult;
use Illuminate\Support\Facades\Cache;

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

it('grants editable_submissions as both workspace and form feature for pro users', function () {
    $user = $this->createProUser();
    $workspace = $this->createUserWorkspace($user);

    expect($this->service->hasFeature($workspace, Feature::EDITABLE_SUBMISSIONS))->toBeTrue();
    expect($this->service->hasFormFeature($workspace, Feature::EDITABLE_SUBMISSIONS))->toBeTrue();
});

it('does not leak paid workspace or form features into a free workspace payload', function () {
    $user = $this->createUser();
    $workspace = $this->createUserWorkspace($user);

    $features = $this->service->getFeatures($workspace);

    expect($features)->toBe([]);
    expect($this->service->hasFeature($workspace, Feature::BRANDING_REMOVAL))->toBeFalse();
    expect($this->service->hasFormFeature($workspace, 'redirect_url'))->toBeFalse();
});

it('requires self-hosted whitelabel for branding removal and no_branding', function () {
    config()->set('app.self_hosted', true);
    Cache::flush();

    $user = $this->createUser();
    $workspace = $this->createUserWorkspace($user);

    expect($this->service->hasFeature($workspace, Feature::BRANDING_REMOVAL))->toBeFalse();
    expect($this->service->hasFormFeature($workspace, 'no_branding'))->toBeFalse();
});

it('does not grant custom code with self-hosted whitelabel only', function () {
    config()->set('app.self_hosted', true);
    $this->storeSelfHostedLicense([
        'license_key' => 'lic_whitelabel12345',
    ]);

    Cache::put('self_hosted_license_check', new LicenseCheckResult(
        status: 'active',
        features: ['whitelabel' => true],
        lastChecked: now(),
    ), 86400);

    $user = $this->createUser();
    $workspace = $this->createUserWorkspace($user);

    expect($this->service->hasFeature($workspace, Feature::CUSTOM_CODE))->toBeFalse();
    expect($this->service->hasFeature($workspace, Feature::BRANDING_ADVANCED))->toBeTrue();
});

it('grants branding removal and no_branding with self-hosted whitelabel', function () {
    config()->set('app.self_hosted', true);
    $this->storeSelfHostedLicense([
        'license_key' => 'lic_whitelabel12345',
    ]);

    Cache::put('self_hosted_license_check', new LicenseCheckResult(
        status: 'active',
        features: ['whitelabel' => true],
        lastChecked: now(),
    ), 86400);

    $user = $this->createUser();
    $workspace = $this->createUserWorkspace($user);

    expect($this->service->hasFeature($workspace, Feature::BRANDING_REMOVAL))->toBeTrue();
    expect($this->service->hasFormFeature($workspace, 'no_branding'))->toBeTrue();
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
