<?php

namespace App\Models\Forms;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SubmissionOperation extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['processed_data' => 'array', 'submission_meta' => 'array',
            'temporary_paths' => 'array', 'completed_at' => 'datetime'];
    }

    public function form(): BelongsTo
    {
        return $this->belongsTo(Form::class);
    }
}
