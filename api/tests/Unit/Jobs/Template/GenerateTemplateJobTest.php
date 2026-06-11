<?php

use App\Jobs\Template\GenerateTemplateJob;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;

uses(\Tests\TestCase::class);

beforeEach(function () {
    Config::set('services.unsplash.access_key', 'test-unsplash-key');
});

it('returns null when unsplash response has no results', function () {
    Http::fake([
        'api.unsplash.com/*' => Http::response(['results' => []]),
    ]);

    $job = new GenerateTemplateJob('prompt');
    $method = new ReflectionMethod(GenerateTemplateJob::class, 'getImageCoverUrl');
    $method->setAccessible(true);

    expect($method->invoke($job, 'office workspace'))->toBeNull();
});

it('returns null when unsplash response is not an array', function () {
    Http::fake([
        'api.unsplash.com/*' => Http::response('not-json', 500),
    ]);

    $job = new GenerateTemplateJob('prompt');
    $method = new ReflectionMethod(GenerateTemplateJob::class, 'getImageCoverUrl');
    $method->setAccessible(true);

    expect($method->invoke($job, 'office workspace'))->toBeNull();
});

it('returns a resized image url from unsplash results', function () {
    Http::fake([
        'api.unsplash.com/*' => Http::response([
            'results' => [
                ['urls' => ['regular' => 'https://images.unsplash.com/photo-1?w=1080&h=720']],
                ['urls' => ['regular' => 'https://images.unsplash.com/photo-2?w=1080&h=720']],
            ],
        ]),
    ]);

    $job = new GenerateTemplateJob('prompt');
    $method = new ReflectionMethod(GenerateTemplateJob::class, 'getImageCoverUrl');
    $method->setAccessible(true);

    $url = $method->invoke($job, 'office workspace');

    expect($url)->toBeString();
    expect($url)->toContain('w=600');
    expect($url)->not->toContain('w=1080');
});
