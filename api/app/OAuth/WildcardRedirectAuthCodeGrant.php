<?php

namespace App\OAuth;

use League\OAuth2\Server\Exception\OAuthServerException;
use League\OAuth2\Server\Grant\AuthCodeGrant;
use League\OAuth2\Server\RequestEvent;
use Psr\Http\Message\ServerRequestInterface;
use League\OAuth2\Server\Entities\ClientEntityInterface;

class WildcardRedirectAuthCodeGrant extends AuthCodeGrant
{
    /**
     * Override redirect URI validation to support wildcard patterns.
     *
     * Stored redirect URIs ending with `*` are treated as prefix matches.
     * For example, `https://chatgpt.com/connector/oauth/*` matches
     * `https://chatgpt.com/connector/oauth/CxpwIw6kFjLr`.
     */
    protected function validateRedirectUri(
        string $redirectUri,
        ClientEntityInterface $client,
        ServerRequestInterface $request
    ) {
        $allowedUris = $client->getRedirectUri();

        if (is_string($allowedUris)) {
            $allowedUris = [$allowedUris];
        }

        foreach ($allowedUris as $allowed) {
            if (str_ends_with($allowed, '*')) {
                $prefix = substr($allowed, 0, -1);
                if (str_starts_with($redirectUri, $prefix)) {
                    return;
                }
            } elseif ($redirectUri === $allowed) {
                return;
            }
        }

        $this->getEmitter()->emit(new RequestEvent(RequestEvent::CLIENT_AUTHENTICATION_FAILED, $request));
        throw OAuthServerException::invalidClient($request);
    }
}
