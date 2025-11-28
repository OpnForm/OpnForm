<?php

namespace App\Integrations\OAuth\Drivers;

use App\Exceptions\OAuth\InvalidWidgetDataException;
use App\Integrations\OAuth\Drivers\Contracts\WidgetOAuthDriver;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Laravel\Socialite\Contracts\User;

class OAuthGoogleOneTapDriver implements WidgetOAuthDriver
{
    public function getRedirectUrl(): string
    {
        throw new \Exception('Google One Tap does not use redirect URLs');
    }

    public function setRedirectUrl(string $url): self
    {
        return $this;
    }

    public function setScopes(array $scopes): self
    {
        return $this;
    }

    public function setState(string $state): self
    {
        // Widget-based auth doesn't use state, but interface requires it
        return $this;
    }

    public function getUser(): User
    {
        throw new \Exception('Use getUserFromWidgetData for Google One Tap');
    }

    public function canCreateUser(): bool
    {
        return true;
    }

    public function getScopesForIntent(string $intent): array
    {
        return ['openid', 'profile', 'email']; // One Tap only supports these
    }

    public function isWidgetBased(): bool
    {
        return true;
    }

    public function verifyWidgetData(array $data): bool
    {
        if (!isset($data['credential'])) {
            throw new InvalidWidgetDataException('Missing Google One Tap credential');
        }

        try {
            $this->verifyAndDecodeJWT($data['credential']);
            return true;
        } catch (InvalidWidgetDataException $e) {
            throw $e;
        } catch (\Throwable $e) {
            throw new InvalidWidgetDataException('Invalid Google One Tap credential: ' . $e->getMessage());
        }
    }

    public function getUserFromWidgetData(array $data): array
    {
        $payload = $this->verifyAndDecodeJWT($data['credential']);

        return [
            'id' => $payload['sub'],
            'name' => $payload['name'],
            'email' => $payload['email'],
            'provider_user_id' => $payload['sub'], // Google ID saved here!
            'access_token' => $data['credential'], // Store the JWT
            'refresh_token' => null,
            'avatar' => $payload['picture'] ?? null,
            'scopes' => ['openid', 'profile', 'email'],
        ];
    }

    private function verifyAndDecodeJWT(string $jwt): array
    {
        // Split JWT into parts
        $parts = explode('.', $jwt);
        if (count($parts) !== 3) {
            throw new InvalidWidgetDataException('Invalid JWT format');
        }

        [$headerB64, $payloadB64, $signatureB64] = $parts;

        // Decode header and payload
        $header = $this->base64UrlDecode($headerB64);
        $payload = $this->base64UrlDecode($payloadB64);

        $headerData = json_decode($header, true);
        $payloadData = json_decode($payload, true);

        if (!$headerData || !$payloadData) {
            throw new InvalidWidgetDataException('Invalid JWT data');
        }

        // Basic payload validation
        $this->validatePayload($payloadData);

        // Verify signature
        $this->verifySignature($headerB64 . '.' . $payloadB64, $signatureB64, $headerData);

        return $payloadData;
    }

    private function base64UrlDecode(string $data): string
    {
        // Convert base64url to base64
        $base64 = str_replace(['-', '_'], ['+', '/'], $data);

        // Add padding if needed
        $padLength = 4 - (strlen($base64) % 4);
        if ($padLength < 4) {
            $base64 .= str_repeat('=', $padLength);
        }

        return base64_decode($base64);
    }

    private function validatePayload(array $payload): void
    {
        // Check expiration
        if (!isset($payload['exp']) || $payload['exp'] < time()) {
            throw new InvalidWidgetDataException('JWT has expired');
        }

        // Check issued at (not too far in future)
        if (!isset($payload['iat']) || $payload['iat'] > time() + 300) {
            throw new InvalidWidgetDataException('JWT issued in future');
        }

        // Verify audience (your Google Client ID)
        $expectedClientId = config('services.google.client_id');
        if (!isset($payload['aud']) || $payload['aud'] !== $expectedClientId) {
            throw new InvalidWidgetDataException('Invalid audience');
        }

        // Verify issuer
        if (!isset($payload['iss']) || !in_array($payload['iss'], ['https://accounts.google.com', 'accounts.google.com'])) {
            throw new InvalidWidgetDataException('Invalid issuer');
        }

        // Check required fields
        if (!isset($payload['sub']) || !isset($payload['email'])) {
            throw new InvalidWidgetDataException('Missing required fields');
        }
    }

