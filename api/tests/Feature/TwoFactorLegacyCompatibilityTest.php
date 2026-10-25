<?php

use App\Models\User;
use Illuminate\Encryption\Encrypter;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

it('keeps existing v3 encrypted TOTP and recovery records usable without re-enrollment', function () {
    $fixture = require base_path('tests/fixtures/two-factor-v3.php');
    $user = User::factory()->create();
    $originalEncrypter = app('encrypter');
    $originalTime = Carbon::getTestNow();
    app()->instance('encrypter', new Encrypter($fixture['public_test_key'], 'AES-256-CBC'));
    Carbon::setTestNow($fixture['at']);
    Cache::flush();

    try {
        // Insert raw v3 ciphertext so the installed package must read the old format.
        DB::table('two_factor_authentications')->insert(array_merge($fixture['attributes'], [
            'authenticatable_type' => $user->getMorphClass(),
            'authenticatable_id' => $user->getKey(),
            'created_at' => '2030-01-01 12:00:00',
            'updated_at' => '2030-01-01 12:00:00',
        ]));
        $user = $user->fresh();

        expect($user->hasTwoFactorEnabled())->toBeTrue()
            ->and($user->makeTwoFactorCode($fixture['at']))->toBe($fixture['totp'])
            ->and($user->validateTwoFactorCode($fixture['totp']))->toBeTrue()
            ->and($user->validateTwoFactorCode($fixture['totp']))->toBeFalse()
            ->and($user->validateTwoFactorCode($fixture['recovery_code']))->toBeTrue()
            ->and($user->fresh()->validateTwoFactorCode($fixture['recovery_code']))->toBeFalse()
            ->and($user->fresh()->hasTwoFactorEnabled())->toBeTrue()
            ->and($user->fresh()->twoFactorAuth->getRawOriginal('shared_secret'))->toBe($fixture['attributes']['shared_secret'])
            ->and($user->fresh()->twoFactorAuth->getRawOriginal('enabled_at'))->toBe($fixture['attributes']['enabled_at']);
    } finally {
        app()->instance('encrypter', $originalEncrypter);
        Carbon::setTestNow($originalTime);
    }
});
