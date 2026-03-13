<?php

namespace App\Service\FormImport;

use InvalidArgumentException;

class FormImportRegistry
{
    private array $importers = [
        'typeform' => Importers\TypeformImporter::class,
        'tally' => Importers\TallyImporter::class,
        'fillout' => Importers\FilloutImporter::class,
        'google_forms' => Importers\GoogleFormsImporter::class,
    ];

    public function resolve(string $source): FormImporterInterface
    {
        $class = $this->importers[$source] ?? null;

        if (!$class) {
            throw new InvalidArgumentException("Unknown import source: {$source}");
        }

        return app($class);
    }

    public function sources(): array
    {
        return array_keys($this->importers);
    }
}
