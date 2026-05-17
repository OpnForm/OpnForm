<?php

namespace App\Exports\Tax;

use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;

class ArrayExport implements FromArray, WithHeadings
{
    use Exportable;

    private array $headingsList;

    public function __construct(public array $data)
    {
        $this->headingsList = [];

        foreach ($this->data as $row) {
            foreach (array_keys($row) as $key) {
                if (!in_array($key, $this->headingsList, true)) {
                    $this->headingsList[] = $key;
                }
            }
        }
    }

    public function array(): array
    {
        return array_map(function (array $row) {
            $normalized = [];
            foreach ($this->headingsList as $heading) {
                $normalized[$heading] = $row[$heading] ?? null;
            }

            return $normalized;
        }, $this->data);
    }

    public function headings(): array
    {
        return $this->headingsList;
    }
}
