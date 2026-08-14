<?php

namespace App\Service\Forms;

use App\Models\Forms\Form;
use App\Rules\ComputedVariablesRule;
use App\Rules\FormPropertiesRule;
use App\Service\Forms\AgentFormFieldCatalog as FieldCatalog;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AgentFormDefinition
{
    public const SCHEMA_VERSION = 1;

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

    public function __construct(private readonly FormDataNormalizer $normalizer)
    {
    }

    public function normalizeAndValidate(array $definition): array
    {
        $definition = $this->migrate($definition);
        $definition = array_replace($this->defaults(), $definition);
        $definition = $this->normalizeAliases($definition);
        $definition = $this->normalizer->normalize($definition, backfillPropertyIds: true);
        $definition['properties'] = collect($definition['properties'])->map(fn ($property) => is_array($property)
            ? array_replace([
                'help' => null,
                'hidden' => false,
                'required' => false,
                'placeholder' => null,
                'width' => 'full',
            ], $property)
            : $property)->values()->all();

        $this->validate($definition);

        return Arr::only($definition, self::ALLOWED_TOP_LEVEL_KEYS);
    }

    public function validate(array $definition): void
    {
        $unknownKeys = array_values(array_diff(array_keys($definition), self::ALLOWED_TOP_LEVEL_KEYS));

        if ($unknownKeys !== []) {
            throw ValidationException::withMessages([
                'definition' => ['Unknown top-level keys: '.implode(', ', $unknownKeys).'.'],
            ]);
        }

        Validator::make($definition, [
            'schema_version' => ['required', 'integer', Rule::in([self::SCHEMA_VERSION])],
            'title' => ['required', 'string', 'max:255'],
            'visibility' => ['required', Rule::in(Form::VISIBILITY)],
            'properties' => ['required', 'array', 'min:1', 'max:500', new FormPropertiesRule()],
            'properties.*.type' => ['required', Rule::in(FieldCatalog::types())],
            'computed_variables' => ['nullable', 'array', new ComputedVariablesRule()],
            'language' => ['required', Rule::in(Form::LANGUAGES)],
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
            're_fillable' => ['required', 'boolean'],
            'confetti_on_submission' => ['required', 'boolean'],
            'show_progress_bar' => ['required', 'boolean'],
            'auto_save' => ['required', 'boolean'],
            'auto_focus' => ['required', 'boolean'],
            'enable_partial_submissions' => ['required', 'boolean'],
            'enable_ip_tracking' => ['required', 'boolean'],
            'can_be_indexed' => ['required', 'boolean'],
            'use_captcha' => ['required', 'boolean'],
            'captcha_provider' => ['required', Rule::in(['recaptcha', 'hcaptcha'])],
            'settings' => ['present', 'array'],
        ])->validate();
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
                'computed_variables' => ['type' => 'array', 'default' => []],
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
                'cover_picture' => ['type' => ['string', 'null'], 'format' => 'uri'],
                'cover_settings' => ['type' => ['object', 'array'], 'default' => (object) []],
                'logo_picture' => ['type' => ['string', 'null'], 'format' => 'uri'],
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
                        'id' => ['type' => 'string'],
                        'name' => ['type' => 'string', 'minLength' => 1],
                        'type' => ['type' => 'string', 'enum' => FieldCatalog::types()],
                        'help' => ['type' => ['string', 'null']],
                        'hidden' => ['type' => 'boolean', 'default' => false],
                        'required' => ['type' => 'boolean', 'default' => false],
                        'placeholder' => ['type' => ['string', 'null']],
                        'width' => ['type' => 'string', 'enum' => ['full', '1/2', '1/3', '2/3', '1/4', '3/4'], 'default' => 'full'],
                    ],
                ],
            ],
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

    private function normalizeAliases(array $definition): array
    {
        $definition['properties'] = collect($definition['properties'] ?? [])->map(function ($property) {
            if (! is_array($property) || ! isset(FieldCatalog::ALIASES[$property['type'] ?? ''])) {
                return $property;
            }

            return array_replace($property, FieldCatalog::ALIASES[$property['type']]);
        })->values()->all();

        return $definition;
    }
}
