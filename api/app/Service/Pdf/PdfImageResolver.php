<?php

namespace App\Service\Pdf;

use App\Http\Controllers\Forms\FormController;
use App\Models\Forms\Form;
use App\Service\Storage\FilenameUrlEncoder;
use App\Service\Storage\FileUploadPathService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use League\Flysystem\UnableToCheckFileExistence;

class PdfImageResolver
{
    private array $resolvedContentCache = [];
    private ?array $allowedAssetHosts = null;

    public function __construct(
        private readonly ?Form $form = null,
        private readonly ?PdfSafeImageFetcher $remoteFetcher = null,
    ) {
    }

    /**
     * Resolve a zone image value to binary content from storage or a safe remote URL.
     */
    public function resolveContent(string $imageValue): ?string
    {
        $normalized = trim($imageValue);
        if ($normalized === '') {
            return null;
        }

        if (array_key_exists($normalized, $this->resolvedContentCache)) {
            return $this->resolvedContentCache[$normalized];
        }

        $content = $this->resolveUncachedContent($normalized);
        $this->resolvedContentCache[$normalized] = $content;

        return $content;
    }

    private function resolveUncachedContent(string $normalized): ?string
    {
        try {
            foreach ($this->candidatePaths($normalized) as $path) {
                $content = $this->readFromStorage($path);
                if ($content !== null) {
                    return $content;
                }
            }

            if ($this->isUrl($normalized)) {
                if (!$this->shouldRemoteFetch($normalized)) {
                    return null;
                }

                return $this->remoteFetcher()->fetch($normalized);
            }
        } catch (\Throwable $e) {
            Log::debug('PDF image resolve failed', [
                'form_id' => $this->form?->id,
                'value' => $normalized,
                'error' => $e->getMessage(),
            ]);
        }

        return null;
    }

    private function candidatePaths(string $imageValue): array
    {
        if ($this->isUrl($imageValue)) {
            if (!$this->hasAssetPath($imageValue) || !$this->matchesAppAssetOrigin($imageValue)) {
                return [];
            }
        }

        $candidates = [];
        $restrictToFormAssets = $this->isUrl($imageValue);

        foreach ($this->extractFileNames($imageValue) as $fileName) {
            if (!$restrictToFormAssets && $this->form) {
                $candidates[] = FileUploadPathService::getFileUploadPath($this->form->id, $fileName);
            }

            $candidates[] = FormController::ASSETS_UPLOAD_PATH . '/' . $fileName;
        }

        return array_values(array_unique($candidates));
    }

    /**
     * @return array<int, string>
     */
    private function extractFileNames(string $value): array
    {
        $fileNames = [];

        if ($this->isUrl($value)) {
            $path = parse_url($value, PHP_URL_PATH);
            if (is_string($path) && $path !== '') {
                if (preg_match('#/forms/assets/([^/]+)$#', $path, $matches) === 1) {
                    $fileNames[] = $this->sanitizeFileName(rawurldecode($matches[1]));
                }

                if ($this->hasAssetPath($value)) {
                    $fileNames[] = $this->sanitizeFileName(rawurldecode(basename($path)));
                }
            }
        } else {
            $fileNames[] = $this->sanitizeFileName(rawurldecode(basename($value)));
        }

        $resolved = [];
        foreach ($fileNames as $fileName) {
            if ($fileName === null) {
                continue;
            }

            $resolved[] = $fileName;

            if (FilenameUrlEncoder::isEncoded($fileName)) {
                $sanitized = $this->sanitizeFileName(FilenameUrlEncoder::decode($fileName));
                if ($sanitized !== null) {
                    $resolved[] = $sanitized;
                }
            }
        }

        return array_values(array_unique($resolved));
    }

    private function readFromStorage(string $path): ?string
    {
        try {
            if (!Storage::exists($path)) {
                return null;
            }

            return Storage::get($path);
        } catch (UnableToCheckFileExistence $e) {
            Log::debug('PDF image storage lookup skipped', [
                'form_id' => $this->form?->id,
                'path' => $path,
                'error' => $e->getMessage(),
            ]);

            return null;
        } catch (\Throwable $e) {
            Log::debug('PDF image storage lookup failed', [
                'form_id' => $this->form?->id,
                'path' => $path,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    private function remoteFetcher(): PdfSafeImageFetcher
    {
        return $this->remoteFetcher ?? new PdfSafeImageFetcher();
    }

    private function isUrl(string $value): bool
    {
        return filter_var($value, FILTER_VALIDATE_URL) !== false;
    }

    private function shouldRemoteFetch(string $url): bool
    {
        return !$this->matchesAppAssetOrigin($url);
    }

    private function hasAssetPath(string $url): bool
    {
        $path = parse_url($url, PHP_URL_PATH);

        return is_string($path) && preg_match('#/forms/assets/([^/]+)$#', $path) === 1;
    }

    private function matchesAppAssetOrigin(string $url): bool
    {
        $host = $this->normalizedHostFromUrl($url);
        if ($host === null) {
            return false;
        }

        return in_array($host, $this->allowedAssetHosts(), true);
    }

    /**
     * @return array<int, string>
     */
    private function allowedAssetHosts(): array
    {
        if ($this->allowedAssetHosts !== null) {
            return $this->allowedAssetHosts;
        }

        $hosts = [];

        foreach ([config('app.url'), config('app.front_url')] as $baseUrl) {
            if (!is_string($baseUrl) || $baseUrl === '') {
                continue;
            }

            $host = $this->normalizedHostFromUrl($baseUrl);
            if ($host !== null) {
                $hosts[] = $host;
            }
        }

        $routeHost = $this->normalizedHostFromUrl(route('forms.assets.show', ['__probe__']));
        if ($routeHost !== null) {
            $hosts[] = $routeHost;
        }

        $this->allowedAssetHosts = array_values(array_unique($hosts));

        return $this->allowedAssetHosts;
    }

    private function normalizedHostFromUrl(string $url): ?string
    {
        $host = parse_url($url, PHP_URL_HOST);

        if (!is_string($host) || $host === '') {
            return null;
        }

        return $this->normalizeHost($host);
    }

    private function normalizeHost(string $host): string
    {
        $host = strtolower($host);

        if (in_array($host, ['127.0.0.1', '::1'], true)) {
            return 'localhost';
        }

        if (str_starts_with($host, 'www.')) {
            return substr($host, 4);
        }

        return $host;
    }

    private function sanitizeFileName(?string $fileName): ?string
    {
        if ($fileName === null) {
            return null;
        }

        $fileName = trim($fileName);
        if ($fileName === '' || $fileName === '.' || $fileName === '..') {
            return null;
        }

        if (
            str_contains($fileName, '/')
            || str_contains($fileName, '\\')
            || str_contains($fileName, '..')
        ) {
            return null;
        }

        return $fileName;
    }
}
