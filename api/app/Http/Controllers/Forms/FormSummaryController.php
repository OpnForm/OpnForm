<?php

namespace App\Http\Controllers\Forms;

use App\Http\Controllers\Controller;
use App\Http\Requests\FormSummaryRequest;
use App\Models\Forms\Form;
use App\Models\Workspace;
use App\Service\Forms\FormSummaryService;
use Illuminate\Http\JsonResponse;

class FormSummaryController extends Controller
{
    public function __construct(
        private FormSummaryService $summaryService
    ) {
        $this->middleware('auth');
    }

    public function getSummary(FormSummaryRequest $request, Workspace $workspace, Form $form): JsonResponse
    {
        $this->authorize('view', $form);

        $summary = $this->summaryService->generateSummary(
            $form,
            $request->getDateFrom(),
            $request->getDateTo(),
            $request->getStatus()
        );

        return response()->json($summary);
    }

    public function getFieldValues(FormSummaryRequest $request, Workspace $workspace, Form $form, string $fieldId): JsonResponse
    {
        $this->authorize('view', $form);

        $values = $this->summaryService->getFieldTextValues(
            $form,
            $fieldId,
            $request->getOffset(),
            $request->getDateFrom(),
            $request->getDateTo(),
            $request->getStatus()
        );

        if ($values === null) {
            return response()->json(['error' => 'Field not found'], 404);
        }

        return response()->json($values);
    }
}
