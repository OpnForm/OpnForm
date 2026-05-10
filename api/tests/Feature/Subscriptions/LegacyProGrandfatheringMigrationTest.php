<?php

use App\Service\Billing\Feature;
use App\Service\Billing\PlanAccessService;
use App\Service\Forms\FormCleaner;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

function legacyProGrandfatheringMigration()
{
    return include database_path('migrations/2026_02_14_000000_grandfather_legacy_pro_workspaces.php');
}

it('grandfathers active legacy default subscriptions with moved pro features', function () {
    $user = $this->createProUser();
    $workspace = $this->createUserWorkspace($user);

    legacyProGrandfatheringMigration()->up();

    $workspace = $workspace->fresh();
    $service = app(PlanAccessService::class);

    expect($workspace->plan_tier)->toBe('pro');
    expect($service->hasFeature($workspace, Feature::BRANDING_ADVANCED))->toBeTrue();
    expect($service->hasFeature($workspace, Feature::PARTIAL_SUBMISSIONS))->toBeTrue();
    expect($service->hasFeature($workspace, Feature::SSO_OIDC))->toBeTrue();
    expect($service->hasFeature($workspace, Feature::FORM_VERSIONING))->toBeFalse();
    expect($service->hasFormFeature($workspace, 'custom_css'))->toBeTrue();
    expect($service->hasFormFeature($workspace, 'seo_meta'))->toBeTrue();
    expect($service->hasFormFeature($workspace, 'enable_ip_tracking'))->toBeTrue();
});

it('does not grandfather new named pro subscriptions', function () {
    $user = $this->createUser();
    $user->subscriptions()->create([
        'type' => 'pro',
        'stripe_id' => (string) Str::uuid(),
        'stripe_status' => 'active',
        'stripe_price' => (string) Str::uuid(),
        'quantity' => 1,
    ]);
    $workspace = $this->createUserWorkspace($user);

    legacyProGrandfatheringMigration()->up();

    $workspace = $workspace->fresh();
    $service = app(PlanAccessService::class);

    expect($workspace->plan_overrides)->toBeNull();
    expect($workspace->plan_tier)->toBe('pro');
    expect($service->hasFeature($workspace, Feature::BRANDING_ADVANCED))->toBeFalse();
    expect($service->hasFormFeature($workspace, 'custom_css'))->toBeFalse();
});

it('grandfathers trialing legacy default subscriptions', function () {
    $user = $this->createUser();
    $user->subscriptions()->create([
        'type' => 'default',
        'stripe_id' => (string) Str::uuid(),
        'stripe_status' => 'trialing',
        'stripe_price' => (string) Str::uuid(),
        'trial_ends_at' => now()->addDays(5),
        'quantity' => 1,
    ]);
    $workspace = $this->createUserWorkspace($user);

    legacyProGrandfatheringMigration()->up();

    $service = app(PlanAccessService::class);

    expect($service->hasFeature($workspace->fresh(), Feature::BRANDING_ADVANCED))->toBeTrue();
});

it('does not grandfather ended legacy default subscriptions', function () {
    $user = $this->createUser();
    $user->subscriptions()->create([
        'type' => 'default',
        'stripe_id' => (string) Str::uuid(),
        'stripe_status' => 'canceled',
        'stripe_price' => (string) Str::uuid(),
        'ends_at' => now()->subDay(),
        'quantity' => 1,
    ]);
    $workspace = $this->createUserWorkspace($user);

    legacyProGrandfatheringMigration()->up();

    expect($workspace->fresh()->plan_overrides)->toBeNull();
});

it('keeps moved pro form features available after legacy grandfathering', function () {
    $user = $this->createProUser();
    $this->actingAsUser($user);
    $workspace = $this->createUserWorkspace($user);

    legacyProGrandfatheringMigration()->up();
    $workspace = $workspace->fresh();

    $form = $this->createForm($user, $workspace, [
        'custom_css' => 'body { color: red; }',
        'seo_meta' => ['page_title' => 'Legacy Pro'],
        'enable_partial_submissions' => true,
        'enable_ip_tracking' => true,
        'database_fields_update' => ['field' => 'email'],
    ]);

    $cleaner = (new FormCleaner())
        ->processForm(Request::create('/', 'GET'), $form)
        ->performCleaning($workspace);

    $data = $cleaner->getData();

    expect($data['custom_css'])->toBe('body { color: red; }');
    expect((array) $data['seo_meta'])->toBe(['page_title' => 'Legacy Pro']);
    expect($data['enable_partial_submissions'])->toBeTrue();
    expect($data['enable_ip_tracking'])->toBeTrue();
    expect($data['database_fields_update'])->toBe(['field' => 'email']);
    expect($cleaner->hasCleaned())->toBeFalse();
});

it('grandfathers active lifetime licenses too', function () {
    $user = $this->createAppSumoLicensedUser(2);
    $workspace = $this->createUserWorkspace($user);

    legacyProGrandfatheringMigration()->up();

    $workspace = $workspace->fresh();
    $service = app(PlanAccessService::class);

    expect($workspace->plan_tier)->toBe('pro');
    expect($service->hasFeature($workspace, Feature::BRANDING_ADVANCED))->toBeTrue();
    expect($service->hasFormFeature($workspace, 'custom_css'))->toBeTrue();
});

it('rolls back only features added by the grandfathering migration', function () {
    $user = $this->createProUser();
    $workspace = $this->createUserWorkspace($user);
    $workspace->update([
        'plan_overrides' => [
            'features' => [
                Feature::SSO_OIDC,
                'custom_domain.wildcard',
            ],
            'limits' => [
                'custom_domain_count' => 25,
            ],
        ],
    ]);

    $migration = legacyProGrandfatheringMigration();
    $migration->up();
    $migration->down();

    $overrides = $workspace->fresh()->plan_overrides;

    expect($overrides['features'])->toContain(Feature::SSO_OIDC);
    expect($overrides['features'])->toContain('custom_domain.wildcard');
    expect($overrides['features'])->not->toContain(Feature::BRANDING_ADVANCED);
    expect($overrides['features'])->not->toContain('custom_css');
    expect($overrides['limits'])->toBe(['custom_domain_count' => 25]);
    expect($overrides)->not->toHaveKey('legacy_pro_grandfathering');
});
