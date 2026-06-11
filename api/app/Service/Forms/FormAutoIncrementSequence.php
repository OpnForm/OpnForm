<?php

namespace App\Service\Forms;

use App\Models\Forms\Form;
use Illuminate\Support\Facades\DB;

class FormAutoIncrementSequence
{
    /**
     * Atomically allocate the next auto-increment ID for a form.
     */
    public static function allocateNext(Form $form): string
    {
        return (string) DB::transaction(function () use ($form) {
            $lockedForm = Form::query()
                ->whereKey($form->id)
                ->lockForUpdate()
                ->firstOrFail();

            $next = $lockedForm->auto_increment_sequence + 1;

            DB::table('forms')
                ->where('id', $lockedForm->id)
                ->update(['auto_increment_sequence' => $next]);

            return $next;
        });
    }
}
