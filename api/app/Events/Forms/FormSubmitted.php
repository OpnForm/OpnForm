<?php

namespace App\Events\Forms;

use App\Models\Forms\Form;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class FormSubmitted
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public $form;

    public $data;

    public array $meta;

    /**
     * Create a new event instance.
     *
     * @return void
     */
    public function __construct(Form $form, array $data, array $meta = [])
    {
        $this->form = $form;
        $this->data = $data;
        $this->meta = $meta;
    }
}
