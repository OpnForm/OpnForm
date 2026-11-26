<?php

use App\Exceptions\Handler;
use Illuminate\Container\Container;
use League\OAuth2\Server\Exception\OAuthServerException;

it('keeps reporting exceptions before a request is bound', function () {
    $handler = new Handler(new Container());

    expect($handler->shouldReport(new RuntimeException('Bootstrap failed')))->toBeTrue()
        ->and($handler->shouldReport(OAuthServerException::accessDenied()))->toBeTrue();
});
