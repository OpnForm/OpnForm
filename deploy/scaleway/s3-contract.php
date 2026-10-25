<?php

// Offline SDK/controller vectors. Run only in a network-none fixture container.
require '/app/vendor/autoload.php';
$app = require '/app/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Http\Controllers\Content\SignedStorageUrlController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

$_ENV['AWS_ACCESS_KEY_ID'] = 'fictional-email-key';
$_ENV['AWS_SECRET_ACCESS_KEY'] = 'fictional-email-secret';
$_ENV['AWS_SESSION_TOKEN'] = 'fictional-email-session';

$settings = [
    'driver' => 's3',
    'key' => 'fictional-storage-key',
    'secret' => 'fictional-storage-secret',
    'region' => 'fr-par',
    'bucket' => 'fixture-documents',
    'endpoint' => 'https://s3.fr-par.scw.cloud',
    'url' => 'https://assets.fixture.invalid',
    'use_path_style_endpoint' => true,
];

function fixtureDisk(array $settings): void
{
    config()->set('filesystems.disks.s3', $settings);
    Storage::forgetDisk('s3');
}

function fixtureUpload(array $input): array
{
    return app(SignedStorageUrlController::class)->store(new Request($input))->getData(true);
}

fixtureDisk($settings);
$vectors = [
    'private_put' => fixtureUpload(['content_type' => 'image/png']),
    'public_put' => fixtureUpload(['visibility' => 'public-read', 'content_type' => 'image/svg+xml']),
    'metadata_put' => fixtureUpload(['content_type' => 'application/pdf',
        'cache_control' => 'private, max-age=0', 'expires' => '2030-01-01T00:00:00Z']),
    'override_bucket_put' => fixtureUpload(['bucket' => 'fixture-other-bucket']),
    'private_get' => [
        'url' => Storage::disk('s3')->temporaryUrl('forms/42/submissions/réponse é.pdf', now()->addMinutes(7), [
            'ResponseContentDisposition' => 'attachment; filename="fixture.pdf"',
        ]),
        'headers' => [],
    ],
    'public_asset_url' => Storage::disk('s3')->url('assets/logo.png'),
];

fixtureDisk(array_merge($settings, ['token' => 'fictional-storage-session']));
$vectors['session_put'] = fixtureUpload([]);
fixtureDisk(array_merge($settings, ['endpoint' => null, 'region' => 'eu-west-3']));
$vectors['legacy_put'] = fixtureUpload([]);
fixtureDisk(array_merge($settings, ['bucket' => null]));
try {
    fixtureUpload([]);
    $vectors['missing_bucket_refused'] = false;
} catch (InvalidArgumentException $exception) {
    $vectors['missing_bucket_refused'] = true;
}

echo json_encode(['fixture' => true, 'vectors' => $vectors], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
