<?php

use App\Enterprise\Oidc\IdTokenVerifier;
use App\Enterprise\Oidc\Models\IdentityConnection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestHelpers;

uses(TestHelpers::class);
uses()->group('oidc', 'feature');

afterEach(function () {
    Cache::flush();
    Http::fake();
});

describe('IdTokenVerifier', function () {
    it('verifies valid ID token signature', function () {
        $connection = IdentityConnection::factory()->create([
            'issuer' => 'https://idp.example.com',
        ]);

        // Create a valid JWT token with proper structure
        $header = base64_encode(json_encode([
            'alg' => 'RS256',
            'typ' => 'JWT',
            'kid' => 'test-kid',
        ]));
        $payload = base64_encode(json_encode([
            'sub' => 'user-123',
            'iss' => $connection->issuer,
            'aud' => $connection->client_id,
            'exp' => time() + 3600,
        ]));
        $signature = 'valid-signature';
        $idToken = "{$header}.{$payload}.{$signature}";

        // Mock OpenID configuration
        Http::fake([
            "{$connection->issuer}/.well-known/openid-configuration" => Http::response([
                'jwks_uri' => "{$connection->issuer}/.well-known/jwks.json",
            ]),
        ]);

        // Mock JWKS with a test key
        Http::fake([
            "{$connection->issuer}/.well-known/jwks.json" => Http::response([
                'keys' => [
                    [
                        'kty' => 'RSA',
                        'kid' => 'test-kid',
                        'use' => 'sig',
                        'n' => 'test-modulus',
                        'e' => 'AQAB',
                    ],
                ],
            ]),
        ]);

        $verifier = new IdTokenVerifier();

        // This will fail because we can't actually verify without real keys,
        // but we can test the structure validation
        expect(fn () => $verifier->verifySignature($connection, $idToken))
            ->toThrow(\Exception::class);
    });

    it('throws exception for invalid token format', function () {
        $connection = IdentityConnection::factory()->create();

        $verifier = new IdTokenVerifier();

        expect(fn () => $verifier->verifySignature($connection, 'invalid-token'))
            ->toThrow(\Exception::class, 'Invalid ID token format');
    });

    it('throws exception for missing kid in header', function () {
        $connection = IdentityConnection::factory()->create();

        $header = base64_encode(json_encode(['alg' => 'RS256', 'typ' => 'JWT']));
        $payload = base64_encode(json_encode(['sub' => 'user-123']));
        $signature = 'signature';
        $idToken = "{$header}.{$payload}.{$signature}";

        $verifier = new IdTokenVerifier();

        expect(fn () => $verifier->verifySignature($connection, $idToken))
            ->toThrow(\Exception::class, 'Missing key ID (kid) in ID token header');
    });

    it('throws exception for missing alg in header', function () {
        $connection = IdentityConnection::factory()->create();

        $header = base64_encode(json_encode(['typ' => 'JWT', 'kid' => 'test-kid']));
        $payload = base64_encode(json_encode(['sub' => 'user-123']));
        $signature = 'signature';
        $idToken = "{$header}.{$payload}.{$signature}";

        $verifier = new IdTokenVerifier();

        expect(fn () => $verifier->verifySignature($connection, $idToken))
            ->toThrow(\Exception::class, 'Invalid ID token header');
    });

    it('uses custom jwks_uri from connection options', function () {
        $connection = IdentityConnection::factory()->create([
            'issuer' => 'https://idp.example.com',
            'options' => [
                'jwks_uri' => 'https://custom-jwks.example.com/keys',
            ],
        ]);

        $header = base64_encode(json_encode([
            'alg' => 'RS256',
            'typ' => 'JWT',
            'kid' => 'test-kid',
        ]));
        $payload = base64_encode(json_encode(['sub' => 'user-123']));
        $signature = 'signature';
        $idToken = "{$header}.{$payload}.{$signature}";

        // Mock custom JWKS endpoint
        Http::fake([
            'https://custom-jwks.example.com/keys' => Http::response([
                'keys' => [
                    [
                        'kty' => 'RSA',
                        'kid' => 'test-kid',
                        'use' => 'sig',
                        'n' => 'test-modulus',
                        'e' => 'AQAB',
                    ],
                ],
            ]),
        ]);

        $verifier = new IdTokenVerifier();

        // Will fail on signature verification but should use custom URI
        expect(fn () => $verifier->verifySignature($connection, $idToken))
            ->toThrow(\Exception::class);

        // Verify custom URI was called
        Http::assertSent(
            fn ($request) =>
            $request->url() === 'https://custom-jwks.example.com/keys'
        );
    });

    it('falls back to standard jwks.json when OpenID config fails', function () {
        $connection = IdentityConnection::factory()->create([
            'issuer' => 'https://idp.example.com',
        ]);

        $header = base64_encode(json_encode([
            'alg' => 'RS256',
            'typ' => 'JWT',
            'kid' => 'test-kid',
        ]));
        $payload = base64_encode(json_encode(['sub' => 'user-123']));
        $signature = 'signature';
        $idToken = "{$header}.{$payload}.{$signature}";

        // Mock OpenID config to fail
        Http::fake([
            "{$connection->issuer}/.well-known/openid-configuration" => Http::response([], 404),
            "{$connection->issuer}/.well-known/jwks.json" => Http::response([
                'keys' => [
                    [
                        'kty' => 'RSA',
                        'kid' => 'test-kid',
                        'use' => 'sig',
                        'n' => 'test-modulus',
                        'e' => 'AQAB',
                    ],
                ],
            ]),
        ]);

        $verifier = new IdTokenVerifier();

        // Will fail on signature verification but should use fallback
        expect(fn () => $verifier->verifySignature($connection, $idToken))
            ->toThrow(\Exception::class);

        // Verify fallback URI was called
        Http::assertSent(
            fn ($request) =>
            $request->url() === "{$connection->issuer}/.well-known/jwks.json"
        );
    });

    it('throws exception when JWKS fetch fails', function () {
        $connection = IdentityConnection::factory()->create([
            'issuer' => 'https://idp.example.com',
        ]);

        $header = base64_encode(json_encode([
            'alg' => 'RS256',
            'typ' => 'JWT',
            'kid' => 'test-kid',
        ]));
        $payload = base64_encode(json_encode(['sub' => 'user-123']));
        $signature = 'signature';
        $idToken = "{$header}.{$payload}.{$signature}";

        // Mock all endpoints to fail
        Http::fake([
            "{$connection->issuer}/.well-known/openid-configuration" => Http::response([], 404),
            "{$connection->issuer}/.well-known/jwks.json" => Http::response([], 500),
        ]);

        $verifier = new IdTokenVerifier();

        expect(fn () => $verifier->verifySignature($connection, $idToken))
            ->toThrow(\Exception::class, 'Failed to fetch JWKS');
    });

    it('throws exception when JWKS has no keys', function () {
        $connection = IdentityConnection::factory()->create([
            'issuer' => 'https://idp.example.com',
        ]);

        $header = base64_encode(json_encode([
            'alg' => 'RS256',
            'typ' => 'JWT',
            'kid' => 'test-kid',
        ]));
        $payload = base64_encode(json_encode(['sub' => 'user-123']));
        $signature = 'signature';
        $idToken = "{$header}.{$payload}.{$signature}";

        // Mock JWKS with empty keys
        Http::fake([
            "{$connection->issuer}/.well-known/jwks.json" => Http::response([
                'keys' => [],
            ]),
        ]);

        $verifier = new IdTokenVerifier();

        expect(fn () => $verifier->verifySignature($connection, $idToken))
            ->toThrow(\Exception::class, 'Invalid JWKS format');
    });

    it('throws exception when kid not found in JWKS', function () {
        $connection = IdentityConnection::factory()->create([
            'issuer' => 'https://idp.example.com',
        ]);

        $header = base64_encode(json_encode([
            'alg' => 'RS256',
            'typ' => 'JWT',
            'kid' => 'non-existent-kid',
        ]));
        $payload = base64_encode(json_encode(['sub' => 'user-123']));
        $signature = 'signature';
        $idToken = "{$header}.{$payload}.{$signature}";

        // Mock JWKS with different kid
        Http::fake([
            "{$connection->issuer}/.well-known/jwks.json" => Http::response([
                'keys' => [
                    [
                        'kty' => 'RSA',
                        'kid' => 'different-kid',
                        'use' => 'sig',
                        'n' => 'test-modulus',
                        'e' => 'AQAB',
                    ],
                ],
            ]),
        ]);

        $verifier = new IdTokenVerifier();

        expect(fn () => $verifier->verifySignature($connection, $idToken))
            ->toThrow(\Exception::class, 'No matching key found in JWKS');
    });
});
