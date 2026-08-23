<?php

use App\Models\User;
use App\Service\OAuth\McpOAuthSessionService;
use App\Service\OAuth\McpPassportClientRepository;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Laravel\Passport\ClientRepository;
use Laravel\Passport\Passport;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

beforeEach(function () {
    config()->set('app.front_url', 'https://opnform.test');

    $key = openssl_pkey_new([
        'digest_alg' => 'sha256',
        'private_key_bits' => 2048,
        'private_key_type' => OPENSSL_KEYTYPE_RSA,
    ]);
    openssl_pkey_export($key, $privateKey);
    $publicKey = openssl_pkey_get_details($key)['key'];

    config()->set('passport.private_key', $privateKey);
    config()->set('passport.public_key', $publicKey);
});

it('configures delegated OAuth through its dedicated provider', function () {
    $jwtTtl = config('jwt.ttl');

    expect(app(ClientRepository::class))->toBeInstanceOf(McpPassportClientRepository::class)
        ->and(route('passport.token'))->toEndWith('/oauth/token')
        ->and(route('passport.authorizations.authorize'))->toEndWith('/oauth/authorize')
        ->and(config('auth.guards.oauth.driver'))->toBe('passport')
        ->and(config('auth.guards.mcp'))->toBeNull()
        ->and(config('auth.guards.api.driver'))->toBe('jwt');

    config()->set('oauth.access_token_ttl', (int) $jwtTtl + 1);

    expect(config('jwt.ttl'))->toBe($jwtTtl);
});

function mcpInitializePayload(): array
{
    return [
        'jsonrpc' => '2.0',
        'id' => 1,
        'method' => 'initialize',
        'params' => [
            'protocolVersion' => '2025-06-18',
            'capabilities' => [],
            'clientInfo' => [
                'name' => 'OAuth test client',
                'version' => '1.0.0',
            ],
        ],
    ];
}

function mcpHeaders(array $extra = []): array
{
    return array_merge([
        'Accept' => 'application/json, text/event-stream',
    ], $extra);
}

it('publishes OAuth 2.1 discovery metadata for the unified MCP endpoint', function () {
    $this->getJson('/.well-known/oauth-protected-resource/mcp')
        ->assertOk()
        ->assertJsonPath('resource', url('/mcp'))
        ->assertJsonPath('authorization_servers.0', url('/'))
        ->assertJsonPath('scopes_supported.0', 'mcp:use');

    $this->getJson('/.well-known/oauth-authorization-server')
        ->assertOk()
        ->assertJsonPath('authorization_endpoint', route('passport.authorizations.authorize'))
        ->assertJsonPath('token_endpoint', route('passport.token'))
        ->assertJsonPath('registration_endpoint', url('/oauth/register'))
        ->assertJsonPath('code_challenge_methods_supported.0', 'S256')
        ->assertJsonPath('token_endpoint_auth_methods_supported.0', 'none')
        ->assertJsonPath('scopes_supported.0', 'mcp:use');
});

it('requires S256 PKCE for every delegated authorization request', function (array $query) {
    $this->getJson('/oauth/authorize?'.http_build_query($query))
        ->assertBadRequest()
        ->assertJsonPath('error', 'invalid_request')
        ->assertJsonPath('error_description', 'The code_challenge_method must be S256.');
})->with([
    'missing method' => [['code_challenge' => 'challenge']],
    'plain method' => [[
        'code_challenge' => 'challenge',
        'code_challenge_method' => 'plain',
    ]],
]);

it('returns a tool-level OAuth challenge when an account tool is called anonymously', function () {
    $response = $this->postJson('/mcp', [
        'jsonrpc' => '2.0',
        'id' => 1,
        'method' => 'tools/call',
        'params' => [
            'name' => 'list_forms',
            'arguments' => [],
        ],
    ], mcpHeaders())->assertOk()
        ->assertJsonPath('result.isError', true);

    $challenge = $response->json('result._meta.mcp/www_authenticate.0');

    expect($challenge)
        ->toContain('Bearer resource_metadata="'.route('mcp.oauth.protected-resource.nested', ['path' => 'mcp']).'"')
        ->toContain('scope="mcp:use"')
        ->toContain('error="insufficient_scope"')
        ->toContain('error_description="Connect your OpnForm account to continue"');
});

