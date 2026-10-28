<?php

use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\RateLimiter;

it('isolates authenticated renderer reads in a bounded bucket', function (string $name) {
    config(['app.front_api_secret' => 'local-ssr-secret', 'app.ssr_reads_per_minute' => 120]);
    $request = Request::create('/fixture', 'GET', server: ['REMOTE_ADDR' => '172.18.0.4']);
    $request->headers->set('x-api-secret', 'local-ssr-secret');
    $route = (new Route('GET', '/fixture', fn () => null))->name($name);
    $request->setRouteResolver(fn () => $route);
    $limit = RateLimiter::limiter('api')($request);
    expect($limit->maxAttempts)->toBe(120)->and($limit->key)->toBe('ssr-read:172.18.0.4');
})->with(['forms.show', 'content.feature-flags', 'content.plans']);

it('keeps ordinary limits for missing credentials, forged credentials, writes and private routes', function ($secret, $header, $method, $name) {
    config(['app.front_api_secret' => $secret]);
    $request = Request::create('/fixture', $method, server: ['REMOTE_ADDR' => '172.18.0.4']);
    if ($header !== null) {
        $request->headers->set('x-api-secret', $header);
    }
    $route = (new Route($method, '/fixture', fn () => null))->name($name);
    $request->setRouteResolver(fn () => $route);
    $limit = RateLimiter::limiter('api')($request);
    expect($limit->maxAttempts)->toBe(60)->and($limit->key)->toBe('172.18.0.4');
})->with([
    ['secret', null, 'GET', 'forms.show'],
    ['secret', 'wrong', 'GET', 'forms.show'],
    ['', '', 'GET', 'forms.show'],
    [null, '', 'GET', 'forms.show'],
    ['secret', 'secret', 'POST', 'forms.show'],
    ['secret', 'secret', 'GET', 'open.forms.show'],
    ['secret', 'secret', 'GET', 'forms.fetchSubmission'],
    ['secret', 'secret', 'GET', 'forms.assets.show'],
]);


it('enforces the SSR quota and keeps the ordinary bucket independent over HTTP', function () {
    config(['app.front_api_secret' => 'local-ssr-secret', 'app.ssr_reads_per_minute' => 2]);
    $this->withMiddleware(\Illuminate\Routing\Middleware\ThrottleRequests::class);
    \Illuminate\Support\Facades\Route::middleware('throttle:api')
        ->get('/ssr-rate-fixture', fn () => response()->json(['ok' => true]))->name('forms.show');
    $this->withHeader('x-api-secret', 'local-ssr-secret');
    $this->getJson('/ssr-rate-fixture')->assertOk();
    $this->getJson('/ssr-rate-fixture')->assertOk();
    $this->getJson('/ssr-rate-fixture')->assertTooManyRequests();
    $this->withHeader('x-api-secret', 'forged')->getJson('/ssr-rate-fixture')->assertOk();
});
