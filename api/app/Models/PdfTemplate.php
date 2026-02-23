<?php

namespace App\Models;

use App\Models\Forms\Form;
use App\Models\Forms\FormSubmission;
use App\Models\Integration\FormIntegration;
use App\Open\MentionParser;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class PdfTemplate extends Model
{
    use HasFactory;
    use SoftDeletes;

    public const DEFAULT_FILENAME_PATTERN = '<span mention="true" mention-field-id="form_name" mention-field-name="Form Name" mention-fallback="" contenteditable="false" class="mention-item">Form Name</span>-<span mention="true" mention-field-id="submission_id" mention-field-name="Submission ID" mention-fallback="" contenteditable="false" class="mention-item">Submission ID</span>';

    protected $fillable = [
        'form_id',
        'name',
        'filename',
        'original_filename',
        'file_path',
        'file_size',
        'page_count',
        'zone_mappings',
        'filename_pattern',
        'remove_branding',
    ];

    protected function casts(): array
    {
        return [
            'file_size' => 'integer',
            'page_count' => 'integer',
            'zone_mappings' => 'array',
            'remove_branding' => 'boolean',
        ];
    }

    /**
     * Check if this template is in use
     * - by any form (e.g., selected as success page download template)
     * - by any integration (e.g., selected as PDF template for email notification).
     */
    public function isInUse(): bool
    {
        // Check if any form uses this template for success page download
        $isInUse = Form::where('pdf_template_id', $this->id)->exists();
        if ($isInUse) {
            return true;
        }

        // Check if any email integration attaches this template (data key: pdf_template_ids)
        $isInUse = FormIntegration::where('integration_id', 'email')
            ->whereJsonContains('data->pdf_template_ids', (int) $this->id)
            ->exists();
        return $isInUse;
    }

    /**
     * Resolve the download filename for a given form and submission.
     */
    public function resolveFilename(Form $form, FormSubmission $submission): string
    {
        $pattern = $this->filename_pattern ?: self::DEFAULT_FILENAME_PATTERN;

        $variables = [
            ['id' => 'form_name', 'value' => Str::slug($form->title)],
            ['id' => 'submission_id', 'value' => $submission->id ?: 'preview'],
            ['id' => 'date', 'value' => now()->format('Y-m-d')],
        ];

        $parser = new MentionParser($pattern, $variables);
        $filename = $parser->parseAsText();

        $filename = preg_replace('/\.pdf$/i', '', $filename);
        $filename .= '.pdf';

        return preg_replace('/[^a-zA-Z0-9._-]/', '-', $filename);
    }

    /**
     * Relationships
     */
    public function form()
    {
        return $this->belongsTo(Form::class);
    }
}
