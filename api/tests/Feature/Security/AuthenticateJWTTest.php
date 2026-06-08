<?php

use App\Http\Middleware\AuthenticateJWT;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Tymon\JWTAuth\Facades\JWTAuth;
use Tymon\JWTAuth\Payload;

function createJwtMiddlewareRequest(string $userAgent = 'Different Browser'): Request
{
    return Request::create('/api/open/forms', 'GET', [], [], [], [
        'HTTP_USER_AGENT' => $userAgent,
        'HTTP_AUTHORIZATION' => 'Bearer fake-token',
    ]);
}

function mockJwtPayloadWithUserAgent(string $userAgent): Payload
{
    $payload = Mockery::mock(Payload::class);
    $payload->shouldReceive('get')->with('impersonating')->andReturn(false);
    $payload->shouldReceive('get')->with('ua')->andReturn(Hash::make($userAgent));

    JWTAuth::shouldReceive('parseToken->getPayload')->andReturn($payload);

    return $payload;
}

it('defaults jwt user agent validation to enabled', function () {
    expect(config('app.jwt_skip_ip_ua_validation'))->toBeFalse();
});

it('keeps jwt validation enabled in the docker production env template', function () {
    $envDocker = file_get_contents(base_path('.env.docker'));

    expect($envDocker)->toContain('JWT_SKIP_IP_UA_VALIDATION=false');
});

it('skips jwt user agent validation before token parsing when configured', function () {
    config([
        'app.jwt_skip_ip_ua_validation' => true,
        'app.front_api_secret' => 'front-secret',
    ]);

    JWTAuth::shouldReceive('parseToken')->never();

    $middleware = new AuthenticateJWT();
    $request = createJwtMiddlewareRequest('Different Browser');

    $response = $middleware->handle($request, fn () => response('ok'));

    expect($response->getStatusCode())->toBe(200)
        ->and($response->getContent())->toBe('ok');
});

it('rejects jwt requests when user agent does not match token claim', function () {
    config([
        'app.jwt_skip_ip_ua_validation' => false,
        'app.front_api_secret' => 'front-secret',
    ]);

    mockJwtPayloadWithUserAgent('Mozilla/5.0 (Valid Browser)');
    Auth::shouldReceive('invalidate')->once();

    $middleware = new AuthenticateJWT();
    $request = createJwtMiddlewareRequest('Different Browser');

    $response = $middleware->handle($request, fn () => response('ok'));

    expect($response->getStatusCode())->toBe(401)
        ->and($response->getData(true)['message'])->toBe('Origin User Agent is invalid');
});
