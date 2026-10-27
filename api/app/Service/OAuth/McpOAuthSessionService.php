<?php

namespace App\Service\OAuth;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class McpOAuthSessionService
{
    private const AUTHORIZATION_REQUEST_PREFIX = 'mcp:oauth:authorization-request:';

    private const LOGIN_TICKET_PREFIX = 'mcp:oauth:login-ticket:';

    private const WEB_SESSION_CREDENTIALS = 'mcp:oauth:credentials';

    public function beginAuthorization(Request $request): string
    {
        $frontUrl = rtrim((string) config('app.front_url'), '/');
        if (! filter_var($frontUrl, FILTER_VALIDATE_URL)) {
            throw new RuntimeException('FRONT_URL must be configured to use MCP OAuth.');
        }

        $token = Str::random(64);

        Cache::put(
            self::AUTHORIZATION_REQUEST_PREFIX.hash('sha256', $token),
            $request->getRequestUri(),
            now()->addMinutes(10)
        );

        return $frontUrl.'/mcp/authorize?request='.rawurlencode($token);
    }

    public function issueLoginTicket(string $authorizationRequestToken, User $user): string
    {
        $requestUri = $this->pullOnce(
            self::AUTHORIZATION_REQUEST_PREFIX.hash('sha256', $authorizationRequestToken)
        );

        if (! is_string($requestUri) || ! $this->isAuthorizationRequestUri($requestUri)) {
            throw new BadRequestHttpException('This OAuth authorization request is invalid or has expired.');
        }

        $ticket = Str::random(64);

        Cache::put(
            self::LOGIN_TICKET_PREFIX.hash('sha256', $ticket),
            [
                'user_id' => $user->getAuthIdentifier(),
                'auth_version' => $user->auth_version,
                'request_uri' => $this->removeQueryParameter($requestUri, 'mcp_login_ticket'),
            ],
            now()->addMinutes(5)
        );

        return url($this->appendQueryParameter($requestUri, 'mcp_login_ticket', $ticket));
    }

    public function consumeLoginTicket(string $ticket, Request $request): void
    {
        $ticketData = $this->pullOnce(self::LOGIN_TICKET_PREFIX.hash('sha256', $ticket));
        $expectedRequestUri = is_array($ticketData) ? ($ticketData['request_uri'] ?? null) : null;
        $userId = is_array($ticketData) ? ($ticketData['user_id'] ?? null) : null;

        if ((! is_int($userId) && ! is_string($userId))
            || ! is_string($expectedRequestUri)
            || ! hash_equals(
                $expectedRequestUri,
                $this->removeQueryParameter($request->getRequestUri(), 'mcp_login_ticket')
            )) {
            throw new AccessDeniedHttpException('This MCP login ticket is invalid or has expired.');
        }

        $user = User::find($userId);
        if (! $user || ! array_key_exists('auth_version', $ticketData)
            || $ticketData['auth_version'] !== $user->auth_version) {
            throw new AccessDeniedHttpException('This MCP login ticket is invalid or has expired.');
        }

        Auth::guard('web')->login($user);
        $this->rememberWebCredentials($request, $user);
        $request->session()->regenerate();
    }

    public function ensureCurrentWebSession(Request $request): void
    {
        $guard = Auth::guard('web');
        if (! $request->hasSession() || ! $guard->check()) {
            return;
        }

        // Query the current version even if the guard already has a cached user.
        $user = User::find($guard->id());
        $credentials = $request->session()->get(self::WEB_SESSION_CREDENTIALS);

        // Existing sessions are compatible only until the first credential rotation.
        if ($credentials === null && $user && $user->auth_version === null) {
            $this->rememberWebCredentials($request, $user);
            return;
        }

        if (! $user || ! is_array($credentials)
            || ($credentials['user_id'] ?? null) !== $user->getAuthIdentifier()
            || ! array_key_exists('auth_version', $credentials)
            || $credentials['auth_version'] !== $user->auth_version) {
            $guard->logoutCurrentDevice();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
            throw new AuthenticationException('Authentication expired. Please sign in again.', ['web']);
        }
    }

    private function rememberWebCredentials(Request $request, User $user): void
    {
        $request->session()->put(self::WEB_SESSION_CREDENTIALS, [
            'user_id' => $user->getAuthIdentifier(),
            'auth_version' => $user->auth_version,
        ]);
    }

    public function withoutLoginTicket(Request $request): string
    {
        $query = $request->query();
        unset($query['mcp_login_ticket']);

        return $request->url().($query === [] ? '' : '?'.http_build_query($query));
    }

    private function isAuthorizationRequestUri(string $uri): bool
    {
        $parts = parse_url($uri);
        $expectedPath = '/'.trim((string) config('passport.path', 'oauth'), '/').'/authorize';

        return is_array($parts)
            && ($parts['path'] ?? null) === $expectedPath
            && isset($parts['query']);
    }

    private function appendQueryParameter(string $uri, string $name, string $value): string
    {
        $separator = str_contains($uri, '?') ? '&' : '?';

        return $uri.$separator.rawurlencode($name).'='.rawurlencode($value);
    }

    private function removeQueryParameter(string $uri, string $name): string
    {
        $parts = parse_url($uri);
        parse_str((string) ($parts['query'] ?? ''), $query);
        unset($query[$name]);

        $path = (string) ($parts['path'] ?? '');

        return $path.($query === [] ? '' : '?'.http_build_query($query));
    }

    private function pullOnce(string $key): mixed
    {
        $lock = Cache::lock('mcp:oauth:consume:'.hash('sha256', $key), 5);

        if (! $lock->get()) {
            return null;
        }

        try {
            return Cache::pull($key);
        } finally {
            $lock->release();
        }
    }
}