it('creates and previews a guest form over HTTP without an OAuth challenge', function () {
    $createResponse = $this->postJson('/mcp', [
        'jsonrpc' => '2.0',
        'id' => 1,
        'method' => 'tools/call',
        'params' => [
            'name' => 'create_form_draft',
            'arguments' => [
                'definition' => [
                    'title' => 'Contact form',
                    'properties' => [
                        ['name' => 'Name', 'type' => 'text', 'required' => true],
                        ['name' => 'Email', 'type' => 'email', 'required' => true],
                        ['name' => 'Subject', 'type' => 'text'],
                        ['name' => 'Message', 'type' => 'text', 'multi_lines' => true],
                    ],
                ],
            ],
        ],
    ], mcpHeaders())->assertOk()
        ->assertJsonPath('result.isError', false)
        ->assertJsonMissingPath('result._meta.mcp/www_authenticate');

    $draftToken = $createResponse->json('result.structuredContent.draft_token');

    expect($draftToken)->toBeString()->toHaveLength(43);

    $this->postJson('/mcp', [
        'jsonrpc' => '2.0',
        'id' => 2,
        'method' => 'tools/call',
        'params' => [
            'name' => 'preview_form_draft',
            'arguments' => [
                'draft_token' => $draftToken,
            ],
        ],
    ], mcpHeaders())->assertOk()
        ->assertJsonPath('result.isError', false)
        ->assertJsonPath('result.structuredContent.draft.definition.title', 'Contact form')
        ->assertJsonPath('result.structuredContent.preview_url', fn (string $url): bool => str_starts_with($url, 'https://opnform.test/'))
        ->assertJsonPath('result.structuredContent.editor_url', fn (string $url): bool => str_starts_with($url, 'https://opnform.test/'))
        ->assertJsonMissingPath('result._meta.mcp/www_authenticate');
});

it('does not treat a normal OpnForm web session as MCP authentication', function () {
    $user = User::factory()->create();

    $this->actingAs($user, 'web')->postJson('/mcp', [
        'jsonrpc' => '2.0',
        'id' => 1,
        'method' => 'tools/call',
        'params' => [
            'name' => 'get_account_context',
            'arguments' => [],
        ],
    ], mcpHeaders())->assertOk()
        ->assertJsonPath('result.isError', true)
        ->assertJsonPath('result._meta.mcp/www_authenticate.0', fn (string $challenge) => str_contains(
            $challenge,
            'error="insufficient_scope"',
        ));
});

it('uses a scoped MCP bearer token for account tool calls', function () {
    $user = User::factory()->create();
    Passport::actingAs($user, ['mcp:use'], 'oauth');

    $this->postJson('/mcp', [
        'jsonrpc' => '2.0',
        'id' => 1,
        'method' => 'tools/call',
        'params' => [
            'name' => 'get_account_context',
            'arguments' => [],
        ],
    ], mcpHeaders([
        'Authorization' => 'Bearer scoped-account-token',
    ]))->assertOk()
        ->assertJsonPath('result.isError', false)
        ->assertJsonPath('result.structuredContent.account.id', $user->id)
        ->assertJsonMissingPath('result._meta.mcp/www_authenticate');
});

it('dynamically registers public PKCE clients only for allowed redirects', function () {
    $this->postJson('/oauth/register', [
        'client_name' => 'ChatGPT',
        'redirect_uris' => ['https://chatgpt.com/connector/oauth/local-test'],
    ])->assertCreated()
        ->assertJsonPath('token_endpoint_auth_method', 'none')
        ->assertJsonPath('scope', 'mcp:use')
        ->assertJsonPath('redirect_uris.0', 'https://chatgpt.com/connector/oauth/local-test');

    $this->assertDatabaseCount('oauth_clients', 1);

    $this->postJson('/oauth/register', [
        'client_name' => 'Untrusted client',
        'redirect_uris' => ['https://attacker.example/callback'],
    ])->assertBadRequest()
        ->assertJsonPath('error', 'invalid_redirect_uri');

    $this->assertDatabaseCount('oauth_clients', 1);

    $this->postJson('/oauth/register', [
        'client_name' => 'Delimiter injection',
        'redirect_uris' => ['https://chatgpt.com/callback,https://attacker.example/callback'],
    ])->assertBadRequest()
        ->assertJsonPath('error', 'invalid_redirect_uri');

    $this->assertDatabaseCount('oauth_clients', 1);

    $this->postJson('/oauth/register', [
        'client_name' => 'Loopback userinfo injection',
        'redirect_uris' => ['http://localhost:123@attacker.example/callback'],
    ])->assertBadRequest()
        ->assertJsonPath('error', 'invalid_redirect_uri');

    $this->assertDatabaseCount('oauth_clients', 1);
});

it('rejects PKCE methods other than S256', function () {
    $registration = $this->postJson('/oauth/register', [
        'client_name' => 'Plain PKCE client',
        'redirect_uris' => ['http://localhost/callback'],
    ])->assertCreated();

    $this->actingAs(User::factory()->create(), 'web')
        ->get('/oauth/authorize?'.http_build_query([
            'client_id' => $registration->json('client_id'),
            'redirect_uri' => 'http://localhost/callback',
            'response_type' => 'code',
            'scope' => 'mcp:use',
            'state' => 'oauth-state',
            'code_challenge' => str_repeat('a', 64),
            'code_challenge_method' => 'plain',
        ]))
        ->assertBadRequest()
        ->assertJsonPath('error', 'invalid_request')
        ->assertJsonPath('error_description', 'The code_challenge_method must be S256.');
});

