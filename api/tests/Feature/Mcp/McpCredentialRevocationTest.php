<?php

use App\Models\User;
use App\Service\OAuth\McpOAuthSessionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

beforeEach(function () {
    config()->set('app.front_url', 'https://opnform.test');
    $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
    openssl_pkey_export($key, $privateKey);
    config()->set('passport.private_key', $privateKey);
    config()->set('passport.public_key', openssl_pkey_get_details($key)['key']);

    $registration = $this->postJson('/oauth/register', [
        'client_name' => 'Credential rotation test',
        'redirect_uris' => ['http://localhost/callback'],
    ])->assertCreated();
    $this->clientId = $registration->json('client_id');
    $this->verifier = str_repeat('a', 64);
    $this->authorizationUri = '/oauth/authorize?'.http_build_query([
        'client_id' => $this->clientId,
        'redirect_uri' => 'http://localhost/callback',
        'response_type' => 'code',
        'scope' => 'mcp:use',
        'state' => 'credential-rotation',
        'code_challenge' => rtrim(strtr(base64_encode(hash('sha256', $this->verifier, true)), '+/', '-_'), '='),
        'code_challenge_method' => 'S256',
    ]);
    $this->user = User::factory()->create();
});

function mcpCredentialLoginTicket(User $user, string $uri): array
{
    $sessions = app(McpOAuthSessionService::class);
    $frontendUrl = $sessions->beginAuthorization(Request::create($uri));
    parse_str((string) parse_url($frontendUrl, PHP_URL_QUERY), $frontendQuery);
    $ticketUrl = $sessions->issueLoginTicket($frontendQuery['request'], $user);
    parse_str((string) parse_url($ticketUrl, PHP_URL_QUERY), $ticketQuery);

    return [$ticketUrl, $ticketQuery['mcp_login_ticket']];
}

it('rejects a previously issued login ticket after password rotation', function () {
    [$ticketUrl, $ticket] = mcpCredentialLoginTicket($this->user, $this->authorizationUri);
    $this->user->update(['password' => bcrypt('Changed@1234')]);
    $request = Request::create($ticketUrl);
    $request->setLaravelSession(app('session')->driver());

    expect(fn () => app(McpOAuthSessionService::class)->consumeLoginTicket($ticket, $request))
        ->toThrow(AccessDeniedHttpException::class);
    expect(Auth::guard('web')->guest())->toBeTrue();
});

it('rejects an existing consent session after password rotation', function (string $method) {
    $consent = $this->actingAs($this->user, 'web')->get($this->authorizationUri)->assertOk();
    preg_match('/name="auth_token" value="([^"]+)"/', $consent->getContent(), $matches);
    $this->user->update(['password' => bcrypt('Changed@1234')]);

    if ($method === 'GET') {
        $response = $this->get($this->authorizationUri)->assertRedirect();
        expect($response->headers->get('Location'))->toStartWith('https://opnform.test/mcp/authorize?request=');
    } else {
        $this->post('/oauth/authorize', ['client_id' => $this->clientId, 'auth_token' => $matches[1]])
            ->assertUnauthorized();
    }

    expect(Auth::guard('web')->guest())->toBeTrue();
})->with(['GET', 'POST']);

it('rejects an unstamped legacy web session when credentials have already rotated', function () {
    $this->user->update(['password' => bcrypt('Changed@1234')]);
    $response = $this->actingAs($this->user, 'web')->get($this->authorizationUri)->assertRedirect();

    expect($response->headers->get('Location'))->toStartWith('https://opnform.test/mcp/authorize?request=');
    expect(Auth::guard('web')->guest())->toBeTrue();
});

it('revokes an authorization code before it can mint tokens after password rotation', function () {
    $consent = $this->actingAs($this->user, 'web')->get($this->authorizationUri)->assertOk();
    preg_match('/name="auth_token" value="([^"]+)"/', $consent->getContent(), $matches);
    $approval = $this->post('/oauth/authorize', [
        'client_id' => $this->clientId,
        'auth_token' => $matches[1],
    ])->assertRedirect();
    parse_str((string) parse_url($approval->headers->get('Location'), PHP_URL_QUERY), $callback);

    $this->user->update(['password' => bcrypt('Changed@1234')]);

    $this->assertDatabaseHas('oauth_auth_codes', ['user_id' => $this->user->id, 'revoked' => true]);

    $this->postJson('/oauth/token', [
        'grant_type' => 'authorization_code',
        'client_id' => $this->clientId,
        'redirect_uri' => 'http://localhost/callback',
        'code_verifier' => $this->verifier,
        'code' => $callback['code'],
    ])->assertBadRequest()->assertJsonPath('error', 'invalid_request');
    $this->assertDatabaseMissing('oauth_access_tokens', ['user_id' => $this->user->id, 'revoked' => false]);
});

it('still completes fresh ticket login and PKCE consent after password rotation', function () {
    $this->user->update(['password' => bcrypt('Changed@1234')]);
    [$ticketUrl] = mcpCredentialLoginTicket($this->user->fresh(), $this->authorizationUri);
    $this->get($ticketUrl)->assertRedirect();
    $consent = $this->get($this->authorizationUri)->assertOk();
    preg_match('/name="auth_token" value="([^"]+)"/', $consent->getContent(), $matches);
    $approval = $this->post('/oauth/authorize', [
        'client_id' => $this->clientId,
        'auth_token' => $matches[1],
    ])->assertRedirect();
    parse_str((string) parse_url($approval->headers->get('Location'), PHP_URL_QUERY), $callback);

    $this->postJson('/oauth/token', [
        'grant_type' => 'authorization_code',
        'client_id' => $this->clientId,
        'redirect_uri' => 'http://localhost/callback',
        'code_verifier' => $this->verifier,
        'code' => $callback['code'],
    ])->assertOk()->assertJsonStructure(['access_token', 'refresh_token']);
});
