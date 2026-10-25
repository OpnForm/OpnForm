<?php

use App\Models\Forms\AI\AiFormCompletion;
use Illuminate\Support\Facades\Bus;

it('requires the completion secret even for callers sharing the same IP', function () {
    Bus::fake();

    $response = $this->postJson(route('forms.ai.generate'), ['form_prompt' => 'A contact form'])->assertSuccessful();
    $id = $response->json('ai_form_completion_id');
    $token = $response->json('ai_form_completion_token');
    expect($token)->toBeString()->toHaveLength(64);
    $this->getJson(route('forms.ai.show', $id))->assertForbidden();
    $this->getJson(route('forms.ai.show', $id), ['X-AI-Completion-Token' => 'wrong'])->assertForbidden();
    $this->getJson(route('forms.ai.show', $id), ['X-AI-Completion-Token' => $token])->assertOk()
        ->assertJsonMissingPath('ai_form_completion.access_token_hash');
    $completion = AiFormCompletion::findOrFail($id);
    $completion->update(['result' => ['properties' => [['id' => 'field']]]]);
    expect($completion->fresh()->result)->toBe(['properties' => [['id' => 'field']]]);
});
