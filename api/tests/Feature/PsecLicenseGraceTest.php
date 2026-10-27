<?php

use App\Service\License\LicenseService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

it('never grants grace to invalid licenses and stops grace at the original verification deadline', function () {
    config(['app.self_hosted' => true, 'services.license.endpoint' => 'https://api.opnform.com']);
    Cache::flush();
    Http::fake(['*' => Http::response([], 500)]);
    $this->storeSelfHostedLicense(['license_key' => 'lic_test', 'status' => 'invalid', 'last_checked_at' => now()->toIso8601String()]);
    expect(app(LicenseService::class)->hasPaidLicense())->toBeFalse();
    Cache::flush();
    $this->storeSelfHostedLicense(['license_key' => 'lic_test', 'status' => 'active', 'last_checked_at' => now()->subHours(23)->subMinutes(30)->toIso8601String()]);
    expect(app(LicenseService::class)->hasPaidLicense())->toBeTrue();
    $this->travel(31)->minutes();
    expect(app(LicenseService::class)->hasPaidLicense())->toBeFalse();
    $this->travelBack();
});

it('does not reactivate a deleted subscription on a delayed active update', function () {
    $license = \App\Models\LicenseKey::create(['license_key' => 'lic_delayed', 'billing_email' => 'license@example.test', 'plan' => 'self_hosted', 'stripe_subscription_id' => 'sub_delayed', 'status' => 'active']);
    $service = app(\App\Service\License\LicenseKeyService::class);
    $service->handleSubscriptionDeleted('sub_delayed');
    $service->handleSubscriptionUpdated('sub_delayed', 'active', now()->addMonth());
    expect($license->fresh()->status)->toBe(\App\Models\LicenseKey::STATUS_CANCELLED);
});
