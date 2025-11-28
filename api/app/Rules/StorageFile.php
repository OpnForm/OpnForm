<?php

namespace App\Rules;

use App\Service\Storage\FileUploadPathService;
use App\Models\Forms\Form;
use App\Service\Storage\StorageFileNameParser;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\Storage;

class StorageFile implements ValidationRule
{
    public string $error = 'Invalid file.';

    public function __construct(public int $maxSize, public array $fileTypes = [], public ?Form $form = null)
    {
    }

    /**
     * File can have 2 formats:
     * - file-name_{uuid}.{ext}
     * - {uuid}
     *
     * @param  string  $attribute
     * @param  mixed  $value
     */
    public function passes($attribute, $value): bool
    {
        // If full path then no need to validate
        if (filter_var($value, FILTER_VALIDATE_URL) !== false) {
            return true;
        }
        $fileNameParser = StorageFileNameParser::parse($value);

        // This is use when updating a record, and file uploads aren't changed.
        if ($this->form) {
            if (Storage::exists(FileUploadPathService::getFileUploadPath($this->form->id, $fileNameParser->getMovedFileName()))) {
                return true;
            }
        }

        $fileNameParser = StorageFileNameParser::parse($value);
        if (! $uuid = $fileNameParser->uuid) {
            return false;
        }

        $filePath = FileUploadPathService::getTmpFileUploadPath($uuid);
        if (! Storage::exists($filePath)) {
            return false;
        }

        if (Storage::size($filePath) > $this->maxSize) {
            $this->error = 'File is too large.';

            return false;
        }

        if (count($this->fileTypes) > 0) {
            $this->error = 'Incorrect file type. Allowed only: ' . implode(',', $this->fileTypes);
            return collect($this->fileTypes)->map(function ($type) {
                return strtolower($type);
            })->contains(strtolower($fileNameParser->extension));
        }

        return true;
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (!$this->passes($attribute, $value)) {
            $fail($this->message());
        }
    }

    public function message(): string
    {
        return $this->error;
    }
}
