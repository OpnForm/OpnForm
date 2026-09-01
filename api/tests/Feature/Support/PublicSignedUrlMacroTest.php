<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\URL;

beforeEach(function () {
    Route::get('/signed-url-test/{value}', fn () => 'ok')
        ->name('signed-url-test')
        ->middleware('signed:relative');
    Route::getRoutes()->refreshNameLookups();
});

it('returns a canonical absolute URL with a relative signature', function () {
    config()->set('app.url', 'https://forms.example.test/');

    $url = URL::publicSignedRoute('signed-url-test', ['value' => 123]);

    expect($url)->toStartWith('https://forms.example.test/signed-url-test/123?signature=');

    $this->get($url)->assertOk();
});

it('returns a canonical temporary URL with a valid relative signature', function () {
    config()->set('app.url', 'https://forms.example.test/');

    $url = URL::temporaryPublicSignedRoute(
        'signed-url-test',
        now()->addMinute(),
        ['value' => 123]
    );

    expect($url)
        ->toStartWith('https://forms.example.test/signed-url-test/123?expires=')
        ->toContain('&signature=');

    $this->get($url)->assertOk();
});

it('falls back to the relative signed URL when the application URL is missing', function () {
    config()->set('app.url', null);

    $url = URL::publicSignedRoute('signed-url-test', ['value' => 123]);

    expect($url)->toStartWith('/signed-url-test/123?signature=');

    $this->get($url)->assertOk();
});
