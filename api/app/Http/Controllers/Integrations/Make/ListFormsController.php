<?php

namespace App\Http\Controllers\Integrations\Make;

use App\Http\Requests\Integration\Make\ListFormsRequest;
use App\Http\Resources\Zapier\FormResource;
use App\Models\Forms\Form;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class ListFormsController
{
    use AuthorizesRequests;

    public function __invoke(ListFormsRequest $request)
    {
        $workspace = $request->workspace();

        $this->authorize('ownsWorkspace', $workspace);
        $this->authorize('viewAny', Form::class);

        return FormResource::collection(
            $workspace->forms()->get()
        );
    }
}
