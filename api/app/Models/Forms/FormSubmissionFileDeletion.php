<?php

namespace App\Models\Forms;

use Illuminate\Database\Eloquent\Model;

class FormSubmissionFileDeletion extends Model
{
    protected $fillable = [
        'path',
        'attempts',
        'last_error',
        'next_attempt_at',
    ];

    protected function casts(): array
    {
        return [
            'attempts' => 'integer',
            'next_attempt_at' => 'datetime',
        ];
    }
}
