<?php

namespace App\Service\FormImport;

use InvalidArgumentException;

class FormImportRegistry
{
    private array $importers = [
        'typeform' => ['class' => Importers\TypeformImporter::class, 'label' => 'Typeform'],
        'tally' => ['class' => Importers\TallyImporter::class, 'label' => 'Tally'],
        'fillout' => ['class' => Importers\FilloutImporter::class, 'label' => 'Fillout'],
        'google_forms' => ['class' => Importers\GoogleFormsImporter::class, 'label' => 'Google Forms'],
    ];

    public function resolve(string $source): FormImporterInterface
    {
        $entry = $this->importers[$source] ?? null;

        if (!$entry) {
            throw new InvalidArgumentException("Unknown import source: " . $this->label($source));
        }

        return app($entry['class']);
    }

    public function label(string $source): string
    {
        return $this->importers[$source]['label'] ?? $source;
    }

    public function sources(): array
    {
        return array_keys($this->importers);
    }
}
