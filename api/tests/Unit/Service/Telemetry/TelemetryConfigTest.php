<?php

use Illuminate\Support\Env;
use Tests\TestCase;

uses(TestCase::class);

it('resolves the telemetry opt-out with new-name precedence and legacy fallback', function ($current, $legacy, $enabled) {
    $repository = Env::getRepository();
    $values = [
        'OPNFORM_TELEMETRY_DISABLED' => $current,
        'OPNFORM_ANONYMOUS_TELEMETRY_DISABLED' => $legacy,
    ];
    $original = [];

    try {
        foreach ($values as $name => $value) {
            $original[$name] = $repository->get($name);
            $repository->clear($name);
            if ($value !== null) {
                $repository->set($name, $value);
            }
        }

        $config = require config_path('telemetry.php');

        expect($config['enabled'])->toBe($enabled);
    } finally {
        foreach ($original as $name => $value) {
            $repository->clear($name);
            if ($value !== null) {
                $repository->set($name, $value);
            }
        }
    }
})->with([
    'unchanged default' => [null, null, true],
    'legacy opt-out' => [null, 'true', false],
    'legacy opt-in' => [null, 'false', true],
    'new opt-out' => ['true', null, false],
    'new opt-in' => ['false', null, true],
    'new true overrides legacy false' => ['true', 'false', false],
    'new false overrides legacy true' => ['false', 'true', true],
    'both disabled' => ['true', 'true', false],
    'both enabled' => ['false', 'false', true],
]);
