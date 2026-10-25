<?php

use App\Service\Forms\AgentFormDefinition;
use App\Service\Forms\AgentFormQualityAnalyzer;

uses(Tests\TestCase::class);

it('returns actionable warnings with severity for a minimal machine-like form', function () {
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
    )->and($warnings)->each->toHaveKeys(['code', 'message', 'path', 'blocking'])
        ->and($warnings)->toContainEqual([
            'code' => 'machine_like_label',
            'message' => 'Replace the raw label [name] with clear respondent-facing copy in sentence case.',
            'path' => 'properties.0.name',
            'blocking' => true,
        ]);
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

it('flags markdown in text blocks because OpnForm renders sanitized HTML', function () {
    $definition = app(AgentFormDefinition::class)->normalizeAndValidate([
        'title' => 'Contact requests',
        'properties' => [
            [
                'name' => 'Introduction',
                'type' => 'nf-text',
                'content' => '# Contact us\n\n**Tell us how we can help.**',
            ],
            ['name' => 'Email address', 'type' => 'email'],
        ],
    ]);

    expect(app(AgentFormQualityAnalyzer::class)->analyze($definition))
        ->toContainEqual([
            'code' => 'markdown_text_content',
            'message' => 'Replace Markdown with sanitized HTML in nf-text content, for example <h1>Contact us</h1><p>How can we help?</p>.',
            'path' => 'properties.0.content',
            'blocking' => true,
        ]);
});
