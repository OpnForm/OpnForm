<?php

use App\Enums\SettingsKey;
use App\Models\Setting;
use Tests\TestCase;

uses(TestCase::class);

describe('Setting', function () {
    beforeEach(function () {
        // Clean up before each test
        Setting::whereIn('key', [
            SettingsKey::INSTANCE_ID->value,
            SettingsKey::INSTANCE_CREATED_AT->value,
        ])->delete();
    });

    describe('get', function () {
        it('returns null when key does not exist', function () {
            expect(Setting::get(SettingsKey::INSTANCE_ID))->toBeNull();
        });

        it('returns value when key exists', function () {
            $value = 'test-value';
            Setting::set(SettingsKey::INSTANCE_ID, $value);

            expect(Setting::get(SettingsKey::INSTANCE_ID))->toBe($value);
        });

        it('handles string values', function () {
            $value = 'test-uuid-string';
            Setting::set(SettingsKey::INSTANCE_ID, $value);

            expect(Setting::get(SettingsKey::INSTANCE_ID))->toBe($value);
        });

        it('handles array values', function () {
            $value = ['key1' => 'value1', 'key2' => 'value2'];
            Setting::set(SettingsKey::INSTANCE_ID, $value);

            expect(Setting::get(SettingsKey::INSTANCE_ID))->toBe($value);
        });
    });

    describe('set', function () {
        it('creates new setting when key does not exist', function () {
            Setting::set(SettingsKey::INSTANCE_ID, 'new-value');

            expect(Setting::get(SettingsKey::INSTANCE_ID))->toBe('new-value');
        });

        it('updates existing setting when key exists', function () {
            Setting::set(SettingsKey::INSTANCE_ID, 'initial-value');
            Setting::set(SettingsKey::INSTANCE_ID, 'updated-value');

            expect(Setting::get(SettingsKey::INSTANCE_ID))->toBe('updated-value');
        });

        it('handles different value types', function () {
            Setting::set(SettingsKey::INSTANCE_ID, 'string-value');
            expect(Setting::get(SettingsKey::INSTANCE_ID))->toBe('string-value');

            Setting::set(SettingsKey::INSTANCE_ID, ['array' => 'value']);
            expect(Setting::get(SettingsKey::INSTANCE_ID))->toBe(['array' => 'value']);

            Setting::set(SettingsKey::INSTANCE_ID, 123);
            expect(Setting::get(SettingsKey::INSTANCE_ID))->toBe(123);
        });
    });
});
