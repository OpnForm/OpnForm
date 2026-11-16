<?php

namespace App\Http\Middleware;

use Illuminate\Http\Middleware\TrustProxies as Middleware;
use Illuminate\Http\Request;

class TrustProxies extends Middleware
{
    /**
     * The trusted proxies for this application.
     *
     * @var array|string|null
     */
    protected $proxies;

    /**
     * The headers that should be used to detect proxies.
     *
     * @var int
     */
    protected $headers =
        Request::HEADER_X_FORWARDED_FOR |
        Request::HEADER_X_FORWARDED_HOST |
        Request::HEADER_X_FORWARDED_PORT |
        Request::HEADER_X_FORWARDED_PROTO |
        Request::HEADER_X_FORWARDED_AWS_ELB;

    protected function setTrustedProxyIpAddresses(Request $request)
    {
        if (config('app.self_hosted') && ($this->proxies() ?: config('trustedproxy.proxies')) === null) {
            // Preserve self-hosted HTTPS detection without trusting client IP or host headers.
            $request->setTrustedProxies(['0.0.0.0/0', '::/0'], Request::HEADER_X_FORWARDED_PROTO);

            return;
        }

        parent::setTrustedProxyIpAddresses($request);
    }
}
