<?php

namespace App\Service\Pdf;

use App\Http\Controllers\Forms\FormController;
use App\Models\Forms\Form;
use App\Service\Storage\FileUploadPathService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class PdfImageResolver
{
    public function __construct(
        private readonly ?Form $form = null
    ) {
    }

    /**
     * Resolve a zone image value to binary content from storage only.
     * External URLs are explicitly unsupported.
     */
    public function resolveContent(string $imageValue): ?string
    {
        try {
            foreach ($this->candidatePaths($imageValue) as $path) {
                if (Storage::exists($path)) {
                    return Storage::get($path);
                }
            }
        } catch (\Throwable $e) {
            Log::debug('PDF image resolve failed', [
                'form_id' => $this->form?->id,
                'value' => $imageValue,
                'error' => $e->getMessage(),
            ]);
        }

        return null;
    }

    private function candidatePaths(string $imageValue): array
    {
        $candidates = [];
        $normalized = trim($imageValue);

        if ($normalized === '') {
            return $candidates;
        }

        if (filter_var($normalized, FILTER_VALIDATE_URL) !== false) {
            $path = parse_url($normalized, PHP_URL_PATH);
            $fileName = $path ? basename($path) : null;
            if ($fileName) {
                $candidates[] = FormController::ASSETS_UPLOAD_PATH . '/' . $fileName;
                if ($this->form) {
                    $candidates[] = FileUploadPathService::getFileUploadPath($this->form->id, $fileName);
                }
            }

            // Storage-only mode: we do not fetch remote URLs.
            return $candidates;
        }

        if (str_contains($normalized, '/')) {
            $candidates[] = ltrim($normalized, '/');
        }

        if ($this->form) {
            $candidates[] = FileUploadPathService::getFileUploadPath($this->form->id, $normalized);
        }

        $candidates[] = FormController::ASSETS_UPLOAD_PATH . '/' . $normalized;

        return array_values(array_unique($candidates));
    }
}
