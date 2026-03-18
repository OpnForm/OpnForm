<?php

use App\Http\Controllers\Forms\FormController;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;

function pngFixture(): string
{
    return base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO2Z9f8AAAAASUVORK5CYII=');
}

it('serves image assets inline with restrictive headers', function () {
    Storage::fake();

    $fileName = 'test_asset_' . uniqid() . '.png';
    $path = FormController::ASSETS_UPLOAD_PATH . '/' . $fileName;
    Storage::put($path, pngFixture());

    $response = $this->get(route('forms.assets.show', [$fileName]));

    $response->assertOk();
    expect(strtolower($response->headers->get('content-type')))
        ->toStartWith('image/png');
    expect($response->headers->get('content-disposition'))
        ->toBeNull();
    expect($response->headers->get('x-content-type-options'))
        ->toBe('nosniff');
    expect($response->headers->get('content-security-policy'))
        ->toContain("default-src 'none'")
        ->toContain('sandbox');
});

it('serves non-image public assets as attachments with restrictive headers', function () {
    Storage::fake();

    $fileName = 'test_asset_' . uniqid() . '.pdf';
    $path = FormController::ASSETS_UPLOAD_PATH . '/' . $fileName;
    Storage::put($path, '%PDF-1.4 test');

    $response = $this->get(route('forms.assets.show', [$fileName]));

    $response->assertOk();
    expect(strtolower($response->headers->get('content-type')))
        ->toStartWith('application/pdf');
    expect($response->headers->get('content-disposition'))
        ->toStartWith('attachment;');
    expect($response->headers->get('x-frame-options'))
        ->toBe('DENY');
});

it('serves signed local temporary files with restrictive anti-execution headers', function () {
    Storage::fake();

    $path = 'tmp/' . uniqid() . '.html';
    Storage::put($path, '<html><script>alert(1)</script></html>');

    $signedUrl = URL::temporarySignedRoute('local.temp', now()->addMinute(), ['path' => $path]);
    $response = $this->get($signedUrl);

    $response->assertOk();
    expect(strtolower($response->headers->get('content-type')))
        ->toStartWith('text/html');
    expect($response->headers->get('content-disposition'))
        ->toStartWith('attachment;');
    expect($response->headers->get('x-content-type-options'))
        ->toBe('nosniff');
    expect($response->headers->get('content-security-policy'))
        ->toContain("script-src 'none'")
        ->toContain('sandbox');
});
