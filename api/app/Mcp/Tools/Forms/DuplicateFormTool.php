<?php

namespace App\Mcp\Tools\Forms;

use App\Models\Forms\Form;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Create a copy of an existing form. The duplicate gets a new slug and "Copy of" prefix in its title.')]
class DuplicateFormTool extends Tool
{
    public function handle(Request $request): ResponseFactory
    {
        $validated = $request->validate([
            'form_id' => 'required',
        ]);

        $form = $this->resolveForm($validated['form_id']);
        Gate::forUser($request->user())->authorize('update', $form);

        $formCopy = $form->replicate();
        if (Str::isUuid($formCopy->slug)) {
            $formCopy->slug = Str::uuid();
        } else {
            $formCopy->slug = null;
            $formCopy->save();
        }
        $formCopy->title = 'Copy of ' . $formCopy->title;
        $formCopy->removed_properties = [];
        $formCopy->save();

        return Response::structured([
            'id' => $formCopy->id,
            'slug' => $formCopy->slug,
            'title' => $formCopy->title,
            'share_url' => $formCopy->share_url,
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'form_id' => $schema->string()
                ->description('The form ID (integer) or slug to duplicate.')
                ->required(),
        ];
    }

    private function resolveForm(string $formId): Form
    {
        $query = Form::with(['workspace.users' => fn ($q) => $q->withPivot('role')]);

        if (is_numeric($formId)) {
            return $query->findOrFail((int) $formId);
        }

        return $query->where('slug', $formId)->firstOrFail();
    }
}
