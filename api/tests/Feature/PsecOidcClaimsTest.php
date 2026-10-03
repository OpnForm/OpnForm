<?php

use App\Enterprise\Oidc\IdTokenVerifier;
use App\Enterprise\Oidc\Models\IdentityConnection;
use App\Enterprise\Oidc\DomainValidator;
use Firebase\JWT\JWT;

beforeEach(function () {
    $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
    openssl_pkey_export($key, $this->privateKey);
    $details = openssl_pkey_get_details($key);
    $jwks = ['keys' => [['kid' => 'test', 'kty' => 'RSA', 'alg' => 'RS256', 'n' => JWT::urlsafeB64Encode($details['rsa']['n']), 'e' => JWT::urlsafeB64Encode($details['rsa']['e'])]]];
    $this->verifier = new class($jwks) extends IdTokenVerifier {
        public function __construct(private array $keys) {}
        protected function getJwks(IdentityConnection $connection): array { return $this->keys; }
    };
    $this->connection = new IdentityConnection(['issuer' => 'https://idp.example.test', 'client_id' => 'our-client']);
    $this->claims = ['sub' => 'user', 'iss' => $this->connection->issuer, 'aud' => 'our-client', 'exp' => time() + 300];
});

it('accepts a genuinely signed token for our issuer and client', function () {
    $this->verifier->verifySignature($this->connection, JWT::encode($this->claims, $this->privateKey, 'RS256', 'test'));
    expect(true)->toBeTrue();
});

it('rejects valid signatures with invalid identity claims', function (array $override, array $remove) {
    $claims = array_diff_key(array_merge($this->claims, $override), array_flip($remove));
    expect(fn () => $this->verifier->verifySignature($this->connection, JWT::encode($claims, $this->privateKey, 'RS256', 'test')))->toThrow(Exception::class);
})->with([
    [['iss' => 'https://other.test'], []], [['aud' => 'other-client'], []], [[], ['exp']], [[], ['iss']],
    [['aud' => ['our-client', 'other-client']], []], [['azp' => 'other-client'], []],
]);

it('separates multi-label and private suffix tenants', function () {
    $validator = new DomainValidator();
    expect($validator->matchesUserDomain('attacker.co.uk', 'user@victim.co.uk'))->toBeFalse()
        ->and($validator->matchesUserDomain('a.github.io', 'user@b.github.io'))->toBeFalse()
        ->and($validator->matchesUserDomain('victim.co.uk', 'user@mail.victim.co.uk'))->toBeTrue();
});
