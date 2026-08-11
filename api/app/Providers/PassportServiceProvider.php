<?php

namespace App\Providers;

use App\OAuth\WildcardRedirectAuthCodeGrant;
use DateInterval;
use Laravel\Passport\Bridge;
use Laravel\Passport\PassportServiceProvider as BasePassportServiceProvider;

class PassportServiceProvider extends BasePassportServiceProvider
{
    /**
     * Build a WildcardRedirectAuthCodeGrant instead of the default AuthCodeGrant
     * so that redirect URIs ending with `*` are treated as prefix matches.
     */
    protected function buildAuthCodeGrant()
    {
        return new WildcardRedirectAuthCodeGrant(
            $this->app->make(Bridge\AuthCodeRepository::class),
            $this->app->make(Bridge\RefreshTokenRepository::class),
            new DateInterval('PT10M')
        );
    }
}
