<?php

use App\Models\Forms\Form;
use App\Models\Integration\FormIntegration;
use App\Service\AI\Prompts\Integration\CheckSpamEmailIntegrationPrompt;

uses(\Tests\TestCase::class);

it('treats default forms with only hidden, disabled, or display blocks as dummy forms', function () {
    $form = (new Form())->forceFill([
        'title' => 'Contact Form',
        'description' => null,
        'properties' => [
            [
                'id' => 'disabled-url',
                'type' => 'url',
                'name' => 'Continue',
                'disabled' => true,
                'hidden' => false,
            ],
            [
                'id' => 'hidden-email',
                'type' => 'email',
                'name' => 'Email',
                'hidden' => true,
            ],
            [
                'id' => 'image',
                'type' => 'nf-image',
                'name' => 'Image',
                'hidden' => false,
            ],
            [
                'id' => 'text',
                'type' => 'nf-text',
                'name' => 'Text',
                'hidden' => false,
            ],
        ],
    ]);

    $prompt = new CheckSpamEmailIntegrationPrompt($form, new FormIntegration());
    $method = new ReflectionMethod(CheckSpamEmailIntegrationPrompt::class, 'isDefaultDummyForm');
    $method->setAccessible(true);

    expect($method->invoke($prompt))->toBeTrue();
});
