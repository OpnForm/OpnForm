<?php

use App\Models\Forms\Form;
use App\Service\Forms\FormCleaner;

uses(Tests\TestCase::class);

it('sanitizes raw MCP form data with the same common cleaner', function () {
    $cleaned = app(FormCleaner::class)->processData([
        'title' => 'MCP draft',
        'properties' => [
            [
                'id' => 'intro',
                'name' => 'Introduction',
                'type' => 'nf-text',
                'content' => '<script>alert(1)</script><p>Safe introduction</p>',
            ],
            [
                'id' => 'custom-code',
                'name' => 'Custom code',
                'type' => 'nf-code',
                'content' => '<script>window.example = true</script>',
            ],
        ],
    ])->getData();

    expect($cleaned['properties'])->toHaveCount(1)
        ->and($cleaned['properties'][0]['content'])->not->toContain('<script>')
        ->and($cleaned['properties'][0]['content'])->toContain('Safe introduction');
});

it('keeps custom code when the target form has a custom domain', function () {
    $form = new Form(['custom_domain' => 'forms.example.test']);

    $cleaned = app(FormCleaner::class)->processData([
        'properties' => [
            [
                'id' => 'custom-code',
                'name' => 'Custom code',
                'type' => 'nf-code',
                'content' => '<script>window.example = true</script>',
            ],
        ],
    ], $form)->getData();

    expect($cleaned['properties'])->toHaveCount(1)
        ->and($cleaned['properties'][0]['type'])->toBe('nf-code');
});
