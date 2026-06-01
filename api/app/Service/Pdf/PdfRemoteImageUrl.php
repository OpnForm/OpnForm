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
    public static function assertSafe(string $url): void
    {
        PublicWebhookUrl::assertPublicOnly($url);
    }

    /**
     * @return array<string, mixed>
     */
    public static function requestOptions(string $url): array
    {
        return PublicWebhookUrl::requestOptionsPublicOnly($url);
    }
}
