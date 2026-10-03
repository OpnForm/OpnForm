<?php

namespace App\Service\Forms;

use App\Models\Forms\Form;
use App\Models\Workspace;
use App\Rules\ComputedVariablesRule;
use App\Rules\CssOnlyRule;
use App\Rules\FormPropertiesRule;
use App\Rules\PublicMediaUrlRule;
use App\Service\Forms\AgentFormFieldCatalog as FieldCatalog;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use JsonException;
use Stevebauman\Purify\Facades\Purify;

class AgentFormDefinition
{
    public const SCHEMA_VERSION = 1;

    public const MAX_DEFINITION_BYTES = 1_000_000;

    private const ALLOWED_TOP_LEVEL_KEYS = [
        'schema_version',
        'title',
        'visibility',
        'properties',
        'computed_variables',
        'language',
        'font_family',
        'theme',
        'presentation_style',
        'width',
        'size',
        'layout_rtl',
        'border_radius',
        'dark_mode',
        'color',
        'uppercase_labels',
        'no_branding',
        'transparent_background',
        'translations',
        'cover_picture',
        'cover_settings',
        'logo_picture',
        'custom_code',
        'custom_css',
        'submit_button_text',
        're_fillable',
        're_fill_button_text',
        'submitted_text',
        'redirect_url',
        'max_submissions_count',
        'max_submissions_reached_text',
        'editable_submissions',
        'editable_submissions_button_text',
        'confetti_on_submission',
        'show_progress_bar',
        'auto_save',
        'auto_focus',
        'enable_partial_submissions',
        'enable_ip_tracking',
        'can_be_indexed',
        'use_captcha',
        'captcha_provider',
        'seo_meta',
        'settings',
        'analytics',
    ];

    public function __construct(
        private readonly FormDataNormalizer $normalizer,
        private readonly FormValidationIssueMapper $issueMapper,
    ) {
    }

    public function normalizeAndValidate(array $definition, ?Workspace $workspace = null): array
    {
        $definition = $this->migrate($definition);
        $definition = array_replace($this->defaults(), $definition);
        $definition = $this->normalizer->normalize($definition, backfillPropertyIds: true);
        $definition['properties'] = collect($definition['properties'])->map(function ($property) {
            if (! is_array($property)) {
                return $property;
            }

            $property = array_replace([
                'help' => null,
                'hidden' => false,
                'required' => false,
                'placeholder' => null,
                'width' => 'full',
            ], $property);

            if (($property['type'] ?? null) === 'nf-text' && isset($property['content'])) {
                $property['content'] = Purify::clean((string) $property['content']);
            }

            return $property;
        })->values()->all();

        $this->validate($definition, $workspace);

        return Arr::only($definition, self::ALLOWED_TOP_LEVEL_KEYS);
    }

