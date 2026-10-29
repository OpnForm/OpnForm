<?php

use App\Http\Controllers\Content\SignedStorageUrlController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    config()->set('filesystems.disks.s3', [
        'driver' => 's3',
        'key' => 'fictional-storage-key',
        'secret' => 'fictional-storage-secret',
        'region' => 'fr-par',
        'bucket' => 'fixture-documents',
        'endpoint' => 'https://objects.fixture.test',
        'use_path_style_endpoint' => true,
    ]);
    Storage::forgetDisk('s3');
});

it('signs uploads with the configured storage endpoint and credentials without AWS environment variables', function () {
    $response = app(SignedStorageUrlController::class)->store(new Request([
        'content_type' => 'image/png',
    ]));
    $data = $response->getData(true);

    expect($response->getStatusCode())->toBe(201)
        ->and($data['bucket'])->toBe('fixture-documents')
        ->and($data['key'])->toStartWith('tmp/')
        ->and($data['url'])->toStartWith('https://objects.fixture.test/fixture-documents/tmp/')
        ->and($data['headers']['Content-Type'])->toBe('image/png');
    parse_str(parse_url($data['url'], PHP_URL_QUERY), $query);
    expect($query['X-Amz-Credential'])->toStartWith('fictional-storage-key/');
});

it('preserves legacy AWS S3 signing when no custom endpoint is configured', function () {
    config()->set('filesystems.disks.s3.endpoint', null);
    config()->set('filesystems.disks.s3.region', 'eu-west-3');
    Storage::forgetDisk('s3');

    $data = app(SignedStorageUrlController::class)->store(new Request())->getData(true);

    expect(parse_url($data['url'], PHP_URL_HOST))->toBe('s3.eu-west-3.amazonaws.com')
        ->and($data['bucket'])->toBe('fixture-documents');
});

it('fails before signing when no bucket is configured', function () {
    config()->set('filesystems.disks.s3.bucket', null);

    $this->postJson('/vapor/signed-storage-url')->assertStatus(503);
});

it('rejects a different or malformed bucket before signing an anonymous upload', function ($bucket) {
    $this->postJson('/vapor/signed-storage-url', ['bucket' => $bucket])
        ->assertUnprocessable()->assertJsonValidationErrors('bucket');
})->with([
    'different bucket' => ['other-fixture'],
    'array' => [['bucket' => 'fixture-documents']],
    'number' => [123],
]);

it('accepts the configured bucket and blank legacy bucket values', function ($bucket) {
    $this->postJson('/vapor/signed-storage-url', ['bucket' => $bucket])
        ->assertCreated()->assertJsonPath('bucket', 'fixture-documents');
})->with(['fixture-documents', '', null]);

it('returns cache metadata with canonically ordered signed headers and independent upload expiry', function () {
    $data = app(SignedStorageUrlController::class)->store(new Request([
        'content_type' => 'application/pdf',
        'cache_control' => 'private, max-age=0',
        'expires' => '2030-01-01T00:00:00Z',
    ]))->getData(true);
    parse_str(parse_url($data['url'], PHP_URL_QUERY), $query);
    $signed = explode(';', $query['X-Amz-SignedHeaders']);
    $sorted = $signed;
    sort($sorted);

    expect($signed)->toBe($sorted)
        ->and($signed)->toContain('expires', 'host', 'x-amz-acl')
        ->and($data['headers']['Cache-Control'])->toBe('private, max-age=0')
        ->and($data['headers']['Expires'])->toBe(['Tue, 01 Jan 2030 00:00:00 GMT'])
        ->and($query['X-Amz-Expires'])->toBe('300');
});

it('preserves explicit public assets and default private upload headers', function () {
    $private = app(SignedStorageUrlController::class)->store(new Request())->getData(true);
    $public = app(SignedStorageUrlController::class)->store(new Request([
        'visibility' => 'public-read',
    ]))->getData(true);

    expect($private['headers']['x-amz-acl'])->toBe(['private'])
        ->and($public['headers']['x-amz-acl'])->toBe(['public-read'])
        ->and($private['headers'])->not->toHaveKey('Cache-Control')
        ->and($private['headers'])->not->toHaveKey('Expires');
});

it('uses the application signing expiry independently of object expiration', function () {
    config(['filesystems.signed_storage_url_expires_after' => 2]);
    $data = app(SignedStorageUrlController::class)->store(new Request([
        'expires' => '2030-01-01T00:00:00Z',
    ]))->getData(true);
    parse_str(parse_url($data['url'], PHP_URL_QUERY), $query);

    expect($query['X-Amz-Expires'])->toBe('120')
        ->and($data['headers']['Expires'])->toBe(['Tue, 01 Jan 2030 00:00:00 GMT']);
});
