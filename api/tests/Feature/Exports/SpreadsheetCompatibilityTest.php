<?php

use App\Exports\FormSubmissionExport;
use App\Exports\Tax\ArrayExport;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Excel;
use Maatwebsite\Excel\Facades\Excel as ExcelFacade;
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
    $csv = ExcelFacade::raw(new FormSubmissionExport([
        ['Name (title)' => 'Été, "Tokyo"', 'Count (number)' => 0, 'Optional' => null, 'Empty' => ''],
    ]), Excel::CSV);
    $stream = fopen('php://temp', 'w+');
    try {
        fwrite($stream, preg_replace('/^\xEF\xBB\xBF/', '', $csv));
        rewind($stream);
        expect(fgetcsv($stream))->toBe(['Name', 'Count', 'Optional', 'Empty'])
            ->and(fgetcsv($stream))->toBe(['Été, "Tokyo"', '0', '', '']);
    } finally {
        fclose($stream);
    }
});
