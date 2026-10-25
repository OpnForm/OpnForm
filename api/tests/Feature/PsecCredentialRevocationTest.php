<?php

use App\Http\Middleware\AuthenticateJWT;
use App\Models\User;
use Illuminate\Http\Request;
use Tymon\JWTAuth\Facades\JWTAuth;

it('revokes old JWTs and personal tokens on password rotation even when fingerprint checks are disabled', function () {
    config(['app.jwt_skip_ip_ua_validation' => true]);
    $user = User::factory()->create();
    $personal = $user->createToken('existing')->plainTextToken;
    $oldJwt = JWTAuth::fromUser($user);
    $request = Request::create('/user');
    $request->headers->set('Authorization', 'Bearer ' . $oldJwt);
    app()->instance('request', $request);
    JWTAuth::setToken($oldJwt);
    $middleware = new AuthenticateJWT();
    expect($middleware->handle($request, fn () => response('ok'))->status())->toBe(200);
    $user->update(['password' => bcrypt('Changed@1234')]);
    JWTAuth::setToken($oldJwt);
    expect($middleware->handle($request, fn () => response('ok'))->status())->toBe(401);
    expect(\Laravel\Sanctum\PersonalAccessToken::findToken($personal))->toBeNull();
    $freshJwt = JWTAuth::fromUser($user->fresh());
    $request->headers->set('Authorization', 'Bearer ' . $freshJwt);
    JWTAuth::setToken($freshJwt);
    expect($middleware->handle($request, fn () => response('ok'))->status())->toBe(200);
});
