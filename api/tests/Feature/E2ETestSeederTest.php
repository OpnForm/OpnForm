<?php

use App\Models\Forms\Form;
use App\Models\Forms\FormSubmission;
use App\Models\User;
use App\Service\Storage\FileUploadPathService;
use Database\Seeders\E2ETestSeeder;
use Illuminate\Support\Facades\Storage;

it('creates the Codex admin with realistic forms and submissions', function () {
    config(['opnform.admin_emails' => ['e2e@example.test']]);

    $this->seed(E2ETestSeeder::class);

    $user = User::query()->where('email', 'e2e@example.test')->firstOrFail();
    $uploadForm = Form::query()->where('title', 'File upload sample')->firstOrFail();
    $uploadSubmission = FormSubmission::query()->where('form_id', $uploadForm->id)->firstOrFail();
    $fileProperty = collect($uploadForm->properties)->firstWhere('name', 'Reference image');
    $fileName = 'opnform-logo.png';

    expect($user->admin)->toBeTrue()
        ->and($user->workspaces()->wherePivot('role', User::ROLE_ADMIN)->exists())->toBeTrue()
        ->and(Form::query()->pluck('title')->all())->toEqualCanonicalizing([
            'Product feedback',
            'Book a product demo',
            'File upload sample',
            'Website redesign intake',
        ])
        ->and(Form::query()->where('visibility', 'public')->count())->toBe(3)
        ->and(FormSubmission::query()->count())->toBe(6)
        ->and($fileProperty['type'])->toBe('files')
        ->and($uploadSubmission->data[$fileProperty['id']])->toBe([$fileName])
        ->and(Storage::exists(FileUploadPathService::getFileUploadPath($uploadForm->id, $fileName)))->toBeTrue();
});
