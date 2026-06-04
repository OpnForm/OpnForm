<?php

use App\Console\Commands\Tax\GenerateDesXmlExport;
use Illuminate\Support\Facades\Storage;

uses(\Tests\TestCase::class);

it('writes des xml through storage with atomic rename', function () {
    Storage::fake('local');

    $command = app(GenerateDesXmlExport::class);
    $method = new ReflectionMethod(GenerateDesXmlExport::class, 'writeXmlFile');
    $method->setAccessible(true);

    $path = $method->invoke($command, 'DES_202401.xml', '<?xml version="1.0"?><fichier_des/>');

    expect(Storage::disk('local')->exists('DES_202401.xml'))->toBeTrue();
    expect($path)->toEndWith('DES_202401.xml');
    expect(Storage::disk('local')->get('DES_202401.xml'))->toBe('<?xml version="1.0"?><fichier_des/>');
});
