<?php

namespace App\Models\Forms;

use Illuminate\Database\Eloquent\Model;

class FormSubmissionFile extends Model
{
    protected $fillable = [
        'form_submission_id',
        'path',
        'path_hash',
    ];
}
