<?php

namespace App\Http\Controllers\Pdf;

use App\Exceptions\PdfNotSupportedException;
use App\Http\Controllers\Controller;
use App\Models\Forms\Form;
use App\Models\PdfTemplate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
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
            'file' => 'required|file|mimes:pdf|max:10240', // 10MB max
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

        $template = PdfTemplate::create([
            'form_id' => $form->id,
            'filename' => $filename,
            'original_filename' => $file->getClientOriginalName(),
            'file_path' => $path,
            'file_size' => $file->getSize(),
            'page_count' => $pageCount,
        ]);

        return response()->json([
            'message' => 'PDF template uploaded successfully.',
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
     * Delete a PDF template.
     */
    public function destroy(Form $form, PdfTemplate $pdfTemplate)
    {
        $this->authorize('update', $form);

        // Ensure template belongs to form
        if ($pdfTemplate->form_id !== $form->id) {
            abort(404);
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
