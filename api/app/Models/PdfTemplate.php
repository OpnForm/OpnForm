<?php

namespace App\Models;

use App\Models\Forms\Form;
use App\Models\Integration\FormIntegration;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PdfTemplate extends Model
{
    use HasFactory;
    use SoftDeletes;

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
     * Relationships
     */
    public function form()
    {
        return $this->belongsTo(Form::class);
    }
}