it('keeps guest MCP available while accepting properly scoped Passport users', function () {
    $this->postJson('/mcp', mcpInitializePayload(), mcpHeaders())
        ->assertOk();

    $user = User::factory()->create();
    Passport::actingAs($user, ['mcp:use'], 'oauth');

    $this->postJson('/mcp', mcpInitializePayload(), mcpHeaders([
        'Authorization' => 'Bearer test-passport-token',
    ]))->assertOk();
});

it('completes PKCE authorization and uses the issued bearer token on the same endpoint', function () {
    $registration = $this->postJson('/oauth/register', [
        'client_name' => 'MCP integration test',
        'redirect_uris' => ['http://localhost/callback'],
    ])->assertCreated();

    $clientId = $registration->json('client_id');
    $verifier = str_repeat('a', 64);
    $challenge = rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');
    $authorizationQuery = http_build_query([
        'client_id' => $clientId,
        'redirect_uri' => 'http://localhost/callback',
        'response_type' => 'code',
        'scope' => 'mcp:use',
        'state' => 'oauth-state',
        'code_challenge' => $challenge,
        'code_challenge_method' => 'S256',
    ]);

    $user = User::factory()->create();
    $consent = $this->actingAs($user, 'web')
        ->get('/oauth/authorize?'.$authorizationQuery)
        ->assertOk()
        ->assertSee('Connect MCP integration test')
        ->assertSee('Use OpnForm MCP features')
        ->assertSee('http://localhost/callback')
        ->assertSee('aria-label="OpnForm"', false)
        ->assertHeader('Content-Security-Policy', "frame-ancestors 'none'")
        ->assertHeader('X-Frame-Options', 'DENY')
        ->assertHeader('Referrer-Policy', 'no-referrer')
        ->assertHeader('Cache-Control', 'no-store, private');

    preg_match('/name="auth_token" value="([^"]+)"/', $consent->getContent(), $authTokenMatch);

    $approval = $this->actingAs($user, 'web')->post('/oauth/authorize', [
        'client_id' => $clientId,
        'auth_token' => $authTokenMatch[1],
    ])->assertRedirect();

    parse_str((string) parse_url($approval->headers->get('Location'), PHP_URL_QUERY), $callbackQuery);

    $token = $this->post('/oauth/token', [
        'grant_type' => 'authorization_code',
        'client_id' => $clientId,
        'redirect_uri' => 'http://localhost/callback',
        'code_verifier' => $verifier,
        'code' => $callbackQuery['code'],
    ])->assertOk();

    $authorizationHeader = ['Authorization' => 'Bearer '.$token->json('access_token')];

    $this->postJson('/mcp', mcpInitializePayload(), mcpHeaders($authorizationHeader))->assertOk();

    $this->deleteJson('/mcp-oauth/session', [], $authorizationHeader)->assertNoContent();

    $this->app['auth']->forgetGuards();
    $this->postJson('/mcp', mcpInitializePayload(), mcpHeaders($authorizationHeader))->assertUnauthorized();

    $this->post('/oauth/token', [
        'grant_type' => 'refresh_token',
        'client_id' => $clientId,
        'refresh_token' => $token->json('refresh_token'),
    ])->assertUnauthorized();
});

it('rejects an OAuth token without the MCP scope and advertises discovery', function () {
    $user = User::factory()->create();
    Passport::actingAs($user, [], 'oauth');

    $this->postJson('/mcp', mcpInitializePayload(), mcpHeaders([
        'Authorization' => 'Bearer insufficient-token',
    ]))->assertUnauthorized()
        ->assertHeader('WWW-Authenticate');
});

it('rejects MCP access for blocked authenticated accounts', function () {
    $user = User::factory()->create(['blocked_at' => now()]);
    Passport::actingAs($user, ['mcp:use'], 'oauth');

    $this->postJson('/mcp', mcpInitializePayload(), mcpHeaders([
        'Authorization' => 'Bearer blocked-user-token',
    ]))->assertForbidden()
        ->assertJsonPath('message', 'Your account has been blocked. Please contact support.');
});

