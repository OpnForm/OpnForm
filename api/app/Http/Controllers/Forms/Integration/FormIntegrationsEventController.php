<?php

namespace App\Http\Controllers\Forms\Integration;

use App\Http\Controllers\Controller;
use App\Http\Resources\FormIntegrationsEventResource;
use App\Models\Forms\Form;

class FormIntegrationsEventController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function index(Form $form, string $integrationid)
    {
        $this->authorize('manageIntegrations', $form);
        $formIntegration = $form->integrations()->findOrFail((int) $integrationid);

        return FormIntegrationsEventResource::collection(
            $formIntegration->events()->orderByDesc('created_at')->get()
        );
    }
}
