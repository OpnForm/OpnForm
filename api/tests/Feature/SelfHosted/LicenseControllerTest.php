<?php

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
});
