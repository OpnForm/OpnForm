<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;

class FormSubmissionExport implements FromArray, WithHeadings
{
    protected array $submissionData;
    protected array $headings;

    public function __construct(array $submissionData)
    {
        $this->headings = empty($submissionData)
            ? []
            : $this->cleanColumnNames(array_keys($submissionData[0]));

        $this->submissionData = array_map(function ($row) {
            return array_values($row);
        }, $submissionData);
    }

    private function cleanColumnNames(array $columnNames): array
    {
        return collect($columnNames)->map(function ($columnName) {
            return preg_replace('/\s\(.*\)/', '', $columnName);
        })->toArray();
    }

    public function array(): array
    {
        return $this->submissionData;
    }

    public function headings(): array
    {
        return $this->headings;
    }
}