it('bridges normal frontend authentication into a one-time Passport web session', function () {
    config()->set('app.front_url', 'https://opnform.test');
    $sessions = app(McpOAuthSessionService::class);
    $oauthRequest = Request::create(
        '/oauth/authorize?client_id=7&redirect_uri=https%3A%2F%2Fchatgpt.com%2Fcallback&response_type=code&scope=mcp%3Ause&state=state-1&code_challenge=challenge&code_challenge_method=S256',
        'GET'
    );

    $frontendUrl = $sessions->beginAuthorization($oauthRequest);
    parse_str((string) parse_url($frontendUrl, PHP_URL_QUERY), $frontendQuery);

    expect($frontendUrl)->toStartWith('https://opnform.test/mcp/authorize?request=')
        ->and($frontendUrl)->not->toContain('client_id')
        ->and($frontendQuery['request'])->toHaveLength(64);

    $user = User::factory()->create();
    $this->actingAs($user, 'api')
        ->postJson('/mcp-oauth/session', [
            'authorization_request_token' => $frontendQuery['request'],
        ])->assertOk()
        ->assertJsonPath('authorization_url', fn (string $url) => str_contains($url, 'mcp_login_ticket='));

    $authorizationUrl = $this->actingAs($user, 'api')
        ->postJson('/mcp-oauth/session', [
            'authorization_request_token' => $frontendQuery['request'],
        ]);

    $authorizationUrl->assertBadRequest();
});

it('consumes login tickets once and removes them from the authorization URL', function () {
    $sessions = app(McpOAuthSessionService::class);
    $user = User::factory()->create();
    $oauthRequest = Request::create('/oauth/authorize?client_id=7&state=state-1', 'GET');
    $frontendUrl = $sessions->beginAuthorization($oauthRequest);
    parse_str((string) parse_url($frontendUrl, PHP_URL_QUERY), $frontendQuery);
    $authorizationUrl = $sessions->issueLoginTicket($frontendQuery['request'], $user);
    parse_str((string) parse_url($authorizationUrl, PHP_URL_QUERY), $authorizationQuery);

    $request = Request::create($authorizationUrl, 'GET');
    $request->setLaravelSession(app('session.store'));
    $sessions->consumeLoginTicket($authorizationQuery['mcp_login_ticket'], $request);

    expect(auth('web')->id())->toBe($user->id)
        ->and($sessions->withoutLoginTicket($request))->not->toContain('mcp_login_ticket')
        ->and($sessions->withoutLoginTicket($request))->toContain('client_id=7')
        ->and($sessions->withoutLoginTicket($request))->toContain('state=state-1');

    expect(fn () => $sessions->consumeLoginTicket($authorizationQuery['mcp_login_ticket'], $request))
        ->toThrow(AccessDeniedHttpException::class);
});

it('does not consume an authorization request while its atomic lock is held', function () {
    config()->set('app.front_url', 'https://opnform.test');
    $sessions = app(McpOAuthSessionService::class);
    $oauthRequest = Request::create(
        '/oauth/authorize?client_id=7&state=state-1&code_challenge='.str_repeat('a', 64).'&code_challenge_method=S256',
        'GET'
    );
    $frontendUrl = $sessions->beginAuthorization($oauthRequest);
    parse_str((string) parse_url($frontendUrl, PHP_URL_QUERY), $frontendQuery);

    $cacheKey = 'mcp:oauth:authorization-request:'.hash('sha256', $frontendQuery['request']);
    $lock = Cache::lock('mcp:oauth:consume:'.hash('sha256', $cacheKey), 5);
    expect($lock->get())->toBeTrue();

    try {
        expect(fn () => $sessions->issueLoginTicket($frontendQuery['request'], User::factory()->create()))
            ->toThrow(\Symfony\Component\HttpKernel\Exception\BadRequestHttpException::class);
    } finally {
        $lock->release();
    }

    expect($sessions->issueLoginTicket($frontendQuery['request'], User::factory()->create()))
        ->toContain('mcp_login_ticket=');
});

it('binds each login ticket to the exact OAuth authorization request', function () {
    $sessions = app(McpOAuthSessionService::class);
    $user = User::factory()->create();
    $oauthRequest = Request::create('/oauth/authorize?client_id=7&state=expected', 'GET');
    $frontendUrl = $sessions->beginAuthorization($oauthRequest);
    parse_str((string) parse_url($frontendUrl, PHP_URL_QUERY), $frontendQuery);
    $authorizationUrl = $sessions->issueLoginTicket($frontendQuery['request'], $user);
    parse_str((string) parse_url($authorizationUrl, PHP_URL_QUERY), $authorizationQuery);

    $swappedRequest = Request::create(
        '/oauth/authorize?client_id=99&state=attacker&mcp_login_ticket='.$authorizationQuery['mcp_login_ticket'],
        'GET'
    );
    $swappedRequest->setLaravelSession(app('session.store'));

    expect(fn () => $sessions->consumeLoginTicket(
        $authorizationQuery['mcp_login_ticket'],
        $swappedRequest
    ))->toThrow(AccessDeniedHttpException::class);
});
