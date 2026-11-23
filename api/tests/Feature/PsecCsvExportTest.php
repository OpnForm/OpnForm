<?php

use App\Service\Forms\FormExportService;

it('neutralizes spreadsheet formulas in text while preserving numeric cells', function () {
    $file = app(FormExportService::class)->writeFile([
        ['=heading' => '=1+1', 'number' => -5, 'text' => "\t@SUM(1)"],
    ], 'csv');
    $stream = $file->readStream();

    try {
        expect(fread($stream, 3))->toBe("\xEF\xBB\xBF");
        $headers = fgetcsv($stream, escape: '');
        $row = fgetcsv($stream, escape: '');

        expect($headers[0])->toBe("'=heading")
            ->and($row[0])->toBe("'=1+1")
            ->and($row[1])->toBe('-5')
            ->and($row[2])->toBe("'\t@SUM(1)");
    } finally {
        fclose($stream);
        $file->delete();
    }
});
