<?php

namespace App\Service\FormImport;

interface FormImporterInterface
{
    /**
     * Parse import data and return an array representing the form.
     *
     * @param  array  $importData  ['url' => '...'] or ['google_access_token' => '...', 'url' => '...']
     * @return array  ['title' => string, 'properties' => array]
     *
     * @throws FormImportException
     */
    public function import(array $importData): array;

    /**
     * Validate that the provided import data is acceptable.
     */
    public function validate(array $importData): bool;

    /**
     * Return the list of allowed URL domains for this source.
     *
     * @return array<string>
     */
    public function allowedDomains(): array;
}
