<?php

namespace App\Enterprise\Oidc;

use App\Enterprise\Oidc\Models\IdentityConnection;
use Firebase\JWT\JWK;
use Firebase\JWT\JWT;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * ID Token Verifier for OIDC.
 *
 * Verifies ID token signatures using JWKS (JSON Web Key Set) from the provider.
 * Uses firebase/php-jwt library for JWT signature verification.
 *
 * How signature verification works:
 * 1. Extract the JWT header to get the key ID (kid) and algorithm (alg)
 * 2. Fetch the provider's public keys from JWKS endpoint
 * 3. Find the matching key using the kid from the token header
 * 4. Verify the token signature using the public key
 * 5. If signature is valid, the token was signed by the provider's private key
 *
 * JWKS URI resolution (in order):
 * - Custom jwks_uri from connection.options (for non-standard providers)
 * - jwks_uri from OpenID configuration discovery (/.well-known/openid-configuration)
 * - Standard fallback: {issuer}/.well-known/jwks.json
 */
class IdTokenVerifier
{
    /**
     * Verify ID token signature using JWKS from the provider.
     *
     * @throws \Exception
     */
    public function verifySignature(IdentityConnection $connection, string $idToken): void
    {
        try {
            // Decode header to get key ID (kid) and algorithm
            $parts = explode('.', $idToken);
            if (count($parts) !== 3) {
                throw new \Exception('Invalid ID token format');
            }

            [$headerB64] = $parts;
            $header = json_decode($this->base64UrlDecode($headerB64), true);

            if (!$header || !isset($header['alg'])) {
                throw new \Exception('Invalid ID token header');
            }

            if (!isset($header['kid'])) {
                throw new \Exception('Missing key ID (kid) in ID token header');
            }

            // Get JWKS from provider
            $jwks = $this->getJwks($connection);

            // Parse JWKS and find the matching key
            $keys = JWK::parseKeySet($jwks, $header['alg']);
            $kid = $header['kid'];

            if (!isset($keys[$kid])) {
                throw new \Exception("No matching key found in JWKS for kid: {$kid}");
            }

            $key = $keys[$kid];

            // Verify signature using firebase/php-jwt
            // This will throw an exception if signature is invalid
            $decoded = JWT::decode($idToken, $key);

            Log::debug('OIDC ID token signature verified', [
                'connection_id' => $connection->id,
                'algorithm' => $header['alg'],
                'kid' => $kid,
            ]);
        } catch (\Exception $e) {
            Log::warning('OIDC ID token signature verification failed', [
                'connection_id' => $connection->id,
                'error' => $e->getMessage(),
            ]);

            throw new \Exception('ID token signature verification failed: ' . $e->getMessage());
        }
    }

    /**
     * Get JWKS from provider's well-known endpoint.
     *
     * Caches the JWKS for 1 hour to reduce network requests.
     * JWKS typically don't change frequently, but providers may rotate keys.
     *
     * JWKS URI resolution order:
     * 1. Custom jwks_uri from connection options (for non-standard providers)
     * 2. jwks_uri from OpenID configuration discovery
     * 3. Standard fallback: /.well-known/jwks.json
     */
    protected function getJwks(IdentityConnection $connection): array
    {
        $cacheKey = "oidc_jwks_{$connection->issuer}_{$connection->id}";

        return Cache::remember($cacheKey, 3600, function () use ($connection) {
            // Try multiple methods to find JWKS URI
            $jwksUri = $this->resolveJwksUri($connection);

            $response = Http::timeout(10)->get($jwksUri);

            if (!$response->successful()) {
                throw new \Exception("Failed to fetch JWKS from provider: {$jwksUri} (HTTP {$response->status()})");
            }

            $jwks = $response->json();

            if (!isset($jwks['keys']) || !is_array($jwks['keys']) || empty($jwks['keys'])) {
                throw new \Exception('Invalid JWKS format: missing or empty keys array');
            }

            Log::debug('OIDC JWKS fetched', [
                'connection_id' => $connection->id,
                'jwks_uri' => $jwksUri,
                'key_count' => count($jwks['keys']),
                'source' => $this->getJwksUriSource($connection),
            ]);

            return $jwks;
        });
    }

    /**
     * Resolve JWKS URI using multiple fallback strategies.
     */
    protected function resolveJwksUri(IdentityConnection $connection): string
    {
        // 1. Check for custom jwks_uri in connection options (for non-standard providers)
        $customJwksUri = $connection->options['jwks_uri'] ?? null;
        if ($customJwksUri && filter_var($customJwksUri, FILTER_VALIDATE_URL)) {
            Log::debug('Using custom JWKS URI from connection options', [
                'connection_id' => $connection->id,
                'jwks_uri' => $customJwksUri,
            ]);
            return $customJwksUri;
        }

        // 2. Try to get JWKS URI from OpenID configuration discovery
        try {
            $openIdConfig = $this->getOpenIdConfig($connection);
            $jwksUri = $openIdConfig['jwks_uri'] ?? null;

            if ($jwksUri && filter_var($jwksUri, FILTER_VALIDATE_URL)) {
                Log::debug('Using JWKS URI from OpenID configuration', [
                    'connection_id' => $connection->id,
                    'jwks_uri' => $jwksUri,
                ]);
                return $jwksUri;
            }
        } catch (\Exception $e) {
            Log::warning('Failed to fetch OpenID configuration, using fallback', [
                'connection_id' => $connection->id,
                'error' => $e->getMessage(),
            ]);
        }

        // 3. Standard fallback: construct JWKS URI from issuer
        $jwksUri = rtrim($connection->issuer, '/') . '/.well-known/jwks.json';
        Log::debug('Using standard JWKS URI fallback', [
            'connection_id' => $connection->id,
            'jwks_uri' => $jwksUri,
        ]);

        return $jwksUri;
    }

    /**
     * Get the source of the JWKS URI for logging purposes.
     */
    protected function getJwksUriSource(IdentityConnection $connection): string
    {
        if (!empty($connection->options['jwks_uri'])) {
            return 'custom';
        }

        try {
            $openIdConfig = $this->getOpenIdConfig($connection);
            if (!empty($openIdConfig['jwks_uri'])) {
                return 'openid_config';
            }
        } catch (\Exception $e) {
            // Ignore
        }

        return 'fallback';
    }

    /**
     * Get OpenID configuration from provider.
     */
    protected function getOpenIdConfig(IdentityConnection $connection): array
    {
        $cacheKey = "oidc_config_{$connection->issuer}";

        return Cache::remember($cacheKey, 3600, function () use ($connection) {
            $wellKnownUrl = rtrim($connection->issuer, '/') . '/.well-known/openid-configuration';

            $response = Http::timeout(10)->get($wellKnownUrl);

            if (!$response->successful()) {
                throw new \Exception("Failed to fetch OpenID configuration from: {$wellKnownUrl}");
            }

            return $response->json();
        });
    }

    /**
     * Base64 URL decode.
     */
    protected function base64UrlDecode(string $data): string
    {
        return base64_decode(str_pad(
            strtr($data, '-_', '+/'),
            strlen($data) % 4,
            '=',
            STR_PAD_RIGHT
        ));
    }
}
