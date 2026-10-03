<?php

use Illuminate\Support\Facades\File;
use Laravel\Passport\Passport;

function temporaryPassportKeyPair(): array
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
    $this->originalPassportKeyPath = Passport::$keyPath;
    $this->emptyPassportKeyPath = storage_path('framework/testing/passport-keys-'.uniqid());
    File::ensureDirectoryExists($this->emptyPassportKeyPath);
    Passport::loadKeysFrom($this->emptyPassportKeyPath);
    config()->set('passport.private_key');
    config()->set('passport.public_key');
});

afterEach(function () {
    Passport::$keyPath = $this->originalPassportKeyPath;
    File::deleteDirectory($this->emptyPassportKeyPath);
});

it('accepts a valid stable Passport key pair from the environment', function () {
    [$privateKey, $publicKey] = temporaryPassportKeyPair();
    config()->set('passport.private_key', str_replace("\n", '\\n', $privateKey));
    config()->set('passport.public_key', str_replace("\n", '\\n', $publicKey));

    $this->artisan('mcp:check-oauth-keys --environment-only')
        ->expectsOutputToContain('configured and valid')
        ->assertSuccessful();
});

it('fails when Passport keys are missing', function () {
    $this->artisan('mcp:check-oauth-keys --environment-only')
        ->expectsOutputToContain('requires both')
        ->assertFailed();
});

it('fails when only one Passport environment key is configured', function () {
    [$privateKey] = temporaryPassportKeyPair();
    config()->set('passport.private_key', $privateKey);

    $this->artisan('mcp:check-oauth-keys --environment-only')
        ->expectsOutputToContain('requires both')
        ->assertFailed();
});

it('fails when Passport keys do not belong to the same pair', function () {
    [$privateKey] = temporaryPassportKeyPair();
    [, $publicKey] = temporaryPassportKeyPair();
    config()->set('passport.private_key', $privateKey);
    config()->set('passport.public_key', $publicKey);

    $this->artisan('mcp:check-oauth-keys --environment-only')
        ->expectsOutputToContain('do not belong to the same pair')
        ->assertFailed();
});

it('checks OAuth keys before running Vapor migrations', function () {
    $vaporConfig = file_get_contents(base_path('vapor.yml'));
    $checkBeforeMigrate = '/deploy:\s*\n\s*- "php artisan mcp:check-oauth-keys --environment-only"\s*\n\s*- "php artisan migrate --force"/';

    expect(preg_match_all($checkBeforeMigrate, $vaporConfig))->toBe(2);
});
