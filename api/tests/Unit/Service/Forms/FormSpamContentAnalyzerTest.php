<?php

use App\Models\Forms\Form;
use App\Service\Forms\FormSpamContentAnalyzer;

uses(\Tests\TestCase::class);

function makeSpamAnalyzerForm(array $properties): Form
{
    return (new Form())->forceFill([
        'title' => 'Contact Form',
        'description' => null,
        'submit_button_text' => 'Submit',
        'submitted_text' => '<p>Thanks</p>',
        'properties' => $properties,
    ]);
}

it('scans visible rich content, help links, and image URLs', function () {
    $form = makeSpamAnalyzerForm([
        [
            'id' => 'image',
            'type' => 'nf-image',
            'name' => 'Image',
            'hidden' => false,
            'image_block' => 'https://api.opnform.com/forms/assets/BT-SPAM.png',
        ],
        [
            'id' => 'text',
            'type' => 'nf-text',
            'name' => 'Text',
            'hidden' => false,
            'content' => '<p>Securely sign in with your BT email address.</p><p><a href="https://patient-hands-046879.framer.app/">CLICK HERE TO LISTEN</a></p>',
        ],
        [
            'id' => 'hidden',
            'type' => 'text',
            'name' => 'Hidden bait',
            'hidden' => true,
            'content' => 'this should not be rendered',
        ],
    ]);

    $text = (new FormSpamContentAnalyzer())->keywordScanText($form);

    expect($text)
        ->toContain('Securely sign in with your BT email address')
        ->toContain('https://patient-hands-046879.framer.app/')
        ->toContain('https://api.opnform.com/forms/assets/BT-SPAM.png')
        ->not->toContain('this should not be rendered');
});

it('counts only active input fields', function () {
    $form = makeSpamAnalyzerForm([
        [
            'id' => 'visible-email',
            'type' => 'email',
            'name' => 'Email',
            'hidden' => false,
        ],
        [
            'id' => 'disabled-url',
            'type' => 'url',
            'name' => 'Continue',
            'hidden' => false,
            'disabled' => true,
            'help' => '<a href="https://btmmm-108027.weeblysite.com/">CLICK HERE TO CONTINUE</a>',
        ],
        [
            'id' => 'hidden-text',
            'type' => 'text',
            'name' => 'Hidden',
            'hidden' => true,
        ],
        [
            'id' => 'display-text',
            'type' => 'nf-text',
            'name' => 'Instructions',
            'hidden' => false,
            'content' => '<p>Visible instructions</p>',
        ],
        [
            'id' => 'visible-rich-text',
            'type' => 'rich_text',
            'name' => 'Message',
            'hidden' => false,
        ],
    ]);

    expect((new FormSpamContentAnalyzer())->activeInputFieldCount($form))->toBe(2);
});

it('keeps disabled visible help in the prompt without counting it as active', function () {
    $form = makeSpamAnalyzerForm([
        [
            'id' => 'disabled-url',
            'type' => 'url',
            'name' => '*',
            'hidden' => false,
            'disabled' => true,
            'help' => '<a href="https://btmmm-108027.weeblysite.com/">CLICK HERE TO CONTINUE</a>',
        ],
    ]);

    $promptContent = (new FormSpamContentAnalyzer())->promptContent($form);

    expect($promptContent)
        ->toContain('Active input fields: 0')
        ->toContain('Disabled: yes')
        ->toContain('CLICK HERE TO CONTINUE')
        ->toContain('https://btmmm-108027.weeblysite.com/');
});
