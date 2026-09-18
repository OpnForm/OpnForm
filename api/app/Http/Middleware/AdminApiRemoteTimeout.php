<?php

namespace App\Http\Middleware;

use Closure;
use Stripe\ApiRequestor;
use Stripe\HttpClient\CurlClient;

class AdminApiRemoteTimeout
{
    public function handle($request, Closure $next)
    {
        $previous = ApiRequestor::httpClient();
        $client = new class () extends CurlClient {
            private float $deadline;
            public function __construct()
            {
                parent::__construct();
                $this->deadline = microtime(true) + (app()->runningInConsole() && !app()->runningUnitTests() ? 100 : 9);
            }
            public function request($method, $absUrl, $headers, $params, $hasFile, $apiMode = 'v1', $maxNetworkRetries = null)
            {
                $remaining = (int) floor($this->deadline - microtime(true));
                if ($remaining < 1) {
                    throw new \RuntimeException('Administrative provider deadline exceeded.');
                }
                $this->setConnectTimeout(min(2, $remaining));
                $this->setTimeout(min(4, $remaining));
                // A write with an uncertain response must never be replayed by SDK retries.
                return parent::request($method, $absUrl, $headers, $params, $hasFile, $apiMode, 0);
            }
        };
        ApiRequestor::setHttpClient($client);
        try {
            return $next($request);
        } finally {
            ApiRequestor::setHttpClient($previous);
        }
    }
}
