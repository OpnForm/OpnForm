<?php

use App\Service\Forms\FormExportService;

it('neutralizes spreadsheet formulas in text while preserving numeric cells', function () {
    $service = app(FormExportService::class);
    $csv = (new ReflectionMethod($service, 'generateCsvContent'))->invoke($service, [['=heading' => '=HYPERLINK("https://evil.test")', 'number' => -5, 'text' => "\t@SUM(1)"]]);
    $stream = fopen('php://temp', 'r+'); fwrite($stream, substr($csv, 3)); rewind($stream);
    $headers = fgetcsv($stream); $row = fgetcsv($stream); fclose($stream);
    expect($headers[0])->toBe("'=heading")->and($row[0])->toStartWith("'=")->and($row[1])->toBe('-5')->and($row[2])->toBe("'\t@SUM(1)");
});
