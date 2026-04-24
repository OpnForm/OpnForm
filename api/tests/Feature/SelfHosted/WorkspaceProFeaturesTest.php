<?php

use App\Models\License;
use App\Service\License\LicenseCheckResult;
use Illuminate\Support\Facades\Cache;

beforeEach(function () {
    config(['app.self_hosted' => true]);
    config(['cashier.key' => null]);
    $this->user = $this->actingAsUser();
    $this->workspace = $this->createUserWorkspace($this->user);
});

function activateLicenseWithFeatures(array $features): void
{
    License::query()->where('license_provider', 'self_hosted_enterprise')->delete();

    License::create([
        'license_key' => 'lic_test_enterprise_12345',
        'license_provider' => 'self_hosted_enterprise',
        'status' => 'active',
        'meta' => [],
        'features' => $features,
        'last_checked_at' => now(),
        'expires_at' => now()->addYear(),
    ]);

    Cache::put('self_hosted_license_check', new LicenseCheckResult(
        status: 'active',
        features: $features,
        lastChecked: now(),
        expiresAt: now()->addYear(),
    ), 86400);
}

/*
|--------------------------------------------------------------------------
| Custom SMTP (Email Settings)
|--------------------------------------------------------------------------
*/
describe('custom SMTP / email settings', function () {
    $validEmailPayload = [
        'host' => 'smtp.example.com',
        'port' => 587,
        'encryption' => 'tls',
        'username' => 'user@example.com',
        'password' => 'secret123',
        'sender_address' => 'noreply@example.com',
    ];

    it('blocks email settings without license', function () use (&$validEmailPayload) {
        $response = $this->putJson(
            route('open.workspaces.save-email-settings', ['workspace' => $this->workspace]),
            $validEmailPayload,
        );

        $response->assertStatus(403);
    });

    it('blocks email settings with license missing custom_smtp feature', function () use (&$validEmailPayload) {
        activateLicenseWithFeatures(['sso' => true, 'multiOrg' => true]);

        $response = $this->putJson(
            route('open.workspaces.save-email-settings', ['workspace' => $this->workspace]),
            $validEmailPayload,
        );

        $response->assertStatus(403);
    });

    it('allows email settings with license having custom_smtp feature', function () use (&$validEmailPayload) {
        activateLicenseWithFeatures(['sso' => true, 'custom_smtp' => true]);

        $response = $this->putJson(
            route('open.workspaces.save-email-settings', ['workspace' => $this->workspace]),
            $validEmailPayload,
        );

        $response->assertSuccessful();
    });

    it('allows clearing email settings with license having custom_smtp', function () {
        activateLicenseWithFeatures(['custom_smtp' => true]);

        $response = $this->putJson(
            route('open.workspaces.save-email-settings', ['workspace' => $this->workspace]),
            [],
        );

        $response->assertSuccessful();
    });
});

/*
|--------------------------------------------------------------------------
| Custom Code & CSS
|--------------------------------------------------------------------------
*/
describe('custom code and CSS settings', function () {
    it('blocks custom code without license', function () {
        $response = $this->putJson(
            route('open.workspaces.save-custom-code-settings', ['workspace' => $this->workspace]),
            ['custom_code' => '<script>console.log("hello")</script>'],
        );

        $response->assertStatus(403);
    });

    it('blocks custom CSS without license', function () {
        $response = $this->putJson(
            route('open.workspaces.save-custom-code-settings', ['workspace' => $this->workspace]),
            ['custom_css' => 'body { color: red; }'],
        );

        $response->assertStatus(403);
    });

    it('blocks custom code with license missing custom_code feature', function () {
        activateLicenseWithFeatures(['sso' => true, 'custom_smtp' => true]);

        $response = $this->putJson(
            route('open.workspaces.save-custom-code-settings', ['workspace' => $this->workspace]),
            ['custom_code' => '<script>console.log("hello")</script>'],
        );

        $response->assertStatus(403);
    });

    it('allows custom code with license having custom_code feature', function () {
        activateLicenseWithFeatures(['custom_code' => true]);

        $response = $this->putJson(
            route('open.workspaces.save-custom-code-settings', ['workspace' => $this->workspace]),
            ['custom_code' => '<script>console.log("hello")</script>'],
        );

        $response->assertSuccessful();
    });

    it('allows custom CSS with license having custom_code feature', function () {
        activateLicenseWithFeatures(['custom_code' => true]);

        $response = $this->putJson(
            route('open.workspaces.save-custom-code-settings', ['workspace' => $this->workspace]),
            ['custom_css' => 'body { color: red; }'],
        );

        $response->assertSuccessful();
    });

    it('allows both custom code and CSS together', function () {
        activateLicenseWithFeatures(['custom_code' => true]);

        $response = $this->putJson(
            route('open.workspaces.save-custom-code-settings', ['workspace' => $this->workspace]),
            [
                'custom_code' => '<script>console.log("hello")</script>',
                'custom_css' => '.form-container { background: #fff; }',
            ],
        );

        $response->assertSuccessful();
    });

    it('allows clearing custom code with license', function () {
        activateLicenseWithFeatures(['custom_code' => true]);

        $response = $this->putJson(
            route('open.workspaces.save-custom-code-settings', ['workspace' => $this->workspace]),
            ['custom_code' => null, 'custom_css' => null],
        );

        $response->assertSuccessful();
    });
});

/*
|--------------------------------------------------------------------------
| Custom Domain (always allowed on self-hosted — is_pro is true)
|--------------------------------------------------------------------------
*/
describe('custom domain on self-hosted', function () {
    it('allows saving custom domain without license (is_pro always true)', function () {
        $response = $this->putJson(
            route('open.workspaces.save-custom-domains', ['workspace' => $this->workspace]),
            ['custom_domains' => ['forms.example.com']],
        );

        $response->assertSuccessful();
    });
});
