<?php

namespace App\Service\Storage;

use Illuminate\Support\Str;

/**
 * Used
 * File can have 2 formats:
 * - file_name-{uuid}.{ext}
 * - {uuid}
 */
class StorageFileNameParser
{
    public ?string $uuid = null;

    public ?string $fileName = null;

    public ?string $extension = null;

    public function __construct(string $fileName)
    {
        $this->parseFileName($fileName);
    }

    /**
     * If we have parsed a file name and an extension, we keep the same and append uuid to avoid collisions
     * Otherwise we just return the uuid
     */
    public function getMovedFileName(): ?string
    {
        if ($this->fileName && $this->extension) {
            $fileName = mb_substr($this->fileName, 0, 50) . '_' . $this->uuid . '.' . $this->extension;
            $fileName = preg_replace('#\p{C}+#u', '', $fileName); // avoid CorruptedPathDetected exceptions

            return $fileName ? S3KeyCleaner::sanitize($fileName) : null;
        }

        return $this->uuid;
    }

    private function parseFileName(string $fileName)
    {
        if (Str::isUuid($fileName)) {
            $this->uuid = $fileName;

            return;
        }

        if (!str_contains($fileName, '_')) {
            return;
        }

        $candidateString = substr($fileName, strrpos($fileName, '_') + 1);
        if (
            !str_contains($candidateString, '.')
            || !Str::isUuid(substr($candidateString, 0, strpos($candidateString, '.')))
        ) {
            return;
        }

        try {
            $this->uuid = substr($candidateString, 0, strpos($candidateString, '.'));
            $this->fileName = substr($fileName, 0, strrpos($fileName, '_'));
            // get everything after the last dot
            $this->extension = substr($candidateString, strrpos($candidateString, '.') + 1);
        } catch (\Exception $e) {
            return;
        }
    }

    public static function parse(string $fileName): self
    {
        return new self($fileName);
    }
}
