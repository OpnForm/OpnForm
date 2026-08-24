<?php

use App\Service\Forms\AgentFormDefinition;
use App\Service\Forms\AgentFormQualityAnalyzer;

uses(Tests\TestCase::class);

it('returns actionable non-blocking warnings for a minimal machine-like form', function () {
    $definition = app(AgentFormDefinition::class)->normalizeAndValidate([
        'title' => 'Contact form',
        'properties' => [
            ['name' => 'name', 'type' => 'text'],
            ['name' => 'email', 'type' => 'email'],
            ['name' => 'message', 'type' => 'text'],
        ],
    ]);

    $warnings = app(AgentFormQualityAnalyzer::class)->analyze($definition);
    $codes = collect($warnings)->pluck('code');

    expect($codes)->toContain(
        'missing_visible_intro',
        'machine_like_label',
        'missing_helpful_placeholder',
        'long_answer_should_be_multiline',
        'generic_submit_label',
        'generic_completion_message',
    )->and($warnings)->each->toHaveKeys(['code', 'message', 'path']);
});

it('does not warn for a polished contact form', function () {
    $definition = app(AgentFormDefinition::class)->normalizeAndValidate([
        'title' => 'Contact requests',
        'submit_button_text' => 'Send message',
        'submitted_text' => '<p>Thanks — your message has been sent. We will get back to you soon.</p>',
        'properties' => [
            [
                'name' => 'Introduction',
                'type' => 'nf-text',
                'content' => '<h1>Contact us</h1><p>Tell us how we can help.</p>',
            ],
            ['name' => 'Full name', 'type' => 'text', 'placeholder' => 'Jane Smith', 'width' => '1/2'],
            ['name' => 'Email address', 'type' => 'email', 'placeholder' => 'jane@company.com', 'width' => '1/2'],
            [
                'name' => 'How can we help?',
                'type' => 'text',
                'multi_lines' => true,
                'placeholder' => 'Tell us a little about your request',
            ],
        ],
    ]);

    expect(app(AgentFormQualityAnalyzer::class)->analyze($definition))->toBe([]);
});
