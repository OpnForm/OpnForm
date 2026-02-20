<?php

namespace App\Http\Controllers\Pdf;

use App\Exceptions\PdfNotSupportedException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Pdf\UpdatePdfTemplateRequest;
use App\Models\Forms\Form;
use App\Models\PdfTemplate;
use App\Service\Pdf\PdfTemplateRebuildService;
use Illuminate\Http\Request;
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
    public function store(Request $request, Form $form)
    {
        $this->authorize('update', $form);

        $request->validate([
            'file' => 'required|file|mimes:pdf|max:5120', // 5MB max
            'name' => 'nullable|string|max:255',
        ]);

        $file = $request->file('file');

        // Validate PDF compatibility before storing (catches unsupported compression early)
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

        $uuid = (string) Str::uuid();
        $filename = $uuid . '.pdf';
        $path = "pdf-templates/{$form->id}/{$filename}";

        // Store the file
        Storage::put($path, file_get_contents($file->getRealPath()));

        // Use provided name or generate from original filename
        $originalName = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        $templateName = $request->input('name') ?: $originalName;

        $template = PdfTemplate::create([
            'form_id' => $form->id,
            'name' => $templateName,
            'filename' => $filename,
            'original_filename' => $file->getClientOriginalName(),
            'file_path' => $path,
            'file_size' => $file->getSize(),
            'page_count' => $pageCount,
            'zone_mappings' => [],
            'filename_pattern' => '{form_name}-{submission_id}.pdf',
            'remove_branding' => false,
        ]);

        return response()->json([
            'message' => 'PDF template uploaded successfully. Let\'s customize as per your needs.',
            'data' => $template,
        ], 201);
    }

    /**
     * Create a new PDF template from scratch (1 blank page).
     */
    public function storeFromScratch(Request $request, Form $form)
    {
        $this->authorize('update', $form);

        $pdf = new Fpdi();
        $pdf->AddPage();
        $pdfContent = $pdf->Output('S');

        $filename = time() . '.pdf';
        $path = "pdf-templates/{$form->id}/{$filename}";

        Storage::put($path, $pdfContent);

        $template = PdfTemplate::create([
            'form_id' => $form->id,
            'name' => 'Untitled Template',
            'filename' => $filename,
            'original_filename' => $filename,
            'file_path' => $path,
            'file_size' => strlen($pdfContent),
            'page_count' => 1,
            'zone_mappings' => [],
            'filename_pattern' => '{form_name}-{submission_id}.pdf',
            'remove_branding' => false,
        ]);

        return response()->json([
            'message' => 'PDF template created. Let\'s customize as per your needs.',
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
     * If new_pages or removed_pages are sent, the stored PDF file is rebuilt (add/remove pages) then not stored in DB.
     */
    public function update(UpdatePdfTemplateRequest $request, Form $form, PdfTemplate $pdfTemplate)
    {
        $this->authorize('update', $form);

        if ($pdfTemplate->form_id !== $form->id) {
            abort(404);
        }

        $validated = $request->validated();
        $newPages = $validated['new_pages'] ?? [];
        $removedPages = $validated['removed_pages'] ?? [];
        $targetPageCount = (int) ($validated['page_count'] ?? $pdfTemplate->page_count);

        // Rebuild PDF file when add/remove pages (apply to file only; do not store in DB)
        if (! empty($newPages) || ! empty($removedPages)) {
            try {
                $service = app(PdfTemplateRebuildService::class);
                $newContent = $service->rebuild(
                    $pdfTemplate->file_path,
                    $removedPages,
                    $newPages,
                    $targetPageCount
                );
                Storage::put($pdfTemplate->file_path, $newContent);
            } catch (PdfNotSupportedException $e) {
                return response()->json([
                    'message' => $e->getMessage(),
                    'errors' => ['file' => [$e->getMessage()]],
                ], 422);
            }
        }
        unset($validated['new_pages'], $validated['removed_pages']);
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
