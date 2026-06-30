<?php

namespace App\Mcp\Tools\Forms;

use App\Mcp\Concerns\ResolvesForm;
use App\Models\Forms\Form;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Gate;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;

#[Description('Delete a form (soft-delete). The form can be recovered by an admin. Submissions are preserved until permanently purged.')]
#[IsDestructive]
class DeleteFormTool extends Tool
{
    use ResolvesForm;

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
}
