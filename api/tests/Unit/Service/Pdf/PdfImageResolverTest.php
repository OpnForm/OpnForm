<?php

use App\Http\Controllers\Forms\FormController;
use App\Models\Forms\Form;
use App\Models\User;
use App\Models\Workspace;
use App\Service\Pdf\PdfImageResolver;
use App\Service\Pdf\PdfSafeImageFetcher;
use App\Service\Storage\FileUploadPathService;
use App\Service\Storage\FilenameUrlEncoder;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function () {
    Storage::fake('local');
    config(['opnform.webhooks.allow_private_urls' => false]);
});

function createPdfImageResolverTestForm(): Form
{
    $user = User::factory()->create();
    $workspace = Workspace::create(['name' => 'PDF Test Workspace', 'icon' => '📝']);
    $user->workspaces()->attach($workspace->id, ['role' => 'admin']);

    return Form::factory()
        ->forWorkspace($workspace)
        ->createdBy($user)
        ->withProperties([
            ['id' => 'name', 'name' => 'Name', 'type' => 'text'],
        ])
        ->create();
}

function pdfResolverTinyPngBytes(): string
{
    return base64_decode(
        'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/w8AAgMBAQEAAP8AAAAASUVORK5CYII=',
        true
    ) ?: '';
}

describe('PdfImageResolver', function () {
    it('does not fetch unsafe remote image urls', function () {
        Http::fake();

        $resolver = new PdfImageResolver();
        $content = $resolver->resolveContent('https://169.254.169.254/latest/meta-data/iam/security-credentials/');

        expect($content)->toBeNull();
        Http::assertNothingSent();
    });

    it('fetches safe public remote image urls when not in storage', function () {
        Http::fake([
            'https://images.unsplash.com/*' => Http::response(
                pdfResolverTinyPngBytes(),
                200,
                ['Content-Type' => 'image/png']
            ),
        ]);

        $resolver = new PdfImageResolver();
        $content = $resolver->resolveContent(
            'https://images.unsplash.com/photo-1779638715091-90c701d94efd?crop=entropy&cs=tinysrgb&fit=max&fm=jpg&q=80&w=1080'
        );

        expect($content)->toBe(pdfResolverTinyPngBytes());
        Http::assertSentCount(1);
    });

    it('does not treat unsplash photo ids as encoded storage filenames', function () {
        Http::fake([
            'https://images.unsplash.com/*' => Http::response(
                pdfResolverTinyPngBytes(),
                200,
                ['Content-Type' => 'image/png']
            ),
        ]);

        expect(FilenameUrlEncoder::isEncoded('photo-1779638715091-90c701d94efd'))->toBeFalse();

        $resolver = new PdfImageResolver();
        $content = $resolver->resolveContent(
            'https://images.unsplash.com/photo-1779638715091-90c701d94efd?fm=jpg&w=1080'
        );

        expect($content)->toBe(pdfResolverTinyPngBytes());
        Http::assertSentCount(1);
    });

    it('prefers storage over remote fetch for local asset urls', function () {
        Http::fake();
        Storage::put('assets/forms/photo.png', 'stored-image-bytes');

        $resolver = new PdfImageResolver();
        $content = $resolver->resolveContent(route('forms.assets.show', ['photo.png']));

        expect($content)->toBe('stored-image-bytes');
        Http::assertNothingSent();
    });

    it('prefers form assets over submission storage for local asset urls', function () {
        Http::fake();
        $form = createPdfImageResolverTestForm();
        $fileName = 'photo.png';
        Storage::put(FileUploadPathService::getFileUploadPath($form->id, $fileName), 'submission-bytes');
        Storage::put(FormController::ASSETS_UPLOAD_PATH . '/' . $fileName, 'asset-bytes');

        $resolver = new PdfImageResolver($form);
        $content = $resolver->resolveContent(route('forms.assets.show', [$fileName]));

        expect($content)->toBe('asset-bytes');
        Http::assertNothingSent();
    });

    it('resolves asset path urls from storage when host matches front url', function () {
        Http::fake();
        config(['app.url' => 'http://api.test']);
        config(['app.front_url' => 'http://frontend.test']);
        Storage::put(FormController::ASSETS_UPLOAD_PATH . '/photo.png', 'asset-bytes');

        $resolver = new PdfImageResolver();
        $content = $resolver->resolveContent('http://frontend.test/forms/assets/photo.png');

        expect($content)->toBe('asset-bytes');
        Http::assertNothingSent();
    });

    it('treats localhost and 127.0.0.1 as the same app origin', function () {
        Http::fake();
        config(['app.url' => 'http://localhost']);
        Storage::put(FormController::ASSETS_UPLOAD_PATH . '/photo.png', 'asset-bytes');

        $resolver = new PdfImageResolver();
        $content = $resolver->resolveContent('http://127.0.0.1/forms/assets/photo.png');

        expect($content)->toBe('asset-bytes');
        Http::assertNothingSent();
    });

    it('does not remote-fetch app-origin urls regardless of scheme', function () {
        Http::fake();
        config(['app.url' => 'http://api.test']);
        Storage::put(FormController::ASSETS_UPLOAD_PATH . '/photo.png', 'asset-bytes');

        $resolver = new PdfImageResolver();
        $content = $resolver->resolveContent('https://api.test/forms/assets/photo.png');

        expect($content)->toBe('asset-bytes');
        Http::assertNothingSent();
    });

    it('caches repeated image lookups within the same resolver instance', function () {
        Http::fake([
            'https://images.unsplash.com/*' => Http::response(
                pdfResolverTinyPngBytes(),
                200,
                ['Content-Type' => 'image/png']
            ),
        ]);

        $resolver = new PdfImageResolver();
        $url = 'https://images.unsplash.com/photo-12345?auto=format&fit=crop&w=900';

        expect($resolver->resolveContent($url))->toBe(pdfResolverTinyPngBytes());
        expect($resolver->resolveContent($url))->toBe(pdfResolverTinyPngBytes());
        Http::assertSentCount(1);
    });

    it('does not remote-fetch app-origin urls that miss storage', function () {
        Http::fake();

        $resolver = new PdfImageResolver();
        $content = $resolver->resolveContent(route('forms.assets.show', ['missing.png']));

        expect($content)->toBeNull();
        Http::assertNothingSent();
    });

    it('does not treat remote urls with asset-like paths as local', function () {
        Http::fake([
            'https://images.unsplash.com/*' => Http::response(
                pdfResolverTinyPngBytes(),
                200,
                ['Content-Type' => 'image/png']
            ),
        ]);

        $resolver = new PdfImageResolver();
        $content = $resolver->resolveContent('https://images.unsplash.com/forms/assets/photo.png');

        expect($content)->toBe(pdfResolverTinyPngBytes());
        Http::assertSentCount(1);
    });

    it('resolves uploaded form asset urls from storage', function () {
        Http::fake();
        $fileName = 'logo_550e8400-e29b-41d4-a716-446655440000.png';
        Storage::put(FormController::ASSETS_UPLOAD_PATH . '/' . $fileName, 'asset-bytes');

        $resolver = new PdfImageResolver();
        $content = $resolver->resolveContent(route('forms.assets.show', [$fileName]));

        expect($content)->toBe('asset-bytes');
        Http::assertNothingSent();
    });

    it('resolves image content from form submission storage', function () {
        $form = createPdfImageResolverTestForm();
        $fileName = 'avatar-test.png';
        Storage::put(FileUploadPathService::getFileUploadPath($form->id, $fileName), 'form-upload-bytes');

        $resolver = new PdfImageResolver($form);
        $content = $resolver->resolveContent($fileName);

        expect($content)->toBe('form-upload-bytes');
    });

    it('resolves encoded submission filenames from storage', function () {
        $form = createPdfImageResolverTestForm();
        $fileName = 'my (file)_550e8400-e29b-41d4-a716-446655440000.png';
        Storage::put(FileUploadPathService::getFileUploadPath($form->id, $fileName), 'encoded-upload-bytes');

        $resolver = new PdfImageResolver($form);
        $content = $resolver->resolveContent(FilenameUrlEncoder::encode($fileName));

        expect($content)->toBe('encoded-upload-bytes');
    });

    it('resolves encoded submission filenames containing exclamation marks', function () {
        $form = createPdfImageResolverTestForm();
        $fileName = 'Important!-document_550e8400-e29b-41d4-a716-446655440000.png';
        Storage::put(FileUploadPathService::getFileUploadPath($form->id, $fileName), 'important-upload-bytes');

        $resolver = new PdfImageResolver($form);
        $content = $resolver->resolveContent(FilenameUrlEncoder::encode($fileName));

        expect($content)->toBe('important-upload-bytes');
    });

    it('resolves encoded submission filenames containing asterisks', function () {
        $form = createPdfImageResolverTestForm();
        $fileName = 'star*file_550e8400-e29b-41d4-a716-446655440000.png';
        Storage::put(FileUploadPathService::getFileUploadPath($form->id, $fileName), 'star-upload-bytes');

        $resolver = new PdfImageResolver($form);
        $content = $resolver->resolveContent(FilenameUrlEncoder::encode($fileName));

        expect($content)->toBe('star-upload-bytes');
    });

    it('does not probe arbitrary storage keys for slash-containing values', function () {
        Storage::put('private/nested/photo.png', 'private-bytes');

        $resolver = new PdfImageResolver();
        $content = $resolver->resolveContent('private/nested/photo.png');

        expect($content)->toBeNull();
    });
});

