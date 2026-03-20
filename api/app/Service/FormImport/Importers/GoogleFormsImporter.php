<?php

namespace App\Service\FormImport\Importers;

use App\Service\FormImport\FormImportException;

class GoogleFormsImporter extends AbstractImporter
{
    public function validate(array $importData): bool
    {
        if (empty($importData['google_access_token'])) {
            return false;
        }

        $url = $importData['url'] ?? null;

        if ($url && !$this->isDomainAllowed($url)) {
            return false;
        }

        return true;
    }

    public function import(array $importData): array
    {
        // Google Forms OAuth import will be implemented in Phase 5
        throw new FormImportException('Google Forms import is not yet available. Coming soon!');
    }

    public function allowedDomains(): array
    {
        return ['docs.google.com'];
    }
}