    private function verifySignature(string $data, string $signature, array $header): void
    {
        if (!isset($header['kid']) || !isset($header['alg'])) {
            throw new InvalidWidgetDataException('Missing header information');
        }

        if ($header['alg'] !== 'RS256') {
            throw new InvalidWidgetDataException('Unsupported algorithm');
        }

        // Get Google's public keys
        $publicKey = $this->getGooglePublicKey($header['kid']);

        // Verify signature
        $signatureRaw = $this->base64UrlDecode($signature);
        $verified = openssl_verify($data, $signatureRaw, $publicKey, OPENSSL_ALGO_SHA256);

        if ($verified !== 1) {
            throw new InvalidWidgetDataException('Invalid signature');
        }
    }

    private function getGooglePublicKey(string $keyId): string
    {
        // Cache public keys for 1 hour
        return Cache::remember("google_public_key_{$keyId}", 3600, function () use ($keyId) {
            $response = Http::get('https://www.googleapis.com/oauth2/v3/certs');

            if (!$response->successful()) {
                throw new InvalidWidgetDataException('Failed to fetch Google public keys');
            }

            $keys = $response->json();

            if (!isset($keys['keys'])) {
                throw new InvalidWidgetDataException('Invalid keys response');
            }

            foreach ($keys['keys'] as $key) {
                if ($key['kid'] === $keyId) {
                    // Convert JWK to PEM format
                    return $this->jwkToPem($key);
                }
            }

            throw new InvalidWidgetDataException('Key not found');
        });
    }

    private function jwkToPem(array $jwk): string
    {
        if (!isset($jwk['n']) || !isset($jwk['e'])) {
            throw new InvalidWidgetDataException('Invalid JWK format');
        }

        // Decode base64url modulus and exponent
        $n = $this->base64UrlDecode($jwk['n']);
        $e = $this->base64UrlDecode($jwk['e']);

        // Build the public key in DER format
        $modulus = $this->unsignedBigInteger($n);
        $exponent = $this->unsignedBigInteger($e);

        // RSA public key DER structure
        $rsaPublicKey = $this->derSequence($modulus . $exponent);

        // Public key info structure
        $rsaIdentifier = pack('H*', '300d06092a864886f70d0101010500'); // RSA identifier
        $publicKeyInfo = $this->derSequence($rsaIdentifier . $this->derBitString($rsaPublicKey));

        // Convert to PEM format
        return "-----BEGIN PUBLIC KEY-----\n" .
            chunk_split(base64_encode($publicKeyInfo), 64, "\n") .
            "-----END PUBLIC KEY-----\n";
    }

    private function unsignedBigInteger(string $bytes): string
    {
        // Add leading zero if the first bit is set (to ensure positive integer)
        if (ord($bytes[0]) & 0x80) {
            $bytes = "\x00" . $bytes;
        }

        return $this->derInteger($bytes);
    }

    private function derInteger(string $bytes): string
    {
        return "\x02" . $this->derLength(strlen($bytes)) . $bytes;
    }

    private function derSequence(string $bytes): string
    {
        return "\x30" . $this->derLength(strlen($bytes)) . $bytes;
    }

    private function derBitString(string $bytes): string
    {
        return "\x03" . $this->derLength(strlen($bytes) + 1) . "\x00" . $bytes;
    }

    private function derLength(int $length): string
    {
        if ($length < 128) {
            return chr($length);
        }

        $lengthBytes = '';
        while ($length > 0) {
            $lengthBytes = chr($length & 0xff) . $lengthBytes;
            $length >>= 8;
        }

        return chr(0x80 | strlen($lengthBytes)) . $lengthBytes;
    }
}
