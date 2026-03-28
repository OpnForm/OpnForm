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
        'is_pro',
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
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules()
    {
        $slugRule = '';
        if ($this->id) {
            $slugRule = ',' . $this->id;
        }

        return [
            'form' => 'required|array',
            'publicly_listed' => 'boolean',
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
        $structure = $this->form;
        foreach ($structure as $key => $val) {
            if (in_array($key, self::IGNORED_KEYS)) {
                unset($structure[$key]);
            }
        }

        return new Template([
            'creator_id' => $this->user()?->id ?? null,
            'publicly_listed' => $this->publicly_listed,
            'name' => $this->name,
            'slug' => $this->slug,
            'short_description' => $this->short_description,
            'description' => $this->description,
            'image_url' => $this->image_url,
            'structure' => $structure,
            'types' => $this->types ?? [],
            'industries' => $this->industries ?? [],
            'related_templates' => $this->related_templates ?? [],
            'questions' => $this->questions ?? [],
        ]);
    }
}