    public function validate(array $definition, ?Workspace $workspace = null): void
    {
        $unknownKeys = array_values(array_diff(array_keys($definition), self::ALLOWED_TOP_LEVEL_KEYS));

        if ($unknownKeys !== []) {
            throw ValidationException::withMessages([
                'definition' => ['Unknown top-level keys: '.implode(', ', $unknownKeys).'.'],
            ]);
        }

        try {
            $encodedDefinition = json_encode($definition, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw ValidationException::withMessages([
                'definition' => ['The form definition must contain valid JSON values.'],
            ]);
        }

        if (strlen($encodedDefinition) > self::MAX_DEFINITION_BYTES) {
            throw ValidationException::withMessages([
                'definition' => ['The form definition must not exceed 1 MB.'],
            ]);
        }

        $validator = Validator::make($definition, [
            'schema_version' => ['required', 'integer', Rule::in([self::SCHEMA_VERSION])],
            'title' => ['required', 'string', 'max:255'],
            'visibility' => ['required', Rule::in(Form::VISIBILITY)],
            'properties' => ['required', 'array', 'min:1', 'max:'.FormStructureValidator::MAX_PROPERTY_COUNT, new FormPropertiesRule($workspace)],
            'properties.*.help' => ['nullable', 'string'],
            'properties.*.image.url' => ['nullable', new PublicMediaUrlRule()],
            'computed_variables' => ['nullable', 'array', new ComputedVariablesRule()],
            'language' => ['required', Rule::in(Form::LANGUAGES)],
            'font_family' => ['nullable', 'string'],
            'theme' => ['required', Rule::in(Form::THEMES)],
            'presentation_style' => ['required', Rule::in(Form::PRESENTATION_STYLES)],
            'width' => ['required', Rule::in(Form::WIDTHS)],
            'size' => ['required', Rule::in(Form::SIZES)],
            'layout_rtl' => ['required', 'boolean'],
            'border_radius' => ['required', Rule::in(Form::BORDER_RADIUS)],
            'dark_mode' => ['required', Rule::in(Form::DARK_MODE_VALUES)],
            'color' => ['required', 'string'],
            'uppercase_labels' => ['required', 'boolean'],
            'no_branding' => ['required', 'boolean'],
            'transparent_background' => ['required', 'boolean'],
            'translations' => ['nullable', 'array'],
            'cover_picture' => ['nullable', new PublicMediaUrlRule()],
            'cover_settings' => ['nullable', 'array'],
            'cover_settings.focal_point' => ['sometimes', 'nullable', 'array'],
            'cover_settings.focal_point.x' => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:100'],
            'cover_settings.focal_point.y' => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:100'],
            'cover_settings.brightness' => ['sometimes', 'nullable', 'integer', 'min:-100', 'max:100'],
            'logo_picture' => ['nullable', new PublicMediaUrlRule()],
            'custom_code' => ['nullable', 'string'],
            'custom_css' => ['nullable', 'string', new CssOnlyRule()],
            'submit_button_text' => ['nullable', 'string', 'max:50'],
            're_fillable' => ['required', 'boolean'],
            're_fill_button_text' => ['nullable', 'string', 'max:50'],
            'submitted_text' => ['required', 'string', 'max:10000'],
            'redirect_url' => ['nullable', 'string'],
            'max_submissions_count' => ['nullable', 'integer', 'min:1'],
            'max_submissions_reached_text' => ['nullable', 'string'],
            'editable_submissions' => ['required', 'boolean'],
            'editable_submissions_button_text' => ['required', 'string', 'min:1', 'max:50'],
            'confetti_on_submission' => ['required', 'boolean'],
            'show_progress_bar' => ['required', 'boolean'],
            'auto_save' => ['required', 'boolean'],
            'auto_focus' => ['required', 'boolean'],
            'enable_partial_submissions' => ['required', 'boolean'],
            'enable_ip_tracking' => ['required', 'boolean'],
            'can_be_indexed' => ['required', 'boolean'],
            'use_captcha' => ['required', 'boolean'],
            'captcha_provider' => ['required', Rule::in(['recaptcha', 'hcaptcha'])],
            'seo_meta' => ['nullable', 'array'],
            'settings' => ['present', 'array'],
            'settings.navigation_arrows' => ['sometimes', 'boolean'],
            'settings.auto_next' => ['sometimes', 'boolean'],
            'analytics' => ['nullable', 'array'],
            'analytics.provider' => ['nullable', Rule::in(['meta_pixel', 'google_analytics', 'gtm'])],
            'analytics.tracking_id' => [
                'nullable',
                'string',
                'max:50',
                'regex:/^[A-Za-z0-9\-_\.]+$/',
                'required_if:analytics.provider,meta_pixel,google_analytics,gtm',
            ],
        ], [
            'theme.in' => 'theme must be one of: default, simple, notion, minimal, transparent.',
            'presentation_style.in' => 'presentation_style must be one of: classic, focused.',
            'width.in' => 'width must be one of: centered, full.',
            'size.in' => 'size must be one of: sm, md, lg.',
            'border_radius.in' => 'border_radius must be one of: none, small, full.',
            'dark_mode.in' => 'dark_mode must be one of: auto, light, dark.',
        ]);

        if ($validator->passes()) {
            return;
        }

        $errors = $validator->errors()->toArray();
        $pathErrors = $this->issueMapper->pathErrors($errors);

        throw ValidationException::withMessages($pathErrors !== [] ? $pathErrors : $errors);
    }

    public function defaults(): array
    {
        return [
            'schema_version' => self::SCHEMA_VERSION,
            'title' => 'Untitled Form',
            'visibility' => 'draft',
            'properties' => [],
            'computed_variables' => [],
            'language' => 'en',
            'font_family' => null,
            'theme' => 'default',
            'presentation_style' => 'classic',
            'width' => 'centered',
            'size' => 'md',
            'layout_rtl' => false,
            'border_radius' => 'small',
            'dark_mode' => 'auto',
            'color' => '#3B82F6',
            'uppercase_labels' => false,
            'no_branding' => false,
            'transparent_background' => false,
            'translations' => [],
            'cover_picture' => null,
            'cover_settings' => [],
            'logo_picture' => null,
            'custom_code' => null,
            'custom_css' => null,
            'submit_button_text' => null,
            're_fillable' => false,
            're_fill_button_text' => null,
            'submitted_text' => 'Amazing, we saved your answers. Thank you for your time and have a great day!',
            'redirect_url' => null,
            'max_submissions_count' => null,
            'max_submissions_reached_text' => 'This form has now reached the maximum number of allowed submissions and is now closed.',
            'editable_submissions' => false,
            'editable_submissions_button_text' => 'Edit submission',
            'confetti_on_submission' => false,
            'show_progress_bar' => false,
            'auto_save' => true,
            'auto_focus' => true,
            'enable_partial_submissions' => false,
            'enable_ip_tracking' => false,
            'can_be_indexed' => true,
            'use_captcha' => false,
            'captcha_provider' => 'recaptcha',
            'seo_meta' => [],
            'settings' => [],
            'analytics' => [],
        ];
    }

    /**
     * Return the canonical agent-editable portion of an existing form.
     */
    public function fromForm(Form $form): array
    {
        $definition = ['schema_version' => self::SCHEMA_VERSION];

        foreach (self::ALLOWED_TOP_LEVEL_KEYS as $key) {
            if ($key === 'schema_version') {
                continue;
            }

            if (array_key_exists($key, $form->getAttributes())) {
                $definition[$key] = $form->getAttribute($key);
            }
        }

        return $definition;
    }

    public function jsonSchema(): array
    {
        return [
            '$schema' => 'https://json-schema.org/draft/2020-12/schema',
            '$id' => 'https://opnform.com/schemas/agent-form-definition/v1.json',
            'title' => 'OpnForm Agent Form Definition v1',
            'type' => 'object',
            'additionalProperties' => false,
            'required' => ['schema_version', 'title', 'properties'],
            'properties' => [
                'schema_version' => ['const' => self::SCHEMA_VERSION],
                'title' => ['type' => 'string', 'minLength' => 1, 'maxLength' => 255],
                'visibility' => ['type' => 'string', 'enum' => Form::VISIBILITY, 'default' => 'draft'],
                'properties' => [
                    'type' => 'array',
                    'minItems' => 1,
                    'maxItems' => 500,
                    'items' => ['$ref' => '#/$defs/block'],
                ],
                'computed_variables' => [
                    'type' => 'array',
                    'maxItems' => ComputedVariablesRule::MAX_VARIABLE_COUNT,
                    'items' => ['$ref' => '#/$defs/computedVariable'],
                    'default' => [],
                    'description' => 'Calculated values available to other formulas and display logic. Formula references use braces, for example {budget} * 1.2.',
                ],
                'language' => ['type' => 'string', 'enum' => Form::LANGUAGES, 'default' => 'en'],
                'font_family' => ['type' => ['string', 'null']],
                'theme' => ['type' => 'string', 'enum' => Form::THEMES, 'default' => 'default'],
                'presentation_style' => ['type' => 'string', 'enum' => Form::PRESENTATION_STYLES, 'default' => 'classic'],
                'width' => ['type' => 'string', 'enum' => Form::WIDTHS, 'default' => 'centered'],
                'size' => ['type' => 'string', 'enum' => Form::SIZES, 'default' => 'md'],
                'layout_rtl' => ['type' => 'boolean', 'default' => false],
                'border_radius' => ['type' => 'string', 'enum' => Form::BORDER_RADIUS, 'default' => 'small'],
                'dark_mode' => ['type' => 'string', 'enum' => Form::DARK_MODE_VALUES, 'default' => 'auto'],
                'color' => ['type' => 'string', 'default' => '#3B82F6'],
                'uppercase_labels' => ['type' => 'boolean', 'default' => false],
                'no_branding' => ['type' => 'boolean', 'default' => false],
                'transparent_background' => ['type' => 'boolean', 'default' => false],
                'translations' => ['type' => ['object', 'array'], 'default' => (object) []],
                'cover_picture' => ['type' => ['string', 'null'], 'format' => 'uri', 'pattern' => '^https://'],
                'cover_settings' => ['type' => ['object', 'array'], 'default' => (object) []],
                'logo_picture' => ['type' => ['string', 'null'], 'format' => 'uri', 'pattern' => '^https://'],
                'custom_code' => ['type' => ['string', 'null']],
                'custom_css' => ['type' => ['string', 'null']],
                'submit_button_text' => ['type' => ['string', 'null'], 'maxLength' => 50],
                're_fillable' => ['type' => 'boolean', 'default' => false],
                're_fill_button_text' => ['type' => ['string', 'null'], 'maxLength' => 50],
                'submitted_text' => ['type' => 'string', 'maxLength' => 10000],
                'redirect_url' => ['type' => ['string', 'null']],
                'max_submissions_count' => ['type' => ['integer', 'null'], 'minimum' => 1],
                'max_submissions_reached_text' => ['type' => ['string', 'null']],
                'editable_submissions' => ['type' => 'boolean', 'default' => false],
                'editable_submissions_button_text' => ['type' => 'string', 'minLength' => 1, 'maxLength' => 50],
                'confetti_on_submission' => ['type' => 'boolean', 'default' => false],
                'show_progress_bar' => ['type' => 'boolean', 'default' => false],
                'auto_save' => ['type' => 'boolean', 'default' => true],
                'auto_focus' => ['type' => 'boolean', 'default' => true],
                'enable_partial_submissions' => ['type' => 'boolean', 'default' => false],
                'enable_ip_tracking' => ['type' => 'boolean', 'default' => false],
                'can_be_indexed' => ['type' => 'boolean', 'default' => true],
                'use_captcha' => ['type' => 'boolean', 'default' => false],
                'captcha_provider' => ['type' => 'string', 'enum' => ['recaptcha', 'hcaptcha'], 'default' => 'recaptcha'],
                'seo_meta' => ['type' => ['object', 'array'], 'default' => (object) []],
                'settings' => ['type' => ['object', 'array'], 'default' => (object) []],
                'analytics' => ['type' => ['object', 'array'], 'default' => (object) []],
            ],
            '$defs' => [
                'block' => [
                    'type' => 'object',
                    'required' => ['name', 'type'],
                    'additionalProperties' => true,
                    'properties' => [
                        'id' => [
                            'type' => 'string',
                            'description' => 'Stable technical block identifier. Omit it for new blocks so the server generates one. Never reuse it as visible label copy.',
                        ],
                        'name' => [
                            'type' => 'string',
                            'minLength' => 1,
                            'description' => 'Respondent-facing label in sentence case with natural spaces, such as Full name or Email address. Never use snake_case, kebab-case, database keys, or variable names.',
                        ],
                        'type' => ['type' => 'string', 'enum' => FieldCatalog::types()],
                        'content' => [
                            'type' => 'string',
                            'description' => 'For nf-text blocks only: sanitized HTML fragment, never Markdown. Example: <h1>Contact us</h1><p>How can we help?</p>.',
                        ],
                        'help' => ['type' => ['string', 'null']],
                        'hidden' => ['type' => 'boolean', 'default' => false],
                        'required' => ['type' => 'boolean', 'default' => false],
                        'placeholder' => ['type' => ['string', 'null']],
                        'width' => ['type' => 'string', 'enum' => ['full', '1/2', '1/3', '2/3', '1/4', '3/4'], 'default' => 'full'],
                        'image' => ['$ref' => '#/$defs/blockImage'],
                        'logic' => [
                            'anyOf' => [
                                ['$ref' => '#/$defs/displayLogic'],
                                ['type' => 'null'],
                            ],
                            'description' => 'Optional conditional behavior for this target block. Empty or safely invalid logic is removed during normalization.',
                        ],
                    ],
                ],
                'computedVariable' => [
                    'type' => 'object',
                    'additionalProperties' => true,
                    'required' => ['id', 'name', 'formula'],
                    'properties' => [
                        'id' => [
                            'type' => 'string',
                            'pattern' => '^cv_',
                            'description' => 'Unique technical identifier beginning with cv_. It must not duplicate a block ID.',
                        ],
                        'name' => ['type' => 'string', 'minLength' => 1, 'maxLength' => 100],
                        'formula' => [
                            'type' => 'string',
                            'minLength' => 1,
                            'maxLength' => 2000,
                            'description' => 'Expression referencing block or computed-variable IDs with braces, for example {budget} * 1.2.',
                        ],
                        'result_type' => ['type' => ['string', 'null'], 'enum' => ['number', 'text', 'auto', null]],
                    ],
                ],
                'displayLogic' => [
                    'type' => 'object',
                    'additionalProperties' => true,
                    'required' => ['conditions', 'actions'],
                    'properties' => [
                        'conditions' => ['$ref' => '#/$defs/logicCondition'],
                        'actions' => [
                            'type' => 'array',
                            'minItems' => 1,
                            'uniqueItems' => true,
                            'items' => ['type' => 'string', 'enum' => \App\Rules\PropertyValidators\LogicPropertyValidator::ACTIONS_VALUES],
                            'description' => 'Allowed actions depend on the target block state and type; validate_form_definition returns the exact path for an incompatible action.',
                        ],
                    ],
                ],
                'logicCondition' => [
                    'oneOf' => [
                        ['$ref' => '#/$defs/logicConditionGroup'],
                        ['$ref' => '#/$defs/logicLeafCondition'],
                    ],
                ],
                'logicConditionGroup' => [
                    'type' => 'object',
                    'additionalProperties' => true,
                    'required' => ['operatorIdentifier', 'children'],
                    'properties' => [
                        'operatorIdentifier' => ['type' => 'string', 'enum' => ['and', 'or']],
                        'children' => [
                            'type' => 'array',
                            'minItems' => 1,
                            'maxItems' => \App\Rules\PropertyValidators\LogicPropertyValidator::MAX_CONDITION_COUNT,
                            'items' => ['$ref' => '#/$defs/logicCondition'],
                        ],
                    ],
                ],
                'logicLeafCondition' => [
                    'type' => 'object',
                    'additionalProperties' => true,
                    'required' => ['identifier', 'value'],
                    'properties' => [
                        'identifier' => [
                            'type' => 'string',
                            'minLength' => 1,
                            'description' => 'ID of the referenced field or computed variable. It must match value.property_meta.id.',
                        ],
                        'value' => ['$ref' => '#/$defs/logicConditionValue'],
                    ],
                ],
                'logicConditionValue' => [
                    'type' => 'object',
                    'additionalProperties' => true,
                    'required' => ['operator', 'property_meta'],
                    'properties' => [
                        'operator' => [
                            'type' => 'string',
                            'enum' => FieldCatalog::logicOperators(),
                            'description' => 'Comparison operator compatible with property_meta.type. Consult operators_by_reference_type in the field catalog.',
                        ],
                        'property_meta' => [
                            'type' => 'object',
                            'additionalProperties' => true,
                            'required' => ['id', 'type'],
                            'properties' => [
                                'id' => ['type' => 'string', 'minLength' => 1],
                                'type' => [
                                    'type' => 'string',
                                    'enum' => array_keys(FieldCatalog::logicOperatorsByReferenceType()),
                                    'description' => 'Use computed when referencing a computed variable; otherwise use the referenced field type.',
                                ],
                            ],
                        ],
                        'value' => [
                            'description' => 'Comparison value. Omit only for operators such as is_empty, is_not_empty, is_checked, or is_not_checked.',
                        ],
                    ],
                ],
                'blockImage' => [
                    'type' => 'object',
                    'additionalProperties' => true,
                    'properties' => [
                        'url' => ['type' => ['string', 'null'], 'format' => 'uri', 'pattern' => '^https://'],
                        'alt' => ['type' => ['string', 'null'], 'maxLength' => 125],
                        'layout' => [
                            'type' => ['string', 'null'],
                            'enum' => ['between', 'left-small', 'right-small', 'left-split', 'right-split', 'background', null],
                        ],
                        'focal_point' => [
                            'type' => ['object', 'null'],
                            'properties' => [
                                'x' => ['type' => 'number', 'minimum' => 0, 'maximum' => 100],
                                'y' => ['type' => 'number', 'minimum' => 0, 'maximum' => 100],
                            ],
                        ],
                        'brightness' => ['type' => 'integer', 'minimum' => -100, 'maximum' => 100],
                        'fade' => ['type' => 'boolean'],
                    ],
                ],
            ],
            'x-opnform-max-bytes' => self::MAX_DEFINITION_BYTES,
        ];
    }

    private function migrate(array $definition): array
    {
        $version = $definition['schema_version'] ?? self::SCHEMA_VERSION;

        if ($version !== self::SCHEMA_VERSION) {
            throw ValidationException::withMessages([
                'schema_version' => ["Unsupported schema version [{$version}]. Current version is ".self::SCHEMA_VERSION.'.'],
            ]);
        }

        $definition['schema_version'] = self::SCHEMA_VERSION;

        return $definition;
    }

}
