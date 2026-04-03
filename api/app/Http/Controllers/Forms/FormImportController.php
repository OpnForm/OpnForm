<?php

namespace App\Http\Controllers\Forms;

use App\Http\Controllers\Controller;
use App\Http\Requests\FormImportRequest;
use App\Http\Resources\FormResource;
use App\Models\Forms\Form;
use App\Models\Workspace;
use App\Service\FormImport\FormImportException;
use App\Service\FormImport\FormImportService;

class FormImportController extends Controller
{
    public function __construct(
        private FormImportService $importService,
    ) {
        $this->middleware('auth');
    }

    public function import(FormImportRequest $request)
    {
        $workspace = Workspace::findOrFail($request->get('workspace_id'));
        $this->authorize('ownsWorkspace', $workspace);
        $this->authorize('create', [Form::class, $workspace]);

        try {
            $result = $this->importService->import(
                $request->get('source'),
                $request->get('import_data'),
            );
        } catch (FormImportException $e) {
            return $this->error([
                'message' => $e->getMessage(),
            ], 422);
        } catch (\Exception $e) {
            report($e);

            return $this->error([
                'message' => 'An unexpected error occurred while importing the form. Please try again.',
            ], 500);
        }

        $formData = array_merge($result, [
            'workspace_id' => $workspace->id,
            'creator_id' => $request->user()->id,
            'visibility' => 'draft',
        ]);

        $form = Form::create($formData);

        return $this->success([
            'message' => 'Form imported successfully.',
            'form' => new FormResource($form),
            'source' => $request->get('source'),
            'fields_count' => count($result['properties'] ?? []),
        ]);
    }
}
