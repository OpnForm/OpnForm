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
    $limit = RateLimiter::limiter('internal-api')($request);
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
    $limit = RateLimiter::limiter('internal-api')($request);
    expect($limit->maxAttempts)->toBe(100)->and($limit->key)->toBe('|172.18.0.4');
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


function enableProductionSsrApiThrottle($test): void
{
    $previousEnv = $_ENV['APP_ENV'] ?? null;
    $_ENV['APP_ENV'] = 'production';
    try {
        $kernel = new \App\Http\Kernel(app(), app('router'));
    } finally {
        if ($previousEnv === null) {
            unset($_ENV['APP_ENV']);
        } else {
            $_ENV['APP_ENV'] = $previousEnv;
        }
    }
    app()->instance(\Illuminate\Contracts\Http\Kernel::class, $kernel);
    $test->withMiddleware(\Illuminate\Routing\Middleware\ThrottleRequests::class);
}

it('enforces the SSR quota on real public routes with the production kernel', function (string $name) {
    enableProductionSsrApiThrottle($this);
    config(['app.front_api_secret' => 'local-ssr-secret', 'app.ssr_reads_per_minute' => 2]);
    $parameters = [];
    if ($name === 'forms.show') {
        $user = $this->createUser();
        $workspace = $this->createUserWorkspace($user);
        $parameters['form'] = $this->createForm($user, $workspace)->slug;
    }
    $url = route($name, $parameters);
    $this->withHeader('x-api-secret', 'local-ssr-secret');
    $this->getJson($url)->assertOk()->assertHeader('X-RateLimit-Limit', '2');
    $this->getJson($url)->assertOk();
    $this->getJson($url)->assertTooManyRequests();
    $this->withHeader('x-api-secret', 'forged')->getJson($url)
        ->assertOk()->assertHeader('X-RateLimit-Limit', '100');
})->with(['forms.show', 'content.feature-flags', 'content.plans']);

it('shares the ordinary internal quota across routes without consuming the renderer bucket', function () {
    enableProductionSsrApiThrottle($this);
    config(['app.front_api_secret' => 'local-ssr-secret', 'app.ssr_reads_per_minute' => 2]);
    for ($i = 0; $i < 100; $i++) {
        $name = $i % 2 === 0 ? 'content.feature-flags' : 'content.plans';
        $this->getJson(route($name))->assertOk()->assertHeader('X-RateLimit-Limit', '100');
    }
    $this->getJson(route('content.plans'))->assertTooManyRequests();
    $this->withHeader('x-api-secret', 'local-ssr-secret')
        ->getJson(route('content.feature-flags'))->assertOk()->assertHeader('X-RateLimit-Limit', '2');
});

it('preserves the external API quota', function () {
    $request = Request::create('/fixture', 'GET', server: ['REMOTE_ADDR' => '172.18.0.4']);
    $limit = RateLimiter::limiter('api')($request);
    expect($limit->maxAttempts)->toBe(60);
});
