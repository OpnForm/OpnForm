<?php

namespace App\Enterprise\Oidc\Adapters\Socialite;

use Exception;
use GuzzleHttp\RequestOptions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Laravel\Socialite\Two\AbstractProvider;
use Laravel\Socialite\Two\User;

class OidcProvider extends AbstractProvider
{
    public const IDENTIFIER = 'OIDC';

    protected $scopes = ['openid', 'profile', 'email'];

    protected $scopeSeparator = ' ';

    protected bool $usesNonce = true;

    protected ?string $issuer = null;

    protected ?array $openIdConfig = null;

    protected ?string $nonce = null;

    /**
     * Store token response for later use.
     */
    protected array $tokenResponse = [];

    /**
     * Get the token response (including id_token and access_token).
     */
    public function getTokenResponse(): array
    {
        return $this->tokenResponse;
    }

    /**
     * Get the ID token from token response.
     */
    public function getIdToken(): ?string
    {
        return $this->tokenResponse['id_token'] ?? null;
    }

    /**
     * Get the access token from token response.
     */
    public function getAccessToken(): ?string
    {
        return $this->tokenResponse['access_token'] ?? null;
    }

    public function setIssuer(string $issuer): self
    {
        $this->issuer = $issuer;
        return $this;
    }

    protected function getOpenIdConfig(): array
    {
        if ($this->openIdConfig !== null) {
            return $this->openIdConfig;
        }

        $cacheKey = "oidc_config_{$this->issuer}";

        return Cache::remember($cacheKey, 3600, function () {
            $wellKnownUrl = rtrim($this->issuer, '/') . '/.well-known/openid-configuration';

            $response = $this->getHttpClient()->get($wellKnownUrl, [
                RequestOptions::HEADERS => ['Accept' => 'application/json'],
            ]);

            return json_decode((string) $response->getBody(), true);
        });
    }

    public function redirect(): RedirectResponse
    {
        $state = null;

        if ($this->usesState()) {
            $state = $this->getState();
            if (!$state) {
                $state = bin2hex(random_bytes(16));
            }
            Cache::put("oidc_state_{$state}", $state, 600);
        } elseif ($this->usesNonce) {
            $state = bin2hex(random_bytes(16));
            Cache::put("oidc_state_{$state}", $state, 600);
        }

        if ($this->usesNonce) {
            $nonce = Str::random(40);
            $this->nonce = $nonce;
            if ($state) {
                Cache::put("oidc_nonce_{$state}", $nonce, 600);
            }
            Cache::put("oidc_nonce_value_{$nonce}", $nonce, 600);
        }

        return new RedirectResponse($this->getAuthUrl($state));
    }

    protected function getAuthUrl($state): string
    {
        return $this->buildAuthUrlFromBase(
            $this->getOpenIdConfig()['authorization_endpoint'],
            $state
        );
    }

    protected function buildAuthUrlFromBase($url, $state): string
    {
        return $url . '?' . http_build_query($this->getCodeFields($state), '', '&', $this->encodingType);
    }

    protected function getCodeFields($state = null): array
    {
        $fields = [
            'client_id' => $this->clientId,
            'redirect_uri' => $this->redirectUrl,
            'scope' => $this->formatScopes($this->getScopes(), $this->scopeSeparator),
            'response_type' => 'code',
        ];

        if ($this->usesState() && $state) {
            $fields['state'] = $state;
        }

        if ($this->usesNonce && $this->nonce) {
            $fields['nonce'] = $this->nonce;
        }

        return array_merge($fields, $this->parameters);
    }

    protected function getTokenUrl()
    {
        return $this->getOpenIdConfig()['token_endpoint'];
    }

    protected function getUserInfoUrl(): string
    {
        return $this->getOpenIdConfig()['userinfo_endpoint'] ?? '';
    }

    protected function getUserByToken($token)
    {
        if (!empty($this->tokenResponse['id_token'])) {
            return $this->decodeIdToken($this->tokenResponse['id_token']);
        }

        $userInfoUrl = $this->getUserInfoUrl();
        if (!empty($userInfoUrl)) {
            $response = $this->getHttpClient()->get(
                $userInfoUrl,
                [
                    RequestOptions::HEADERS => [
                        'Accept' => 'application/json',
                        'Authorization' => 'Bearer ' . $token,
                    ],
                ]
            );

            return json_decode((string) $response->getBody(), true);
        }

        throw new Exception('Unable to retrieve user information: no ID token or userinfo endpoint');
    }

    public function getAccessTokenResponse($code)
    {
        $response = $this->getHttpClient()->post($this->getTokenUrl(), [
            RequestOptions::HEADERS => ['Accept' => 'application/json'],
            RequestOptions::FORM_PARAMS => array_merge(
                $this->getTokenFields($code),
                [
                    'grant_type' => 'authorization_code',
                ]
            ),
        ]);

        $this->tokenResponse = json_decode((string) $response->getBody(), true);

        return $this->tokenResponse;
    }

    protected function decodeIdToken(string $idToken): array
    {
        try {
            [$header, $payload, $signature] = explode('.', $idToken);
            $decoded = json_decode($this->base64UrlDecode($payload), true);

            if ($this->usesNonce && isset($decoded['nonce'])) {
                $storedNonce = null;
                $state = $this->request->input('state');
                $idTokenNonce = $decoded['nonce'];

                if ($state) {
                    $cacheKey = "oidc_nonce_{$state}";
                    $storedNonce = Cache::get($cacheKey);

                    if ($storedNonce) {
                        Cache::forget($cacheKey);
                        Cache::forget("oidc_nonce_value_{$storedNonce}");
                    }
                }

                if (!$storedNonce) {
                    $nonceCacheKey = "oidc_nonce_value_{$idTokenNonce}";
                    $storedNonce = Cache::get($nonceCacheKey);

                    if ($storedNonce) {
                        Cache::forget($nonceCacheKey);
                    }
                }

                if (!$storedNonce || $storedNonce !== $idTokenNonce) {
                    throw new Exception('Invalid nonce in ID token');
                }
            }

            return $decoded;
        } catch (Exception $e) {
            throw new Exception('Failed to decode ID token: ' . $e->getMessage());
        }
    }

    protected function base64UrlDecode(string $data): string
    {
        return base64_decode(str_pad(
            strtr($data, '-_', '+/'),
            strlen($data) % 4,
            '=',
            STR_PAD_RIGHT
        ));
    }

    protected function mapUserToObject(array $user)
    {
        return (new User())->setRaw($user)->map([
            'id' => $user['sub'] ?? $user['id'] ?? null,
            'email' => $user['email'] ?? null,
            'name' => $user['name'] ?? null,
            'nickname' => $user['nickname'] ?? null,
            'given_name' => $user['given_name'] ?? null,
            'family_name' => $user['family_name'] ?? null,
            'groups' => $user['groups'] ?? $user['group'] ?? [],
        ]);
    }
}
