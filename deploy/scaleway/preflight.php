<?php
// Hosted runtime only; fail before consumers start. Never prints variable values.
require '/app/vendor/autoload.php';
$app = require '/app/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$fail = static function (string $message): never {
    fwrite(STDERR, $message."\n");
    exit(78);
};
if (config('app.self_hosted') || config('app.debug')) {
    $fail('Hosted runtime requires SELF_HOSTED=false and APP_DEBUG=false');
}
// Vapor otherwise defaults to trusting every caller, including spoofed X-Forwarded-For.
$proxies = config('trustedproxy.proxies');
$proxies = is_string($proxies) ? array_map('trim', explode(',', $proxies)) : $proxies;
if (! is_array($proxies) || ! $proxies) {
    $fail('Hosted runtime requires an explicit TRUSTED_PROXIES allowlist');
}
foreach ($proxies as $proxy) {
    $parts = explode('/', (string) $proxy);
    $ip = $parts[0];
    $bits = str_contains($ip, ':') ? 128 : 32;
    if (! filter_var($ip, FILTER_VALIDATE_IP) || count($parts) > 2
        || (isset($parts[1]) && (! ctype_digit($parts[1]) || (int) $parts[1] < 8 || (int) $parts[1] > $bits))) {
        $fail('TRUSTED_PROXIES must contain explicit IPs or narrow CIDRs');
    }
}
$key = (string) config('app.key');
if (str_starts_with($key, 'base64:')) {
    $key = base64_decode(substr($key, 7), true) ?: '';
}
if (! Illuminate\Encryption\Encrypter::supported($key, config('app.cipher')) || strlen((string) config('jwt.secret')) < 32) {
    $fail('APP_KEY and a valid JWT signing secret must be supplied; never generate replacements');
}
if (config('queue.default') !== 'redis' || config('database.default') !== 'pgsql') {
    $fail('Hosted runtime requires PostgreSQL and Redis queues');
}
if (config('queue.connections.redis.retry_after') <= 3650) {
    $fail('REDIS_QUEUE_RETRY_AFTER must exceed worker and job shutdown limits (3650 seconds)');
}
$endpoint = static function (array $connection): array {
    $url = parse_url($connection['url'] ?? '') ?: [];
    return [strtolower($url['host'] ?? $connection['host']), (int) ($url['port'] ?? $connection['port'])];
};
$queue = config('database.redis.'.config('queue.connections.redis.connection'));
$cache = config('database.redis.cache');
if (! in_array(config('hosted-state.mode'), ['redis', 'redis-fresh'], true) && $endpoint($queue) === $endpoint($cache)) {
    $fail('Hosted queue and cache require separate Redis endpoints');
}
if (! preg_match('/^[1-9][0-9]?$/', getenv('WORKER_COUNT') ?: '4')) {
    $fail('WORKER_COUNT must be an integer between 1 and 99');
}
if (! preg_match('/^[1-9][0-9]?$/', getenv('WORKER_TRIES') ?: '3')) {
    $fail('WORKER_TRIES must be an integer between 1 and 99');
}
// Validate the selected Redis strategy and its durable logical bindings.
try {
    app(App\Service\Deployment\HostedStateGuard::class)->assertReady();
} catch (RuntimeException $exception) {
    $fail($exception->getMessage());
}
if (config('app.env') === 'preview') {
    if (config('mail.default') !== 'log' || config('telemetry.enabled')) {
        $fail('Preview requires captured mail and disabled telemetry');
    }
    foreach (['cashier.secret', 'services.openai.api_key', 'services.google.client_secret',
              'services.stripe.client_secret', 'services.telegram.bot_token', 'sentry.dsn'] as $key) {
        if (config($key)) {
            $fail('Preview requires third-party credentials to be removed');
        }
    }
    // These checks supplement, never replace, host/container/browser network isolation.
}
