<?php

use App\Exports\FormSubmissionExport;
use App\Exports\FormSubmissionXlsxExport;
use App\Exports\Tax\ArrayExport;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Excel;
use Maatwebsite\Excel\Files\TemporaryFileFactory;
use PhpOffice\PhpSpreadsheet\IOFactory;

it('round trips a real tax spreadsheet without losing identifiers, amounts, or headings', function () {
    Storage::fake('local');
    $export = new ArrayExport([
        ['Account' => '00123', 'Amount' => 0, 'Note' => 'Été 東京', 'Optional' => null, 'Empty' => '', 'Flag' => false],
        ['Amount' => 25.5, 'Account' => '00999', 'Note' => 'Second'],
    ]);

    expect($export->store('tax.xlsx', 'local', Excel::XLSX))->toBeTrue();

    $spreadsheet = IOFactory::load(Storage::disk('local')->path('tax.xlsx'));
    try {
        $rows = $spreadsheet->getActiveSheet()->toArray(null, true, false);
        expect($rows[0])->toBe(['Account', 'Amount', 'Note', 'Optional', 'Empty', 'Flag'])
            ->and($rows[1][0])->toBe('00123')
            ->and($rows[2][0])->toBe('00999')
            ->and($rows[1][1])->not->toBeNull()
            ->and(is_numeric($rows[1][1]))->toBeTrue()
            ->and((float) $rows[1][1])->toBe(0.0)
            ->and((float) $rows[2][1])->toBe(25.5)
            ->and($rows[1][2])->toBe('Été 東京')
            ->and($rows[1][3])->toBeNull()
            ->and($rows[1][4])->toBeIn([null, ''])
            ->and($rows[1][5])->toBeFalse();
    } finally {
        $spreadsheet->disconnectWorksheets();
    }
});

it('writes submission CSV with cleaned headings and quoted Unicode values', function () {
    $file = (new FormSubmissionExport())->write([
        ['Name (title)' => 'Été, "Tokyo"', 'Count (number)' => 0, 'Optional' => null, 'Empty' => ''],
    ]);
    $stream = fopen('php://temp', 'w+');
    try {
        fwrite($stream, preg_replace('/^\xEF\xBB\xBF/', '', $file->contents()));
        rewind($stream);
        expect(fgetcsv($stream))->toBe(['Name', 'Count', 'Optional', 'Empty'])
            ->and(fgetcsv($stream))->toBe(['Été, "Tokyo"', '0', '', '']);
    } finally {
        fclose($stream);
        $file->delete();
    }
});

it('keeps Excel scratch files private and removes them after writing', function (bool $fails) {
    $directory = sys_get_temp_dir().'/submission-export-'.Str::uuid();
    $this->app->instance(TemporaryFileFactory::class, new TemporaryFileFactory($directory));
    $export = new FormSubmissionXlsxExport();
    $rows = function () use ($directory, $fails) {
        $workbooks = glob($directory.'/*.xlsx');
        $scratch = glob($directory.'/*.xlsx-scratch');
        expect($workbooks)->toHaveCount(1)
            ->and($scratch)->toHaveCount(1)
            ->and(fileperms($workbooks[0]) & 0777)->toBe(0600)
            ->and(fileperms($scratch[0]) & 0777)->toBe(0700);

        yield ['Name' => 'Sample answer'];

        if ($fails) {
            throw new RuntimeException('Simulated row read failure');
        }
    };

    try {
        if ($fails) {
            expect(fn () => $export->write($rows()))->toThrow(RuntimeException::class, 'Simulated row read failure');
            expect(glob($directory.'/*.xlsx'))->toBeEmpty();
        } else {
            $file = $export->write($rows());
            expect($file->exists())->toBeTrue();
        }
        expect(glob($directory.'/*.xlsx-scratch'))->toBeEmpty();
    } finally {
        File::deleteDirectory($directory);
    }
})->with(['success' => false, 'failure' => true]);
