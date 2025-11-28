<?php

use App\Enterprise\Oidc\Models\IdentityConnection;
use Laravel\Socialite\Contracts\User as SocialiteUser;

if (!function_exists('createMockSocialiteUser')) {
    function createMockSocialiteUser(?string $email = null, ?string $name = null): SocialiteUser
    {
        $mock = Mockery::mock(SocialiteUser::class);
        $mock->shouldReceive('getEmail')->andReturn($email);
        $mock->shouldReceive('getName')->andReturn($name);
        return $mock;
    }
}

if (!function_exists('createValidIdTokenClaims')) {
    function createValidIdTokenClaims(IdentityConnection $connection, string $subject): array
    {
        return [
            'sub' => $subject,
            'iss' => $connection->issuer,
            'aud' => $connection->client_id,
            'exp' => time() + 3600, // Valid for 1 hour
            'email' => 'test@example.com',
            'name' => 'Test User',
        ];
    }
}

if (!function_exists('createMockIdToken')) {
    function createMockIdToken(array $claims, ?string $kid = 'test-kid'): string
    {
        // Create a simple mock JWT token (header.payload.signature)
        // Include kid in header for signature verification
        $header = base64_encode(json_encode([
            'alg' => 'RS256',
            'typ' => 'JWT',
            'kid' => $kid,
        ]));
        $payload = base64_encode(json_encode($claims));
        $signature = 'mock-signature';
        return "{$header}.{$payload}.{$signature}";
    }
}
