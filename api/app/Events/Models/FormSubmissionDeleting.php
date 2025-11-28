<?php

namespace App\Events\Models;

use App\Models\Forms\FormSubmission;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class FormSubmissionDeleting
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(public FormSubmission $submission)
    {
    }
}
