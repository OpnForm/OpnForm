<?php

namespace Database\Factories;

use App\Models\Forms\Form;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class FormFactory extends Factory
{
    protected $model = Form::class;

    public function forWorkspace(Workspace $workspace)
    {
        return $this->state(function (array $attributes) use ($workspace) {
            return [
                'workspace_id' => $workspace->id,
            ];
        });
    }

    public function withProperties(array $properties)
    {
        return $this->state(function (array $attributes) use ($properties) {
            return [
                'properties' => $properties,
            ];
        });
    }

    public function createdBy(User $user)
    {
        return $this->state(function (array $attributes) use ($user) {
            return [
                'creator_id' => $user->id,
            ];
        });
    }

    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition()
    {
        return [
            'title' => $this->faker->text(30),
            'description' => $this->faker->randomHtml(1),
            'visibility' => 'public',
            'language' => 'en',
            'theme' => $this->faker->randomElement(Form::THEMES),
            'presentation_style' => 'classic',
            'size' => $this->faker->randomElement(Form::SIZES),
            'border_radius' => $this->faker->randomElement(Form::BORDER_RADIUS),
            'width' => $this->faker->randomElement(Form::WIDTHS),
            'dark_mode' => $this->faker->randomElement(Form::DARK_MODE_VALUES),
            'color' => '#3B82F6',
            'no_branding' => false,
            'uppercase_labels' => true,
            'transparent_background' => false,
            'submit_button_text' => 'Submit',
            'editable_submissions' => false,
            're_fillable' => false,
            're_fill_button_text' => 'Fill Again',
            'submitted_text' => '<p>Amazing, we saved your answers. Thank you for your time and have a great day!</p>',
            'use_captcha' => false,
            'can_be_indexed' => true,
            'password' => false,
            'tags' => [],
            'editable_submissions_button_text' => 'Edit submission',
            'confetti_on_submission' => false,
            'auto_save' => false,
            'seo_meta' => [],
        ];
    }

    /**
     * Given a Notion database list of properties, format them as the front-end does:
     * - Adds id
     * - Adds placeholder, prefill, help
     * - Adds notion_name
     */
    public static function formatProperties($properties): array
    {
        return collect($properties)->map(function ($property) {
            return array_merge($property, [
                'id' => Str::uuid(),
                'placeholder' => $property['name'],
                'prefill' => null,
                'help' => 'Please fill this input',
                'notion_name' => $property['name'],
            ]);
        })->toArray();
    }
}
