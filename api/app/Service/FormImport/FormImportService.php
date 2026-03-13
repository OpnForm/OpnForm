<?php

namespace App\Service\FormImport;

class FormImportService
{
    public function __construct(
        private FormImportRegistry $registry,
    ) {
    }

    public function import(string $source, array $importData): array
    {
        $importer = $this->registry->resolve($source);

        if (!$importer->validate($importData)) {
            throw new FormImportException('Invalid import data for source: ' . $source);
        }

        return $importer->import($importData);
    }
}
