<?php

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;

it('serves the font catalog outside the public fonts directory', function () {
    Config::set('services.google.fonts_api_key', 'test-key');
    Cache::forget('google_fonts');
    Http::fake([
        'https://www.googleapis.com/webfonts/v1/webfonts*' => Http::response([
            'items' => [
                ['family' => 'Roboto', 'category' => 'sans-serif'],
                ['family' => 'Roboto Mono', 'category' => 'monospace'],
                ['family' => 'Lato', 'category' => 'sans-serif'],
            ],
        ]),
    ]);

    $response = $this->getJson(route('content.fonts'));

    $response->assertOk();
    expect(array_values($response->json()))->toBe(['Roboto', 'Lato']);
});
