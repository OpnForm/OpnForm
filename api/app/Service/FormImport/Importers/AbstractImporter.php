<?php

namespace App\Service\FormImport\Importers;

use App\Service\FormImport\FormImportException;
use App\Service\FormImport\FormImporterInterface;
use Illuminate\Support\Facades\Http;

abstract class AbstractImporter implements FormImporterInterface
{
    public function validate(array $importData): bool
    {
        $url = $importData['url'] ?? null;

        if (!$url || !filter_var($url, FILTER_VALIDATE_URL)) {
            return false;
        }

        return $this->isDomainAllowed($url);
    }

    protected function isDomainAllowed(string $url): bool
    {
        $host = parse_url($url, PHP_URL_HOST);

        if (!$host) {
            return false;
        }

        foreach ($this->allowedDomains() as $domain) {
            if (str_starts_with($domain, '*.')) {
                $suffix = substr($domain, 1); // e.g. ".typeform.com"
                if (str_ends_with($host, $suffix) || $host === substr($domain, 2)) {
                    return true;
                }
            } elseif ($host === $domain) {
                return true;
            }
        }

        return false;
    }

    protected function fetchHtml(string $url): string
    {
        $response = Http::timeout(15)
            ->maxRedirects(3)
            ->withOptions(['verify' => true])
            ->withHeaders([
                'User-Agent' => 'Mozilla/5.0 (compatible; OpnFormImporter/1.0)',
                'Accept' => 'text/html,application/xhtml+xml',
            ])
            ->get($url);

        if (!$response->successful()) {
            throw new FormImportException(
                'Failed to fetch form page. HTTP status: ' . $response->status()
            );
        }

        $body = $response->body();

        if (strlen($body) > 5 * 1024 * 1024) {
            throw new FormImportException('Response too large (> 5 MB).');
        }

        return $body;
    }

    protected function extractNextData(string $html): array
    {
        $pattern = '/<script\s+id="__NEXT_DATA__"\s+type="application\/json"[^>]*>(.*?)<\/script>/si';

        if (!preg_match($pattern, $html, $matches)) {
            throw new FormImportException(
                'Could not find form data in the page. Make sure the form URL is public and accessible.'
            );
        }

        $data = json_decode($matches[1], true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new FormImportException('Failed to parse form data from page.');
        }

        return $data;
    }

    protected function sanitizeText(?string $text, int $maxLength = 255): string
    {
        if ($text === null) {
            return '';
        }

        return mb_substr(strip_tags(trim($text)), 0, $maxLength);
    }

    protected function generateFieldId(): string
    {
        return (string) \Illuminate\Support\Str::uuid();
    }
}
