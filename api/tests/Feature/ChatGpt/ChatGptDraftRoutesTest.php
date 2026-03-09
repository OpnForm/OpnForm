<?php

use Illuminate\Support\Facades\Route;

it('returns 404 on chatgpt paths when routes are disabled', function () {
    if (Route::has('chatgpt.drafts.create')) {
        $this->markTestSkipped('ChatGPT draft routes are enabled in this environment.');
    }

    $this->getJson('/api/chatgpt/drafts/invalid-id')->assertNotFound();
    $this->getJson('/api/mcp')->assertNotFound();
});

it('creates and fetches a chatgpt draft when routes are enabled', function () {
    if (!Route::has('chatgpt.drafts.create')) {
        $this->markTestSkipped('ChatGPT draft routes are disabled in this environment.');
    }

    $createResponse = $this->postJson(route('chatgpt.drafts.create'), [
        'form_state' => ['title' => 'My GPT Form', 'properties' => []],
    ])->assertSuccessful();

    $chatId = $createResponse->json('draft.gpt_chat_id');
    expect($chatId)->not->toBeEmpty();
    expect(\Illuminate\Support\Str::isUuid($chatId))->toBeTrue();

    $this->getJson(route('chatgpt.drafts.show', ['gptChatId' => $chatId]))->assertSuccessful();
});
