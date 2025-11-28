<?php

namespace App\Events\Forms;

use App\Models\Forms\Form;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class FormSaved
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(public Form $form)
    {
    }
}
