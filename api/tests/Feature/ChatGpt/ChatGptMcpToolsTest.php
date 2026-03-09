<?php

use Illuminate\Support\Facades\Route;

it('lists render_draft_preview in tools list when mcp is enabled', function () {
    if (! Route::has('chatgpt.mcp')) {
        $this->markTestSkipped('ChatGPT MCP route is disabled in this environment.');
    }

    $response = $this->postJson('/api/mcp', [
        'jsonrpc' => '2.0',
        'id' => 'tools-list-1',
        'method' => 'tools/list',
        'params' => new stdClass(),
    ])->assertOk();

    $toolNames = collect($response->json('result.tools'))->pluck('name')->values()->all();
    expect($toolNames)->toContain('render_draft_preview');
});

it('enforces guide_token for create_draft and returns _meta ui on success', function () {
    if (! Route::has('chatgpt.mcp')) {
        $this->markTestSkipped('ChatGPT MCP route is disabled in this environment.');
    }

    $missingToken = $this->postJson('/api/mcp', [
        'jsonrpc' => '2.0',
        'id' => 'create-no-token',
        'method' => 'tools/call',
        'params' => [
            'name' => 'create_draft',
            'arguments' => [
                'form_state' => [
                    'title' => 'Test Form',
                    'properties' => [],
                    're_fillable' => false,
                    'use_captcha' => false,
                    'redirect_url' => null,
                    'submitted_text' => 'Thanks!',
                    'uppercase_labels' => false,
                    'submit_button_text' => 'Submit',
                    're_fill_button_text' => 'Fill Again',
                    'color' => '#64748b',
                ],
            ],
        ],
    ])->assertOk();

    expect($missingToken->json('error.message'))->toContain('guide_token');

    $guideResponse = $this->postJson('/api/mcp', [
        'jsonrpc' => '2.0',
        'id' => 'guide-1',
        'method' => 'tools/call',
        'params' => [
            'name' => 'get_form_generation_guide',
            'arguments' => [
                'presentation_style' => 'classic',
            ],
        ],
    ])->assertOk();

    $guideToken = $guideResponse->json('result.structuredContent.guide_token');
    expect($guideToken)->not->toBeEmpty();

    $createResponse = $this->postJson('/api/mcp', [
        'jsonrpc' => '2.0',
        'id' => 'create-with-token',
        'method' => 'tools/call',
        'params' => [
            'name' => 'create_draft',
            'arguments' => [
                'guide_token' => $guideToken,
                'form_state' => [
                    'title' => 'Test Form',
                    'properties' => [],
                    're_fillable' => false,
                    'use_captcha' => false,
                    'redirect_url' => null,
                    'submitted_text' => 'Thanks!',
                    'uppercase_labels' => false,
                    'submit_button_text' => 'Submit',
                    're_fill_button_text' => 'Fill Again',
                    'color' => '#64748b',
                ],
            ],
        ],
    ])->assertOk();

    expect($createResponse->json('result.structuredContent.draft.gpt_chat_id'))->not->toBeEmpty();
    expect($createResponse->json('result._meta.openai/outputTemplate'))->not->toBeEmpty();
    expect($createResponse->json('result._meta.ui.resourceUri'))->toContain('embed=chatgpt');
});
