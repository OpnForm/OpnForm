<?php

namespace App\Http\Controllers;

use App\Http\Resources\FormResource;
use App\Models\Forms\AgentFormDraft;
use App\Models\Workspace;
use App\Service\Forms\AgentFormDraftService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AgentFormDraftController extends Controller
{
    public const SESSION_HEADER = 'x-agent-draft-session';

    public function preview(AgentFormDraft $draft, AgentFormDraftService $drafts): JsonResponse
    {
        abort_unless($draft->isAvailable(), Response::HTTP_NOT_FOUND);

        return response()->json([
            'draft' => $drafts->serialize($draft),
        ])->header('Cache-Control', 'private, no-store, max-age=0');
    }

    public function consume(Request $request, AgentFormDraftService $drafts): JsonResponse
    {
        $this->assertTrustedFrontend($request);
        $validated = $request->validate([
            'handoff_token' => ['required', 'string', 'size:43'],
        ]);
        $result = $drafts->consumeEditorHandoff($validated['handoff_token']);

        return response()->json([
            'editor_session' => $result['editor_session'],
            'draft' => $drafts->serialize($result['draft']),
        ])->header('Cache-Control', 'private, no-store, max-age=0');
    }

    public function current(Request $request, AgentFormDraftService $drafts): JsonResponse
    {
        $this->assertTrustedFrontend($request);

        return response()->json([
            'draft' => $drafts->serialize($drafts->getForEditor($this->editorSession($request))),
        ])->header('Cache-Control', 'private, no-store, max-age=0');
    }

    public function replace(Request $request, AgentFormDraftService $drafts): JsonResponse
    {
        $this->assertTrustedFrontend($request);
        $validated = $request->validate([
            'expected_version' => ['required', 'integer', 'min:1'],
            'definition' => ['required', 'array'],
        ]);

        $draft = $drafts->replaceFromEditor(
            $this->editorSession($request),
            $validated['expected_version'],
            $validated['definition'],
        );

        return response()->json(['draft' => $drafts->serialize($draft)]);
    }

    public function claim(Request $request, AgentFormDraftService $drafts): JsonResponse
    {
        $this->assertTrustedFrontend($request);
        $validated = $request->validate([
            'expected_version' => ['required', 'integer', 'min:1'],
            'workspace_id' => ['required', 'integer', 'exists:workspaces,id'],
        ]);
        $workspace = Workspace::query()->findOrFail($validated['workspace_id']);
        $claimed = $drafts->claim(
            $this->editorSession($request),
            $validated['expected_version'],
            $workspace,
            $request->user(),
        );

        return response()->json([
            'form' => (new FormResource($claimed['form']))->setCleanings($claimed['cleanings']),
            'cleanings' => $claimed['cleanings'],
            'already_claimed' => $claimed['already_claimed'],
            'editor_url' => front_url('/forms/'.$claimed['form']->slug.'/edit'),
        ]);
    }

    private function assertTrustedFrontend(Request $request): void
    {
        $configuredSecret = (string) config('app.front_api_secret');
        $providedSecret = (string) $request->header('x-api-secret');

        abort_unless(
            $configuredSecret !== '' && hash_equals($configuredSecret, $providedSecret),
            Response::HTTP_FORBIDDEN,
        );
    }

    private function editorSession(Request $request): string
    {
        return (string) $request->header(self::SESSION_HEADER);
    }
}
