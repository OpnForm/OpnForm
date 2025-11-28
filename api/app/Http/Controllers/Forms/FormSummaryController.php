<?php

namespace App\Http\Controllers\Forms;

use App\Http\Controllers\Controller;
use App\Http\Requests\FormSummaryRequest;
use App\Models\Forms\Form;
use App\Models\Workspace;
use App\Service\Forms\FormSummaryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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

    public function getFieldValues(Request $request, Workspace $workspace, Form $form, string $fieldId): JsonResponse
    {
        $this->authorize('view', $form);

        $request->validate([
            'offset' => ['nullable', 'integer', 'min:0'],
            'status' => ['nullable', 'in:all,completed,partial'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date'],
        ]);

        $values = $this->summaryService->getFieldTextValues(
            $form,
            $fieldId,
            (int) $request->input('offset', 0),
            $request->input('date_from'),
            $request->input('date_to'),
            $request->input('status', 'completed')
        );

        if ($values === null) {
            return response()->json(['error' => 'Field not found'], 404);
        }

        return response()->json($values);
    }
}

