<?php

use App\Enums\SettingsKey;
use App\Models\Setting;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config(['app.self_hosted' => true]);
    config(['services.license.endpoint' => 'https://api.opnform.com']);
    $this->user = $this->actingAsUser();
    $this->workspace = $this->createUserWorkspace($this->user);
});

describe('POST /settings/license/activate', function () {
    it('activates a valid license key', function () {
        Http::fake([
            'api.opnform.com/licenses/validate' => Http::response([
                'valid' => true,
                'status' => 'active',
                'features' => ['sso' => true, 'multiOrg' => true],
                'expiresAt' => '2027-03-03T23:59:59Z',
                'licenseId' => '1',
                'activationId' => '2',
            ]),
        ]);

        $response = $this->postJson('/settings/license/activate', [
            'license_key' => 'lic_validkey1234567890abcdef12345678',
        ]);

        $response->assertSuccessful()
            ->assertJson([
                'status' => 'active',
                'message' => 'License activated successfully.',
            ]);

        expect($response->json('features.sso'))->toBeTrue();
        expect($response->json('expires_at'))->not->toBeNull();
        expect(Setting::get(SettingsKey::SELF_HOSTED_LICENSE))->not->toBeNull();
    });

    it('rejects an invalid license key with 422', function () {
        Http::fake([
            'api.opnform.com/licenses/validate' => Http::response([
                'valid' => false,
                'status' => 'expired',
                'features' => null,
            ]),
        ]);

        $response = $this->postJson('/settings/license/activate', [
            'license_key' => 'lic_invalidkey1234567890abcdef123456',
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'status' => 'expired',
                'message' => 'License key is invalid or expired. Please check your key and try again.',
            ]);
    });

    it('explains when a license key is already activated on another instance', function () {
        Http::fake([
            'api.opnform.com/licenses/validate' => Http::response([
                'valid' => false,
                'status' => 'activation_limit_reached',
                'features' => null,
            ]),
        ]);

        $response = $this->postJson('/settings/license/activate', [
            'license_key' => 'lic_reusedkey1234567890abcdef123456',
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'status' => 'activation_limit_reached',
                'message' => 'This license key is already activated on another self-hosted instance. Contact support to reset it.',
            ]);
    });

    it('validates license_key is required', function () {
        $response = $this->postJson('/settings/license/activate', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['license_key']);
    });

    it('validates license_key minimum length', function () {
        $response = $this->postJson('/settings/license/activate', [
            'license_key' => 'short',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['license_key']);
    });

    it('requires authentication', function () {
        $this->actingAsGuest();

        $response = $this->postJson('/settings/license/activate', [
            'license_key' => 'lic_testkey1234567890abcdef12345678',
        ]);

        $response->assertStatus(401);
    });

    it('requires a workspace admin', function () {
        $member = $this->createUser();
        $this->workspace->users()->attach($member, ['role' => 'user']);
        $this->actingAs($member, 'api');

        $response = $this->postJson('/settings/license/activate', [
            'license_key' => 'lic_testkey1234567890abcdef12345678',
        ]);

        $response->assertStatus(403);
    });

    it('requires a workspace admin for every license management endpoint', function (string $method, string $uri, array $payload = []) {
        $member = $this->createUser();
        $this->workspace->users()->attach($member, ['role' => 'user']);
        $this->actingAs($member, 'api');

        $response = $this->json($method, $uri, $payload);

        $response->assertStatus(403);
    })->with([
        'status' => ['GET', '/settings/license/status'],
        'activate' => ['POST', '/settings/license/activate', [
            'license_key' => 'lic_testkey1234567890abcdef12345678',
        ]],
        'deactivate' => ['POST', '/settings/license/deactivate'],
        'portal' => ['POST', '/settings/license/portal'],
    ]);

    it('hides self-hosted license settings endpoints from cloud instances', function (string $method, string $uri, array $payload = []) {
        config(['app.self_hosted' => false]);

        $response = $this->json($method, $uri, $payload);

        $response->assertStatus(404)
            ->assertJson([
                'error' => 'Only available on self-hosted instances.',
            ]);
    })->with([
        'status' => ['GET', '/settings/license/status'],
        'activate' => ['POST', '/settings/license/activate', [
            'license_key' => 'lic_testkey1234567890abcdef12345678',
        ]],
        'deactivate' => ['POST', '/settings/license/deactivate'],
        'portal' => ['POST', '/settings/license/portal'],
    ]);

    it('opens a cloud billing portal using the installed key', function () {
        $this->storeSelfHostedLicense([
            'license_key' => 'lic_portalkey1234567890',
        ]);

        Http::fake([
            'https://api.opnform.com/licenses/portal' => Http::response([
                'portalUrl' => 'https://billing.stripe.com/p/session',
            ]),
        ]);

        $response = $this->postJson('/settings/license/portal');

        $response->assertSuccessful()
            ->assertJson([
                'portalUrl' => 'https://billing.stripe.com/p/session',
            ]);

        Http::assertSent(
            fn ($request) =>
            str_contains($request->url(), '/licenses/portal')
            && $request['licenseKey'] === 'lic_portalkey1234567890'
        );
    });

    it('deactivates the local license', function () {
        $this->storeSelfHostedLicense();

        $response = $this->postJson('/settings/license/deactivate');

        $response->assertSuccessful()
            ->assertJson([
                'message' => 'License removed from this instance.',
            ]);

        expect(Setting::get(SettingsKey::SELF_HOSTED_LICENSE))->toBeNull();
    });
});
