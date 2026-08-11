<?php

use App\Service\Storage\SafeFileResponseService;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

uses(TestCase::class);

it('serves audio assets inline', function () {
    $stream = fopen('php://temp', 'r+');
    fwrite($stream, 'audio content');
    rewind($stream);

    Storage::shouldReceive('readStream')
        ->once()
        ->with('forms/assets/podcast.mp3')
        ->andReturn($stream);
    Storage::shouldReceive('mimeType')
        ->once()
        ->with('forms/assets/podcast.mp3')
        ->andReturn('audio/mpeg');

    $response = app(SafeFileResponseService::class)->serve('forms/assets/podcast.mp3', 'podcast.mp3');

    expect($response->headers->get('Content-Type'))->toBe('audio/mpeg');
    expect($response->headers->get('Content-Disposition'))->toBeNull();

    fclose($stream);
});
