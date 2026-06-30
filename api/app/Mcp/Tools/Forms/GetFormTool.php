<?php

namespace App\Mcp\Tools\Forms;

use App\Models\Forms\Form;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Gate;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Description('Get full details of a specific form including all fields (properties), settings, and customization. Accepts form ID (integer) or slug (string).')]
#[IsReadOnly]
#[IsIdempotent]
class GetFormTool extends Tool
{
    public function handle(Request $request): ResponseFactory
    {
        $validated = $request->validate([
            'form_id' => 'required',
        ]);

        $form = $this->resolveForm($validated['form_id']);
        Gate::forUser($request->user())->authorize('view', $form);

        return Response::structured([
            'id' => $form->id,
            'title' => $form->title,
            'slug' => $form->slug,
            'visibility' => $form->visibility,
            'share_url' => $form->share_url,
            'workspace_id' => $form->workspace_id,
            'properties' => $form->properties,
            'settings' => $form->settings ?? new \stdClass(),
            'theme' => $form->theme,
            'color' => $form->color,
            'dark_mode' => $form->dark_mode,
            'size' => $form->size,
            'border_radius' => $form->border_radius,
            'width' => $form->width,
            'presentation_style' => $form->presentation_style ?? 'classic',
            'language' => $form->language,
            'cover_picture' => $form->cover_picture,
            'logo_picture' => $form->logo_picture,
            'submit_button_text' => $form->submit_button_text,
            'submitted_text' => $form->submitted_text,
            'redirect_url' => $form->redirect_url,
            'use_captcha' => $form->use_captcha,
            're_fillable' => $form->re_fillable,
            'editable_submissions' => $form->editable_submissions,
            'max_submissions_count' => $form->max_submissions_count,
            'submissions_count' => $form->submissions_count,
            'views_count' => $form->views_count,
            'created_at' => $form->created_at?->toIso8601String(),
            'updated_at' => $form->updated_at?->toIso8601String(),
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'form_id' => $schema->string()
                ->description('The form ID (integer) or slug (string). Use list-forms to find available forms.')
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
