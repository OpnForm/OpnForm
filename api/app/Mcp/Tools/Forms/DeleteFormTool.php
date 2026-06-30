<?php

namespace App\Mcp\Tools\Forms;

use App\Models\Forms\Form;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Gate;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;

#[Description('Permanently delete a form. This cannot be undone. All submissions for this form will also be lost.')]
#[IsDestructive]
class DeleteFormTool extends Tool
{
    public function handle(Request $request): Response
    {
        $validated = $request->validate([
            'form_id' => 'required',
        ]);

        $form = $this->resolveForm($validated['form_id']);
        Gate::forUser($request->user())->authorize('delete', $form);

        $title = $form->title;
        $form->delete();

        return Response::text("Form \"{$title}\" has been deleted.");
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'form_id' => $schema->string()
                ->description('The form ID (integer) or slug to delete.')
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
