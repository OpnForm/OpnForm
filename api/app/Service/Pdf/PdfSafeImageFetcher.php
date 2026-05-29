<?php

namespace App\Service\Pdf;

use App\Service\Security\PublicWebhookUrl;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

class PdfSafeImageFetcher
{
    private const TIMEOUT_SECONDS = 10;

    private const MAX_BYTES = 5 * 1024 * 1024;

    public function fetch(string $url): ?string
    {
        try {
            PublicWebhookUrl::assertSafe($url);
        } catch (InvalidArgumentException $e) {
            Log::debug('PDF remote image URL rejected', [
                'url' => $url,
                'error' => $e->getMessage(),
            ]);

            return null;
        }

        try {
            $response = Http::timeout(self::TIMEOUT_SECONDS)
                ->accept('image/*')
                ->withOptions(PublicWebhookUrl::requestOptions($url))
                ->get($url);

            if (!$response->successful()) {
                return null;
            }

            $contentType = strtolower((string) $response->header('Content-Type', ''));
            if ($contentType !== '' && !str_starts_with($contentType, 'image/')) {
                return null;
            }

            $body = $response->body();
            if ($body === '' || strlen($body) > self::MAX_BYTES) {
                return null;
            }

            return $body;
        } catch (\Throwable $e) {
            Log::debug('PDF remote image download failed', [
                'url' => $url,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }
}
