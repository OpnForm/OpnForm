<?php

namespace App\Http\Controllers\Forms;

use App\Http\Controllers\Controller;
use App\Service\AI\Mcp\ChatGptDraftsService;
use Illuminate\Http\Request;
use RuntimeException;

class ChatGptDraftController extends Controller
{
    public function __construct(
        private readonly ChatGptDraftsService $drafts
    ) {
    }

    public function create(Request $request)
    {
        $validated = $request->validate([
            'form_state' => ['nullable', 'array'],
        ]);

        $draft = $this->drafts->create($validated['form_state'] ?? []);

        return $this->success([
            'draft' => $this->drafts->serialize($draft),
        ]);
    }

    public function show(string $gptChatId)
    {
        try {
            $draft = $this->drafts->fetch($gptChatId);
        } catch (RuntimeException) {
            abort(404);
        }

        return $this->success([
            'draft' => $this->drafts->serialize($draft),
        ]);
    }

    public function update(Request $request, string $gptChatId)
    {
        $validated = $request->validate([
            'form_state' => ['required', 'array'],
        ]);

        try {
            $draft = $this->drafts->update($gptChatId, $validated['form_state']);
        } catch (RuntimeException) {
            abort(404);
        }

        return $this->success([
            'draft' => $this->drafts->serialize($draft),
        ]);
    }

    public function handoff(string $gptChatId)
    {
        try {
            $draft = $this->drafts->handoff($gptChatId);
        } catch (RuntimeException) {
            abort(404);
        }

        return $this->success([
            'takeover_url' => front_url('forms/create/guest?gpt_chat_id=' . urlencode($draft->gpt_chat_id)),
            'draft' => $this->drafts->serialize($draft),
        ]);
    }
}