describe('PdfSafeImageFetcher', function () {
    it('rejects non-image content types', function () {
        Http::fake([
            'https://images.unsplash.com/*' => Http::response(
                '<html></html>',
                200,
                ['Content-Type' => 'text/html']
            ),
        ]);

        $fetcher = new PdfSafeImageFetcher();
        $content = $fetcher->fetch('https://images.unsplash.com/photo-12345');

        expect($content)->toBeNull();
    });

    it('rejects private urls even when private webhook urls are allowed', function () {
        config(['opnform.webhooks.allow_private_urls' => true]);
        Http::fake();

        $fetcher = new PdfSafeImageFetcher();
        $content = $fetcher->fetch('https://127.0.0.1/internal.png');

        expect($content)->toBeNull();
        Http::assertNothingSent();
    });

    it('rejects responses whose content-length exceeds the download limit', function () {
        Http::fake([
            'https://images.unsplash.com/*' => Http::response(
                'ignored',
                200,
                [
                    'Content-Type' => 'image/png',
                    'Content-Length' => (string) (6 * 1024 * 1024),
                ]
            ),
        ]);

        $fetcher = new PdfSafeImageFetcher();
        $content = $fetcher->fetch('https://images.unsplash.com/photo-12345');

        expect($content)->toBeNull();
    });

    it('rejects response bodies that exceed the download limit', function () {
        Http::fake([
            'https://images.unsplash.com/*' => Http::response(
                str_repeat('a', (5 * 1024 * 1024) + 1),
                200,
                ['Content-Type' => 'image/png']
            ),
        ]);

        $fetcher = new PdfSafeImageFetcher();
        $content = $fetcher->fetch('https://images.unsplash.com/photo-12345');

        expect($content)->toBeNull();
    });

    it('rejects chunked response bodies that exceed the download limit', function () {
        Http::fake([
            'https://images.unsplash.com/*' => Http::response(
                str_repeat('a', (5 * 1024 * 1024) + 1),
                200,
                [
                    'Content-Type' => 'image/png',
                    'Transfer-Encoding' => 'chunked',
                ]
            ),
        ]);

        $fetcher = new PdfSafeImageFetcher();
        $content = $fetcher->fetch('https://images.unsplash.com/photo-12345');

        expect($content)->toBeNull();
    });
});
