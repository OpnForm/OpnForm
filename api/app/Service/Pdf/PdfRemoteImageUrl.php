<?php

namespace App\Service\Pdf;

use App\Service\Security\PublicWebhookUrl;

/**
 * SSRF policy for PDF remote image fetches.
 *
 * Unlike webhook URLs, submitter-controlled PDF image values must never trigger
 * fetches to private network targets, even when opnform.webhooks.allow_private_urls
 * is enabled for integrations.
 */
class PdfRemoteImageUrl
{
    /** @var array<string, true> */
    private static array $validatedUrls = [];

    public static function assertSafe(string $url): void
    {
        self::rememberValidated($url);
    }

    /**
     * @return array<string, mixed>
     */
    public static function requestOptions(string $url): array
    {
        self::rememberValidated($url);

        return PublicWebhookUrl::requestOptionsPublicOnly($url);
    }

    private static function rememberValidated(string $url): void
    {
        if (isset(self::$validatedUrls[$url])) {
            return;
        }

        PublicWebhookUrl::assertPublicOnly($url);
        self::$validatedUrls[$url] = true;
    }
}
