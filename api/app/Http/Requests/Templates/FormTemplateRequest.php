<?php

namespace App\Http\Requests\Templates;

use App\Models\Template;
use Illuminate\Foundation\Http\FormRequest;

class FormTemplateRequest extends FormRequest
{
    public const IGNORED_KEYS = [
        'id',
        'creator',
        'cleanings',
        'closes_at',
        'deleted_at',
        'updated_at',
        'form_pending_submission_key',
        'is_closed',
        'is_password_protected',
        'last_edited_human',
        'max_number_of_submissions_reached',
        'removed_properties',
        'creator_id',
        'extra',
        'workspace',
        'workspace_id',
        'submissions',
        'submissions_count',
        'views',
        'views_count',
        'visibility',
        'webhook_url',
    ];

    /**
     * @return array<string, mixed>
     */
    public function rules()
    {
        $slugRule = '';
        if ($this->route('id') ?? $this->input('id')) {
            $slugRule = ',' . ($this->route('id') ?? $this->input('id'));
        }

        return [
            'form' => 'required|array',
            'publicly_listed' => 'sometimes|boolean',
            'name' => 'required|string|max:60',
            'slug' => 'required|string|alpha_dash|unique:templates,slug' . $slugRule,
            'short_description' => 'required|string|max:1000',
            'description' => 'required|string',
            'image_url' => 'required|string',
            'types' => 'nullable|array',
            'industries' => 'nullable|array',
            'related_templates' => 'nullable|array',
            'questions' => 'array',
        ];
    }

    public function getTemplate(): Template
    {
        $template = new Template($this->getMutableAttributes());
        $template->creator_id = $this->user()?->id;

        return $template;
    }

    /**
     * @return array<string, mixed>
     */
    public function getUpdateAttributes(): array
    {
        return $this->getMutableAttributes();
    }

    /**
     * @return array<string, mixed>
     */
    private function getMutableAttributes(): array
    {
        $attributes = [
            'name' => $this->input('name'),
            'slug' => $this->input('slug'),
            'short_description' => $this->input('short_description'),
            'description' => $this->input('description'),
            'image_url' => $this->input('image_url'),
            'structure' => $this->cleanFormStructure($this->input('form', [])),
            'types' => $this->input('types', []),
            'industries' => $this->input('industries', []),
            'related_templates' => $this->input('related_templates', []),
            'questions' => $this->input('questions', []),
        ];

        if ($this->canSetPubliclyListed() && $this->has('publicly_listed')) {
            $attributes['publicly_listed'] = $this->boolean('publicly_listed');
        }

        return $attributes;
    }

    /**
     * @param  array<string, mixed>  $structure
     * @return array<string, mixed>
     */
    private function cleanFormStructure(array $structure): array
    {
        foreach (self::IGNORED_KEYS as $key) {
            unset($structure[$key]);
        }

        return $structure;
    }

    private function canSetPubliclyListed(): bool
    {
        $user = $this->user();

        return $user !== null && ($user->admin || $user->template_editor);
    }
}
