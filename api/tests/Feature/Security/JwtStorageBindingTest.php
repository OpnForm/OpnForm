<?php

use App\Providers\AppServiceProvider;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Facades\Cache;
use Tymon\JWTAuth\Providers\Storage\Illuminate as JwtStorage;

it('reads existing auth-store revocations with the stock JWT provider without changing the default cache', function () {
    config([
        'cache.stores.auth' => ['driver' => 'array', 'serialize' => true],
        'jwt.blacklist_store' => 'auth',
    ]);
    (new AppServiceProvider(app()))->register();
    app()->forgetInstance('tymon.jwt.provider.storage');

    // The removed adapter passed this exact repository to the same stock provider.
    $legacy = new JwtStorage(Cache::store('auth'));
    $value = ['valid_until' => time() + 120];
    $legacy->forever('existing-revocation', $value);
    $provider = app('tymon.jwt.provider.storage');

    expect(config('jwt.providers.storage'))->toBe(JwtStorage::class)
        ->and(get_class($provider))->toBe(JwtStorage::class)
        ->and($provider->get('existing-revocation'))->toBe($value)
        ->and(Cache::tags('tymon.jwt')->get('existing-revocation'))->toBeNull()
        ->and(app(Repository::class))->toBe(Cache::store());

    $provider->forever('new-revocation', $value);
    expect($legacy->get('new-revocation'))->toBe($value)
        ->and(Cache::tags('tymon.jwt')->get('new-revocation'))->toBeNull();
});

it('keeps the package default cache when no JWT store is selected', function () {
    config(['jwt.blacklist_store' => null]);
    (new AppServiceProvider(app()))->register();
    app()->forgetInstance('tymon.jwt.provider.storage');

    $provider = app('tymon.jwt.provider.storage');
    $provider->forever('default-revocation', ['valid_until' => time() + 120]);

    expect(get_class($provider))->toBe(JwtStorage::class)
        ->and($provider->get('default-revocation'))->toBe(Cache::tags('tymon.jwt')->get('default-revocation'))
        ->and(Cache::tags('tymon.jwt')->get('default-revocation'))->not->toBeNull();
});
