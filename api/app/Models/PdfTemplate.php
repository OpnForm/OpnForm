<?php

namespace App\Models;

use App\Models\Forms\Form;
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
     * Check if this template is in use (e.g., selected as success page download template).
     */
    public function isInUse(): bool
    {
        // Check if any form uses this template for success page download
        return Form::where('pdf_template_id', $this->id)->exists();
    }

    /**
     * Relationships
     */
    public function form()
    {
        return $this->belongsTo(Form::class);
    }
}
