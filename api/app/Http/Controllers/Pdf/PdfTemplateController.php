<?php

namespace App\Http\Controllers\Pdf;

use App\Exceptions\PdfNotSupportedException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Pdf\CreatePdfTemplateRequest;
use App\Http\Requests\Pdf\UpdatePdfTemplateRequest;
use App\Models\Forms\Form;
use App\Models\PdfTemplate;
use App\Service\Pdf\PdfTemplateRebuildService;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use setasign\Fpdi\Fpdi;
use setasign\Fpdi\PdfParser\CrossReference\CrossReferenceException;

class PdfTemplateController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * List all PDF templates for a form.
     */
    public function index(Form $form)
    {
        $this->authorize('view', $form);

        return response()->json([
            'data' => $form->pdfTemplates()->get(),
        ]);
    }

    /**
     * Upload a new PDF template.
     */
    public function store(CreatePdfTemplateRequest $request, Form $form)
    {
        $this->authorize('update', $form);

        $uuid = (string) Str::uuid();
        $filename = $uuid . '.pdf';
        $path = "pdf-templates/{$form->id}/{$filename}";
        $file = $request->file('file');

        if ($file) {
            try {
                $pageCount = $this->getPageCount($file->getRealPath());
            } catch (PdfNotSupportedException $e) {
                return response()->json([
                    'message' => $e->getMessage(),
                    'errors' => [
                        'file' => [$e->getMessage()],
                    ],
                ], 422);
            }
            Storage::put($path, file_get_contents($file->getRealPath()));
            $fileSize = $file->getSize();
            $originalFilename = $file->getClientOriginalName();
            $message = 'PDF template uploaded successfully. Let\'s customize as per your needs.';
        } else {
            $pdf = new Fpdi();
            $pdf->AddPage();
            $pdfContent = $pdf->Output('S');

            Storage::put($path, $pdfContent);
            $pageCount = 1;
            $fileSize = strlen($pdfContent);
            $originalFilename = $filename;
            $message = 'PDF template created. Let\'s customize as per your needs.';
        }

        $templateName = $request->input('name') ?: PdfTemplate::generateDefaultTemplateName($form->id);

        $template = PdfTemplate::create([
            'form_id' => $form->id,
            'name' => $templateName,
            'filename' => $filename,
            'original_filename' => $originalFilename,
            'file_path' => $path,
            'file_size' => $fileSize,
            'page_count' => $pageCount,
            'page_manifest' => PdfTemplate::buildDefaultPageManifest($pageCount),
            'zone_mappings' => [],
            'filename_pattern' => PdfTemplate::DEFAULT_FILENAME_PATTERN,
            'remove_branding' => false,
        ]);

        return response()->json([
            'message' => $message,
            'data' => $template,
        ], 201);
    }

    /**
     * Get a specific PDF template.
     */
    public function show(Form $form, PdfTemplate $pdfTemplate)
    {
        $this->authorize('view', $form);

        // Ensure template belongs to form
        if ($pdfTemplate->form_id !== $form->id) {
            abort(404);
        }

        return response()->json([
            'data' => $pdfTemplate,
        ]);
    }

    /**
     * Update a PDF template (zone mappings, name, filename pattern, branding).
     * If page_manifest is sent, the stored PDF file is rebuilt to match the explicit logical page order/content.
     */
    public function update(UpdatePdfTemplateRequest $request, Form $form, PdfTemplate $pdfTemplate)
    {
        $this->authorize('update', $form);

        if ($pdfTemplate->form_id !== $form->id) {
            abort(404);
        }

        $validated = $request->validated();
        $pageManifest = $validated['page_manifest'] ?? null;

        // Rebuild PDF file from explicit page manifest order/content.
        if (is_array($pageManifest) && !empty($pageManifest)) {
            try {
                $service = app(PdfTemplateRebuildService::class);
                $newContent = $service->rebuildFromManifest(
                    $pdfTemplate->file_path,
                    $pageManifest
                );
                Storage::put($pdfTemplate->file_path, $newContent);
            } catch (PdfNotSupportedException $e) {
                return response()->json([
                    'message' => $e->getMessage(),
                    'errors' => ['file' => [$e->getMessage()]],
                ], 422);
            }
            $validated['page_count'] = count($pageManifest);
        }
        $pdfTemplate->update($validated);

        return response()->json([
            'message' => 'PDF template updated successfully.',
            'data' => $pdfTemplate->fresh(),
        ]);
    }

    /**
     * Delete a PDF template.
     */
    public function destroy(Form $form, PdfTemplate $pdfTemplate)
    {
        $this->authorize('update', $form);

        // Ensure template belongs to form
        if ($pdfTemplate->form_id !== $form->id) {
            abort(404);
        }

        // Check if template is in use by any integration
        if ($pdfTemplate->isInUse()) {
            return response()->json([
                'message' => 'Template already in use, cannot be deleted.'
            ], 422);
        }

        // Delete file from storage
        if (Storage::exists($pdfTemplate->file_path)) {
            Storage::delete($pdfTemplate->file_path);
        }

        $pdfTemplate->delete();

        return response()->json([
            'message' => 'PDF template deleted successfully.',
        ]);
    }

    /**
     * Download the PDF template file.
     */
    public function download(Form $form, PdfTemplate $pdfTemplate)
    {
        $this->authorize('view', $form);

        // Ensure template belongs to form
        if ($pdfTemplate->form_id !== $form->id) {
            abort(404);
        }

        if (!Storage::exists($pdfTemplate->file_path)) {
            abort(404, 'PDF template file not found.');
        }

        return Storage::download(
            $pdfTemplate->file_path,
            $pdfTemplate->original_filename,
            ['Content-Type' => 'application/pdf']
        );
    }

    /**
     * Get page count from PDF using FPDI (pure PHP, Vapor-compatible).
     *
     * @throws PdfNotSupportedException
     */
    private function getPageCount(string $filePath): int
    {
        try {
            // Use setasign/fpdi to count pages (pure PHP)
            $pdf = new \setasign\Fpdi\Fpdi();

            return $pdf->setSourceFile($filePath);
        } catch (CrossReferenceException $e) {
            // This exception is thrown for PDFs with unsupported compression (PDF 1.5+)
            throw new PdfNotSupportedException();
        } catch (\Exception $e) {
            // Default to 1 if we can't read the page count for other reasons
            return 1;
        }
    }
}
