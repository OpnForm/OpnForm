<?php

namespace App\Http\Requests;

use App\Http\Requests\Workspace\CustomDomainRequest;
use App\Models\Forms\Form;
use App\Rules\CustomSlugRule;
use App\Rules\FormPropertiesRule;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Http\Request;
use App\Rules\CssOnlyRule;

/**
 * Abstract class to validate create/update forms
 *
 * Class UserFormRequest
 */
abstract class UserFormRequest extends \Illuminate\Foundation\Http\FormRequest
{
    public ?Form $form;

    public function __construct(Request $request)
    {
        // Get form from route model binding instead of middleware
        $this->form = $request?->route('form') ?? null;
    }

    protected function prepareForValidation()
    {
        $data = $this->all();

        if (isset($data['properties']) && is_array($data['properties'])) {
            $data['properties'] = array_map(function ($property) {
                if (isset($property['help']) && is_string($property['help']) && strip_tags($property['help']) === '') {
                    $property['help'] = null;
                }
                return $property;
            }, $data['properties']);
        }

        $this->merge($data);
    }

    /**
     * Handle a failed validation attempt.
     *
     * @param  \Illuminate\Contracts\Validation\Validator  $validator
     * @return void
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    protected function failedValidation(Validator $validator)
    {
        // Log validation errors to default log and Slack
        $errors = $validator->errors()->toArray();
        $requestData = $this->except(['password']); // Exclude sensitive data

        $logData = [
            'errors' => $errors,
            'request_data' => $requestData,
            'ip' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'route' => request()->route()->getName() ?? request()->path()
        ];

        // Log to both default channel and Slack
        if (!app()->environment('testing')) {
            Log::channel('slack_errors')->warning(
                'Frontend validation bypass detected in form submission',
                $logData
            );
        }

        throw new ValidationException($validator);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        // Get the workspace from the form being updated or the current user's workspace
        $workspace = null;

        // For update requests, try to get the workspace from the form
        if ($this->form) {
            $workspace = $this->form->workspace;
        }
        // For create requests, get the workspace from the workspace parameter
        elseif ($this->route('workspace')) {
            $workspace = $this->route('workspace');
        }
        // Otherwise, try to get from the request attribute
        elseif ($this->get('workspace_id')) {
            $workspace = \App\Models\Workspace::find($this->get('workspace_id'));
        }

        return [
            // Form Info
            'title' => 'required|string|max:60',
            'description' => 'nullable|string|max:2000',
            'tags' => 'nullable|array',
            'visibility' => ['required', Rule::in(Form::VISIBILITY)],

            // Customization
            'language' => ['required', Rule::in(Form::LANGUAGES)],
            'font_family' => 'string|nullable',
            'theme' => ['required', Rule::in(Form::THEMES)],
            'presentation_style' => ['required', Rule::in(Form::PRESENTATION_STYLES)],
            'width' => ['required', Rule::in(Form::WIDTHS)],
            'size' => ['required', Rule::in(Form::SIZES)],
            'layout_rtl' => 'boolean',
            'border_radius' => ['required', Rule::in(Form::BORDER_RADIUS)],
            'dark_mode' => ['required', Rule::in(Form::DARK_MODE_VALUES)],
            'color' => 'required|string',
            'uppercase_labels' => 'required|boolean',
            'no_branding' => 'required|boolean',
            'transparent_background' => 'required|boolean',
            'translations' => 'nullable|array',
            'closes_at' => 'date|nullable',
            'closed_text' => 'string|nullable',
            'logo_picture' => 'url|nullable',

            // Cover
            'cover_picture' => 'url|nullable',
            'cover_settings' => 'nullable|array',
            'cover_settings.focal_point' => 'sometimes|nullable|array',
            'cover_settings.focal_point.x' => 'sometimes|nullable|numeric|min:0|max:100',
            'cover_settings.focal_point.y' => 'sometimes|nullable|numeric|min:0|max:100',
            'cover_settings.brightness' => 'sometimes|nullable|integer|min:-100|max:100',

            // Custom Code
            'custom_code' => 'string|nullable',
            'custom_css' => ['string', 'nullable', new CssOnlyRule()],

            // Submission
            'submit_button_text' => 'nullable|string|max:50',
            're_fillable' => 'boolean',
            're_fill_button_text' => 'nullable|string|max:50',
            'submitted_text' => 'string|max:10000',
            'redirect_url' => 'nullable|string',
            'database_fields_update' => 'nullable|array',
            'max_submissions_count' => 'integer|nullable|min:1',
            'max_submissions_reached_text' => 'string|nullable',
            'editable_submissions' => 'boolean|nullable',
            'editable_submissions_button_text' => 'string|min:1|max:50',
            'confetti_on_submission' => 'boolean',
            'show_progress_bar' => 'boolean',
            'auto_save' => 'boolean',
            'auto_focus' => 'boolean',
            'enable_partial_submissions' => 'boolean',
            'enable_ip_tracking' => 'boolean',

            // Properties - Single-pass validation for performance
            // Replaces ~35 wildcard rules (properties.*) with one efficient rule
            'properties' => ['required', 'array', new FormPropertiesRule($workspace)],

            // Security & Privacy
            'can_be_indexed' => 'boolean',
            'password' => 'sometimes|nullable',
            'use_captcha' => 'boolean',
            'captcha_provider' => ['sometimes', Rule::in(['recaptcha', 'hcaptcha'])],
            'slug' => [new CustomSlugRule($this->form)],

            // Custom SEO
            'seo_meta' => 'nullable|array',
            'custom_domain' => 'sometimes|nullable|regex:' . CustomDomainRequest::CUSTOM_DOMAINS_REGEX,

            // Settings
            'settings' => 'nullable|array',
            'settings.navigation_arrows' => 'sometimes|boolean',
        ];
    }

    /**
     * Get the validation messages that apply to the request.
     *
     * @return array
     */
    public function messages()
    {
        // Note: Property-specific messages are now handled by FormPropertiesRule
        // for better performance (single-pass validation)
        return [];
    }
}
