<?php

namespace App\Service\Storage;

readonly class UploadSecurityInspection
{
    public function __construct(
        public string $mimeType,
        public bool $isImage,
        public bool $isSvg,
        public ?string $sanitizedContents = null,
    ) {
    }
}
