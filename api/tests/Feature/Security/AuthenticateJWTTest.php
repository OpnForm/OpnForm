<?php

it('defaults jwt user agent validation to enabled', function () {
    expect(config('app.jwt_skip_ip_ua_validation'))->toBeFalse();
});

it('keeps jwt validation enabled in the docker production env template', function () {
    $envDocker = file_get_contents(base_path('.env.docker'));

    expect($envDocker)->toContain('JWT_SKIP_IP_UA_VALIDATION=false');
});

it('skips jwt user agent validation when configured', function () {
    config(['app.jwt_skip_ip_ua_validation' => true]);

    $middleware = new App\Http\Middleware\AuthenticateJWT();
    $request = Illuminate\Http\Request::create('/api/open/forms', 'GET');
    $called = false;

    $response = $middleware->handle($request, function () use (&$called) {
        $called = true;

        return response('ok');
    });

    expect($called)->toBeTrue()
        ->and($response->getContent())->toBe('ok');
});

it('rejects jwt requests when user agent does not match token claim', function () {
    config([
        'app.jwt_skip_ip_ua_validation' => false,
        'app.front_api_secret' => 'front-secret',
    ]);

    $payload = Mockery::mock(Tymon\JWTAuth\Payload::class);
    $payload->shouldReceive('get')->with('impersonating')->andReturn(false);
    $payload->shouldReceive('get')->with('ua')->andReturn(
        Illuminate\Support\Facades\Hash::make('Mozilla/5.0 (Valid Browser)')
    );

    Tymon\JWTAuth\Facades\JWTAuth::shouldReceive('parseToken->getPayload')->andReturn($payload);
    Illuminate\Support\Facades\Auth::shouldReceive('invalidate')->once();

    $middleware = new App\Http\Middleware\AuthenticateJWT();
    $request = Illuminate\Http\Request::create('/api/open/forms', 'GET', [], [], [], [
        'HTTP_USER_AGENT' => 'Different Browser',
        'HTTP_AUTHORIZATION' => 'Bearer fake-token',
    ]);

    $response = $middleware->handle($request, fn () => response('ok'));

    expect($response->getStatusCode())->toBe(401)
        ->and($response->getData(true)['message'])->toBe('Origin User Agent is invalid');
});
