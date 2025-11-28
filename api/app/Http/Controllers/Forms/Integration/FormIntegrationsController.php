<?php

namespace App\Http\Controllers\Forms\Integration;

use App\Http\Controllers\Controller;
use App\Http\Requests\Integration\FormIntegrationsRequest;
use App\Http\Resources\FormIntegrationResource;
use App\Models\Forms\Form;
use App\Models\Integration\FormIntegration;

class FormIntegrationsController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function index(Form $form)
    {
        $this->authorize('manageIntegrations', $form);

        $integrations = FormIntegration::query()
            ->where('form_id', $form->id)
            ->with('provider.user')
            ->get();

        return FormIntegrationResource::collection($integrations);
    }

    public function create(FormIntegrationsRequest $request, Form $form)
    {
        $this->authorize('manageIntegrations', $form);

        /** @var FormIntegration $formIntegration */
        $formIntegration = FormIntegration::create(
            array_merge([
                'form_id' => $form->id,
            ], $request->toIntegrationData())
        );

        $formIntegration->refresh();
        $formIntegration->load('provider.user');

        return $this->success([
            'message' => 'Form Integration was created.',
            'form_integration' => FormIntegrationResource::make($formIntegration)
        ]);
    }

    public function update(FormIntegrationsRequest $request, Form $form, string $integrationid)
    {
        $this->authorize('manageIntegrations', $form);

        $formIntegration = FormIntegration::findOrFail((int)$integrationid);
        $formIntegration->update($request->toIntegrationData());
        $formIntegration->load('provider.user');

        return $this->success([
            'message' => 'Form Integration was updated.',
            'form_integration' => FormIntegrationResource::make($formIntegration)
        ]);
    }

    public function destroy(Form $form, string $integrationid)
    {
        $this->authorize('manageIntegrations', $form);

        $formIntegration = FormIntegration::findOrFail((int)$integrationid);
        $formIntegration->delete();

        return $this->success([
            'message' => 'Form Integration was deleted.'
        ]);
    }
}
