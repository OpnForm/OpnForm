<?php

namespace App\Http\Requests\Integration\Zapier;

use App\Models\Forms\Form;
use App\Models\Integration\FormZapierWebhook;
use Illuminate\Foundation\Http\FormRequest;

class StoreFormZapierWebhookRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'form_slug' => 'required|exists:forms,slug',
            'hook_url' => 'required|string|url',
        ];
    }

    public function instanciateHook()
    {
        $form = Form::whereSlug($this->form_slug)->firstOrFail();

        return new FormZapierWebhook([
            'form_id' => $form->id,
            'hook_url' => $this->hook_url,
        ]);
    }
}
