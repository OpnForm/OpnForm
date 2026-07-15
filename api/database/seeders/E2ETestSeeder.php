<?php

namespace Database\Seeders;

use App\Models\Forms\Form;
use App\Models\Forms\FormSubmission;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class E2ETestSeeder extends Seeder
{
    public function run(): void
    {
        FormSubmission::query()->delete();
        Form::withTrashed()->forceDelete();
        DB::table('user_workspace')->truncate();
        Workspace::query()->delete();
        User::query()->delete();

        $user = User::query()->create([
            'name' => 'Codex Admin',
            'email' => 'e2e@example.test',
            'password' => Hash::make('Abcd@1234'),
            'hear_about_us' => 'e2e',
            'email_verified_at' => now(),
        ]);

        $workspace = Workspace::query()->create([
            'name' => 'E2E Workspace',
            'icon' => '🧪',
        ]);

        $user->workspaces()->sync([
            $workspace->id => ['role' => User::ROLE_ADMIN],
        ]);

        $feedbackProperties = $this->formProperties([
            ['name' => 'Full name', 'type' => 'text', 'required' => true],
            ['name' => 'Email address', 'type' => 'email', 'required' => true],
            ['name' => 'Satisfaction', 'type' => 'rating', 'required' => true],
            ['name' => 'Feedback', 'type' => 'text'],
        ]);
        $feedbackForm = $this->createForm($user, $workspace, [
            'title' => 'Product feedback',
            'properties' => $feedbackProperties,
            'presentation_style' => 'classic',
            'visibility' => 'public',
        ]);
        $this->createSubmissions($feedbackForm, [
            [
                $feedbackProperties[0]['id'] => 'Alice Martin',
                $feedbackProperties[1]['id'] => 'alice@example.test',
                $feedbackProperties[2]['id'] => 5,
                $feedbackProperties[3]['id'] => 'The editor is quick and easy to use.',
            ],
            [
                $feedbackProperties[0]['id'] => 'Lucas Bernard',
                $feedbackProperties[1]['id'] => 'lucas@example.test',
                $feedbackProperties[2]['id'] => 4,
                $feedbackProperties[3]['id'] => 'More keyboard shortcuts would be useful.',
            ],
            [
                $feedbackProperties[0]['id'] => 'Maya Dupont',
                $feedbackProperties[1]['id'] => 'maya@example.test',
                $feedbackProperties[2]['id'] => 5,
                $feedbackProperties[3]['id'] => 'The focused presentation is great for surveys.',
            ],
        ]);

        $demoProperties = $this->formProperties([
            ['name' => 'Work email', 'type' => 'email', 'required' => true],
            ['name' => 'Company', 'type' => 'text', 'required' => true],
            [
                'name' => 'Team size',
                'type' => 'select',
                'required' => true,
                'options' => [
                    ['name' => '1-10', 'id' => '1-10'],
                    ['name' => '11-50', 'id' => '11-50'],
                    ['name' => '51+', 'id' => '51+'],
                ],
            ],
        ]);
        $demoForm = $this->createForm($user, $workspace, [
            'title' => 'Book a product demo',
            'properties' => $demoProperties,
            'presentation_style' => 'focused',
            'visibility' => 'public',
            'show_progress_bar' => true,
        ]);
        $this->createSubmissions($demoForm, [
            [
                $demoProperties[0]['id'] => 'camille@acme.test',
                $demoProperties[1]['id'] => 'Acme',
                $demoProperties[2]['id'] => '11-50',
            ],
            [
                $demoProperties[0]['id'] => 'noah@northstar.test',
                $demoProperties[1]['id'] => 'Northstar',
                $demoProperties[2]['id'] => '51+',
            ],
        ]);

        $this->createForm($user, $workspace, [
            'title' => 'Website redesign intake',
            'properties' => $this->formProperties([
                ['name' => 'Project name', 'type' => 'text', 'required' => true],
                ['name' => 'Target launch date', 'type' => 'date'],
            ]),
            'visibility' => 'draft',
        ]);
    }

    private function createForm(User $user, Workspace $workspace, array $attributes): Form
    {
        return Form::factory()
            ->forWorkspace($workspace)
            ->createdBy($user)
            ->create($attributes);
    }

    private function createSubmissions(Form $form, array $submissions): void
    {
        foreach ($submissions as $index => $data) {
            $form->submissions()->create([
                'data' => $data,
                'completion_time' => 30 + ($index * 15),
                'status' => FormSubmission::STATUS_COMPLETED,
                'meta' => [
                    'source' => 'codex-seed',
                    'utm_source' => 'local-demo',
                ],
                'public_id' => Str::uuid()->toString(),
            ]);
        }
    }

    private function formProperties(array $fields): array
    {
        return array_map(function (array $field) {
            return array_merge([
                'id' => Str::uuid()->toString(),
                'hidden' => false,
                'required' => false,
                'placeholder' => $field['name'],
                'prefill' => null,
                'help' => null,
                'notion_name' => $field['name'],
            ], $field);
        }, $fields);
    }
}
