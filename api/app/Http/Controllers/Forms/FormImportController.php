<?php

namespace App\Http\Controllers\Forms;

use App\Http\Controllers\Controller;
use App\Http\Requests\FormImportRequest;
use App\Service\FormImport\FormImportException;
use App\Service\FormImport\FormImportService;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Support\Facades\Auth;

class FormImportController extends Controller
{
    public function __construct(
        private FormImportService $importService,
    ) {
    }

    public function import(FormImportRequest $request)
    {
        $this->hydrateAuthenticatedUserIfPresent();

        if ($request->get('source') === 'google_forms' && ! Auth::check()) {
            throw new AuthenticationException('Please log in to import from Google Forms.');
        }

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

        return $this->success([
            'message' => 'Form imported successfully! Feel free to customize it to your needs before publishing.',
            'form' => $result,
            'source' => $request->get('source'),
            'fields_count' => count($result['properties'] ?? []),
        ]);
    }

    private function hydrateAuthenticatedUserIfPresent(): void
    {
        if (Auth::check()) {
            return;
        }

        if (Auth::guard('api')->check()) {
            Auth::shouldUse('api');
            return;
        }

        if (Auth::guard('sanctum')->check()) {
            Auth::setUser(Auth::guard('sanctum')->user());
        }
    }
}
