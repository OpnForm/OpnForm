<?php

use App\Rules\PublicMediaUrlRule;
use Illuminate\Support\Facades\Validator;

it('accepts durable public HTTPS media URLs', function () {
    $validator = Validator::make([
        'media_url' => 'https://cdn.example.com/forms/header.png',
    ], [
        'media_url' => [new PublicMediaUrlRule()],
    ]);

    expect($validator->passes())->toBeTrue();
});

it('rejects unsafe or temporary media URLs', function (string $url, string $message) {
    $validator = Validator::make([
        'media_url' => $url,
    ], [
        'media_url' => [new PublicMediaUrlRule()],
    ]);

    expect($validator->fails())->toBeTrue()
        ->and($validator->errors()->first('media_url'))->toContain($message);
})->with([
    'plain HTTP' => ['http://cdn.example.com/header.png', 'HTTPS'],
    'embedded credentials' => ['https://cdn.example.com@attacker.example/header.png', 'embedded credentials'],
    'localhost' => ['https://localhost/header.png', 'public host'],
    'private IPv4' => ['https://192.168.1.12/header.png', 'public host'],
    'loopback IPv6' => ['https://[::1]/header.png', 'public host'],
    'private hostname' => ['https://assets.internal/header.png', 'public host'],
    'temporary tunnel' => ['https://draft.trycloudflare.com/header.png', 'temporary tunnel'],
    'expiring signature' => ['https://cdn.example.com/header.png?expires=123&signature=secret', 'expiring or signed'],
]);
