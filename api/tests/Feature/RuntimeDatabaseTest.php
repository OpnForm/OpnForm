<?php

use Illuminate\Support\Facades\DB;

it('runs the general suite against the protected SQLite runtime', function () {
    expect(app()->environment())->toBe('testing')
        ->and(DB::connection()->getPdo()->getAttribute(PDO::ATTR_DRIVER_NAME))->toBe('sqlite');
});
