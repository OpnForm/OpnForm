<?php

use App\Enums\SettingsKey;
use App\Models\Setting;
use Laravel\Passport\Passport;

function mcpSettingsPassportKeyPair(): array
{
    $key = openssl_pkey_new([
        'private_key_bits' => 2048,
        'private_key_type' => OPENSSL_KEYTYPE_RSA,
    ]);
    openssl_pkey_export($key, $privateKey);
    $details = openssl_pkey_get_details($key);

    return [$privateKey, $details['key']];
}

beforeEach(function () {
    Passport::$keyPath = null;
    config()->set('app.self_hosted', true);
    config()->set('app.url', 'https://forms.example.com');
    config()->set('app.front_url', 'https://forms.example.com');
    config()->set('opnform.mcp.enabled', false);
    config()->set('oauth.enabled', true);

    [$privateKey, $publicKey] = mcpSettingsPassportKeyPair();
    config()->set('passport.private_key', $privateKey);
    config()->set('passport.public_key', $publicKey);

    $this->user = $this->actingAsUser();
    $this->workspace = $this->createUserWorkspace($this->user);
});

it('returns connection details generated for the self-hosted instance', function () {
    $response = $this->getJson('/settings/mcp');

    $response->assertSuccessful()
        ->assertJson([
            'enabled' => false,
            'available' => false,
            'configured_value' => null,
            'source' => 'environment',
            'ready' => true,
            'server_url' => 'https://forms.example.com/mcp',
            'settings_url' => 'https://forms.example.com/?user-settings=mcp',
        ]);

    expect($response->json('snippets.cursor'))
        ->toContain('https://forms.example.com/mcp')
        ->not->toContain('api.opnform.com');
    expect($response->json('snippets.claude_code'))->toBe("claude mcp add --transport http opnform 'https://forms.example.com/mcp'");
    expect($response->json('snippets.chatgpt'))->toContain('Authentication: OAuth');
    expect($response->json('snippets.codex'))->toBe("codex mcp add opnform --url 'https://forms.example.com/mcp'");
    expect($response->json('snippets.other'))->toContain('"type": "http"');
    expect($response->json('snippets.portable'))->toContain('streamable-http');
    expect($response->json('install_urls.cursor'))->toStartWith('cursor://anysphere.cursor-deeplink/mcp/install');

    parse_str(parse_url($response->json('install_urls.cursor'), PHP_URL_QUERY), $cursorQuery);
    expect(json_decode(base64_decode($cursorQuery['config'], true), true, flags: JSON_THROW_ON_ERROR))
        ->toBe(['url' => 'https://forms.example.com/mcp']);
});

it('stores an enabled override that wins over the environment default', function () {
    $this->putJson('/settings/mcp', ['enabled' => true])
        ->assertSuccessful()
        ->assertJson([
            'enabled' => true,
            'available' => true,
            'configured_value' => true,
            'source' => 'settings',
        ]);

    expect(Setting::get(SettingsKey::MCP_ENABLED))->toBeTrue();
});

it('stores a disabled override without revoking existing OAuth state', function () {
    Setting::set(SettingsKey::MCP_ENABLED, true);

    $this->putJson('/settings/mcp', ['enabled' => false])
        ->assertSuccessful()
        ->assertJson([
            'enabled' => false,
            'available' => false,
            'configured_value' => false,
        ]);

    expect(Setting::get(SettingsKey::MCP_ENABLED))->toBeFalse();
});

it('refuses activation until Passport and public URLs are ready', function () {
    config()->set('passport.private_key', null);
    config()->set('passport.public_key', null);
    Passport::loadKeysFrom(base_path('tests/Fixtures/missing-passport-keys'));
    config()->set('app.front_url', 'http://forms.example.com');

    $response = $this->putJson('/settings/mcp', ['enabled' => true]);

    $response->assertUnprocessable()
        ->assertJsonPath('message', 'Complete the MCP OAuth prerequisites before enabling MCP.');

    expect(collect($response->json('blockers'))->pluck('code')->all())
        ->toContain('passport_keys_missing', 'front_url_invalid');
    expect(Setting::get(SettingsKey::MCP_ENABLED))->toBeNull();
});

it('still exposes readiness guidance when the API URL is invalid', function () {
    config()->set('app.url', 'forms.example.com');

    $this->getJson('/settings/mcp')
        ->assertSuccessful()
        ->assertJsonPath('ready', false)
        ->assertJsonPath('blockers.0.code', 'app_url_invalid');
});

it('rejects base URLs that would produce ambiguous MCP endpoints', function (string $url) {
    config()->set('app.url', $url);

    $response = $this->getJson('/settings/mcp')
        ->assertSuccessful()
        ->assertJsonPath('ready', false);

    expect(collect($response->json('blockers'))->pluck('code')->all())
        ->toContain('app_url_invalid');
})->with([
    'credentials' => 'https://user:password@forms.example.com',
    'query parameters' => 'https://forms.example.com?tenant=one',
    'fragment' => 'https://forms.example.com#settings',
]);

it('supports self-hosted instances installed below a URL path', function () {
    config()->set('app.url', 'https://forms.example.com/opnform/');
    config()->set('app.front_url', 'https://forms.example.com/opnform/');

    $this->getJson('/settings/mcp')
        ->assertSuccessful()
        ->assertJson([
            'ready' => true,
            'server_url' => 'https://forms.example.com/opnform/mcp',
            'settings_url' => 'https://forms.example.com/opnform/?user-settings=mcp',
        ]);
});

it('allows disabling even when OAuth readiness is broken', function () {
    Setting::set(SettingsKey::MCP_ENABLED, true);
    config()->set('passport.private_key', null);
    config()->set('passport.public_key', null);
    Passport::loadKeysFrom(base_path('tests/Fixtures/missing-passport-keys'));

    $this->putJson('/settings/mcp', ['enabled' => false])
        ->assertSuccessful()
        ->assertJson([
            'enabled' => false,
            'available' => false,
            'ready' => false,
        ]);
});

it('requires a workspace admin to read or update instance MCP settings', function (string $method) {
    $member = $this->createUser();
    $this->workspace->users()->attach($member, ['role' => 'user']);
    $this->actingAs($member, 'api');

    $this->json($method, '/settings/mcp', ['enabled' => true])->assertForbidden();
})->with(['GET', 'PUT']);

it('hides MCP settings from cloud instances', function (string $method) {
    config()->set('app.self_hosted', false);

    $this->json($method, '/settings/mcp', ['enabled' => true])
        ->assertNotFound()
        ->assertJson([
            'error' => 'Only available on self-hosted instances.',
        ]);
})->with(['GET', 'PUT']);
