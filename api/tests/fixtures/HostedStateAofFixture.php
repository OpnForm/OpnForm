<?php

// Fictional local-only daemon restart fixture; never used by deployment entrypoints.
require dirname(__DIR__, 2).'/vendor/autoload.php';
require __DIR__.'/HostedStateBootstrap.php';
bootHostedStateFixture(false);
$state = app('redis')->connection('state');
$store = Illuminate\Support\Facades\Cache::store('redis');
$authStore = Illuminate\Support\Facades\Cache::store('auth');
$auth = app('redis')->connection('auth');
$path = '/fixture-state/fictional-aof-witness.json';
if (($argv[1] ?? '') === 'write') {
    $user = new App\Models\User();
    $user->id = 42;
    $token = app('tymon.jwt')->fromSubject($user);
    $payload = app('tymon.jwt')->setToken($token)->getPayload();
    app('tymon.jwt')->invalidate();
    $session = (new Illuminate\Session\SessionManager(app()))->driver();
    $session->setId(str_repeat('d', 40));
    $session->start();
    $session->put('fictional-user', 42);
    $session->save();
    $store->put('aof-once', 'consumed', 120);
    $store->pull('aof-once');
    $store->put('aof-quota', 3, 120);
    $store->increment('aof-quota');
    $jwtKey = $authStore->getStore()->getPrefix().$authStore->tags('tymon.jwt')->taggedItemKey($payload->get('jti'));
    $sessionKey = $authStore->getStore()->getPrefix().$session->getId();
    file_put_contents($path, json_encode([
        'token' => $token, 'session' => $session->getId(), 'jwt_key' => $jwtKey,
        'session_key' => $sessionKey, 'run_id' => $state->info('server')['run_id'],
        'jwt_expires_at_ms' => $auth->pexpiretime($jwtKey),
        'session_expires_at_ms' => $auth->pexpiretime($sessionKey),
    ], JSON_THROW_ON_ERROR));
    echo "Fictional state acknowledged with AOF/always\n";
    exit(0);
}
$witness = json_decode(file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
$blocked = false;
try {
    app('tymon.jwt')->setToken($witness['token'])->getPayload();
} catch (Tymon\JWTAuth\Exceptions\TokenBlacklistedException) {
    $blocked = true;
}
$session = (new Illuminate\Session\SessionManager(app()))->driver();
$session->setId($witness['session']);
$session->start();
$checks = [
    'new_daemon_run_id' => $state->info('server')['run_id'] !== $witness['run_id'],
    'signed_jwt_still_blacklisted' => $blocked,
    'actual_session_retained' => $session->get('fictional-user') === 42,
    'consumed_context_absent' => $store->get('aof-once') === null,
    'quota_increment_retained' => (int) $store->get('aof-quota') === 4,
    'jwt_absolute_expiration_retained' => $auth->pexpiretime($witness['jwt_key']) === $witness['jwt_expires_at_ms'],
    'session_absolute_expiration_retained' => $auth->pexpiretime($witness['session_key']) === $witness['session_expires_at_ms'],
];
unlink($path);
echo json_encode($checks, JSON_THROW_ON_ERROR).PHP_EOL;
exit(in_array(false, $checks, true) ? 78 : 0);
