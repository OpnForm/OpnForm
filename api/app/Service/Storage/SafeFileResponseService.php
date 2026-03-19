<?php

namespace App\Service\Storage;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SafeFileResponseService
{
    public function serve(string $path, ?string $downloadName = null): StreamedResponse
    {
        $stream = Storage::readStream($path);

        abort_if($stream === false, 404, 'File not found.');

        $mimeType = Storage::mimeType($path) ?: 'application/octet-stream';
        $headers = [
            'Content-Type' => $mimeType,
            'Content-Security-Policy' => "default-src 'none'; script-src 'none'; object-src 'none'; base-uri 'none'; form-action 'none'; frame-ancestors 'none'; sandbox",
            'X-Content-Type-Options' => 'nosniff',
            'X-Frame-Options' => 'DENY',
        ];

        if (!Str::startsWith($mimeType, 'image/')) {
            $headers['Content-Disposition'] = 'attachment; filename="' . ($downloadName ?? basename($path)) . '"';
        }

        return response()->stream(function () use ($stream) {
            try {
                fpassthru($stream);
            } finally {
                if (is_resource($stream)) {
                    fclose($stream);
                }
            }
        }, 200, $headers);
    }
}
