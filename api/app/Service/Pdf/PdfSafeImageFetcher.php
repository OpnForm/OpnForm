<?php

namespace App\Service\Pdf;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use Psr\Http\Message\ResponseInterface;
use RuntimeException;

class PdfSafeImageFetcher
{
    private const CONNECT_TIMEOUT_SECONDS = 5;

    private const TIMEOUT_SECONDS = 10;

    private const MAX_BYTES = 5 * 1024 * 1024;

    public function fetch(string $url): ?string
    {
        try {
            $requestOptions = PdfRemoteImageUrl::requestOptions($url);
        } catch (InvalidArgumentException $e) {
            Log::debug('PDF remote image URL rejected', [
                'url' => $url,
                'error' => $e->getMessage(),
            ]);

            return null;
        }

        $httpOptions = array_merge($requestOptions, [
            'on_headers' => function (ResponseInterface $response): void {
                if (!$this->responseHeadersAreSafeFromPsr($response)) {
                    throw new RuntimeException('PDF remote image response rejected.');
                }
            },
        ]);

        try {
            $response = Http::connectTimeout(self::CONNECT_TIMEOUT_SECONDS)
                ->timeout(self::TIMEOUT_SECONDS)
                ->accept('image/*')
                ->withOptions($httpOptions)
                ->get($url);

            if (!$response->successful()) {
                return null;
            }

            if (!$this->responseHeadersAreSafe($response)) {
                return null;
            }

            $body = $response->body();
            if ($body === '' || strlen($body) > self::MAX_BYTES) {
                return null;
            }

            return $body;
        } catch (RuntimeException $e) {
            Log::debug('PDF remote image download rejected', [
                'url' => $url,
                'error' => $e->getMessage(),
            ]);

            return null;
        } catch (\Throwable $e) {
            Log::debug('PDF remote image download failed', [
                'url' => $url,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    private function responseHeadersAreSafe(\Illuminate\Http\Client\Response $response): bool
    {
        $contentLength = $response->header('Content-Length');
        if ($contentLength !== null && $contentLength !== '' && (int) $contentLength > self::MAX_BYTES) {
            return false;
        }

        $contentType = strtolower((string) $response->header('Content-Type', ''));
        if ($contentType !== '' && !str_starts_with($contentType, 'image/')) {
            return false;
        }

        return true;
    }

    private function responseHeadersAreSafeFromPsr(ResponseInterface $response): bool
    {
        $contentLength = $response->getHeaderLine('Content-Length');
        if ($contentLength !== '' && (int) $contentLength > self::MAX_BYTES) {
            return false;
        }

        $contentType = strtolower($response->getHeaderLine('Content-Type'));
        if ($contentType !== '' && !str_starts_with($contentType, 'image/')) {
            return false;
        }

        return true;
    }
}
