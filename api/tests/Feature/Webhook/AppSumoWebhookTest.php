<?php


beforeEach(function () {
    $this->apiKey = 'test-appsumo-api-key';
    config(['services.appsumo.api_key' => $this->apiKey]);
});

function signPayload(string $payload, string $key): string
{
    return hash_hmac('sha256', $payload, $key);
}

describe('AppSumo webhook signature validation', function () {
    it('accepts a valid signature', function () {
        $payload = json_encode([
            'test' => true,
            'event' => 'activate',
        ]);

        $signature = signPayload($payload, $this->apiKey);

        $this->postJson(route('appsumo.webhook'), json_decode($payload, true), [
            'x-appsumo-signature' => $signature,
        ])->assertSuccessful();
    });

    it('rejects a missing signature', function () {
        $this->postJson(route('appsumo.webhook'), [
            'test' => true,
            'event' => 'activate',
        ])->assertStatus(401);
    });

    it('rejects an empty signature', function () {
        $this->postJson(route('appsumo.webhook'), [
            'test' => true,
            'event' => 'activate',
        ], [
            'x-appsumo-signature' => '',
        ])->assertStatus(401);
    });

    it('rejects a wrong signature', function () {
        $this->postJson(route('appsumo.webhook'), [
            'test' => true,
            'event' => 'activate',
        ], [
            'x-appsumo-signature' => 'definitely-not-a-valid-hmac',
        ])->assertStatus(401);
    });

    it('rejects a signature computed with the wrong key', function () {
        $payload = json_encode([
            'test' => true,
            'event' => 'activate',
        ]);

        $badSignature = signPayload($payload, 'wrong-secret-key');

        $this->postJson(route('appsumo.webhook'), json_decode($payload, true), [
            'x-appsumo-signature' => $badSignature,
        ])->assertStatus(401);
    });
});
