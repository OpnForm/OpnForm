<?php

use App\Service\Forms\FormDataNormalizer;

uses(Tests\TestCase::class);

it('uses one normalization path for form titles, properties, and select options', function () {
    $normalized = app(FormDataNormalizer::class)->normalize([
        'title' => '  My form  ',
        'properties' => [
            [
                'name' => '  <strong>Choice</strong>  ',
                'type' => 'select',
                'help' => '<script>alert(1)</script><p>Pick one</p>',
                'select' => [
                    'options' => [
                        ['name' => 'First'],
                    ],
                ],
            ],
        ],
    ], backfillPropertyIds: true);

    expect($normalized['title'])->toBe('My form')
        ->and($normalized['properties'][0]['name'])->toBe('Choice')
        ->and($normalized['properties'][0]['help'])->not->toContain('<script>')
        ->and($normalized['properties'][0]['select']['options'][0]['id'])->toBe('First')
        ->and($normalized['properties'][0]['id'])->toBeString()->not->toBeEmpty();
});
