<?php

namespace App\Exports;

use Maatwebsite\Excel\Files\LocalTemporaryFile;
use Maatwebsite\Excel\Files\TemporaryFileFactory;
use RuntimeException;
use Throwable;

class FormSubmissionExport
{
    public const CONTENT_TYPE = 'text/csv; charset=UTF-8';

    public function write(iterable $rows): LocalTemporaryFile
    {
        $file = app(TemporaryFileFactory::class)->makeLocal(fileExtension: 'csv');

        try {
            if (!chmod($file->getLocalPath(), 0600)) {
                throw new RuntimeException('Unable to prepare private CSV export storage');
            }

            $stream = $file->readStream();

            try {
                if (fwrite($stream, "\xEF\xBB\xBF") !== 3) {
                    throw new RuntimeException('Unable to write CSV export');
                }

                $columns = null;
                foreach ($rows as $row) {
                    if ($columns === null) {
                        $columns = array_keys($row);
                        $this->writeRow($stream, self::cleanColumnNames($columns));
                    }

                    $this->writeRow($stream, array_map(fn ($column) => $row[$column] ?? null, $columns));
                }
            } finally {
                fclose($stream);
            }
        } catch (Throwable $exception) {
            $file->delete();

            throw $exception;
        }

        return $file;
    }

    public static function cleanColumnNames(array $columnNames): array
    {
        return array_map(fn ($name) => preg_replace('/\s\(.*\)/', '', $name), $columnNames);
    }

    private function writeRow($stream, array $values): void
    {
        $safeValues = array_map(function ($value) {
            if (is_string($value) && !is_numeric($value) && preg_match('/^[\s\x00-\x1f]*[=+\-@]/u', $value)) {
                return "'" . $value;
            }

            return is_bool($value) ? (int) $value : $value;
        }, $values);

        if (fputcsv($stream, $safeValues, escape: '') === false) {
            throw new RuntimeException('Unable to write CSV export');
        }
    }
}
