<?php

namespace App\Exports;

use Illuminate\Support\Facades\File;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Files\LocalTemporaryFile;
use Maatwebsite\Excel\Files\TemporaryFileFactory;
use OpenSpout\Common\Entity\Cell;
use OpenSpout\Common\Entity\Cell\StringCell;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Options;
use OpenSpout\Writer\XLSX\Writer;
use RuntimeException;
use Throwable;

class FormSubmissionXlsxExport
{
    public const CONTENT_TYPE = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';

    public function write(iterable $rows): LocalTemporaryFile
    {
        $file = app(TemporaryFileFactory::class)->makeLocal(fileExtension: 'xlsx');
        $scratch = $file->getLocalPath() . '-scratch';

        try {
            if (!chmod($file->getLocalPath(), 0600) || !mkdir($scratch, 0700)) {
                throw new RuntimeException('Unable to prepare private Excel export storage');
            }

            try {
                $this->writeWorkbook($file->getLocalPath(), $scratch, $rows);
            } finally {
                File::deleteDirectory($scratch);
            }
        } catch (Throwable $exception) {
            $file->delete();

            throw $exception;
        }

        return $file;
    }

    private function writeWorkbook(string $path, string $scratch, iterable $rows): void
    {
        $options = new Options();
        $options->setTempFolder($scratch);
        $writer = new Writer($options);
        $writer->openToFile($path);

        try {
            $columns = null;
            foreach ($rows as $row) {
                if ($columns === null) {
                    $columns = array_keys($row);

                    if (count($columns) > 16_384) {
                        throw ValidationException::withMessages([
                            'format' => 'This export has more columns than Excel can hold. Choose CSV to export all columns.',
                        ]);
                    }

                    $writer->addRow($this->makeRow(FormSubmissionExport::cleanColumnNames($columns)));
                }

                $values = array_map(fn ($column) => $row[$column] ?? null, $columns);
                $writer->addRow($this->makeRow($values));
            }
        } finally {
            $writer->close();
        }
    }

    private function makeRow(array $values): Row
    {
        $cells = [];
        foreach ($values as $value) {
            if (!is_string($value) || $value === '') {
                $cells[] = Cell::fromValue($value);

                continue;
            }

            if (mb_strlen($value, 'UTF-8') > 32_767) {
                throw ValidationException::withMessages([
                    'format' => 'A cell exceeds Excel’s 32,767 character limit. Choose CSV to export the full text.',
                ]);
            }

            if (preg_match('/[\x{FFFE}\x{FFFF}]/u', $value)) {
                throw ValidationException::withMessages([
                    'format' => 'A cell contains a character that Excel cannot store. Choose CSV to preserve the original text.',
                ]);
            }

            $cells[] = new StringCell($value, null);
        }

        return new Row($cells);
    }
}
