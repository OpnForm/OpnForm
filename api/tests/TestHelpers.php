<?php

namespace Tests;

use App\Models\Forms\Form;
use App\Models\User;
use App\Models\Workspace;
use App\Service\Forms\FormSubmissionDataFactory;
use Database\Factories\FormFactory;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

trait TestHelpers
{
    /**
     * Creates a workspace for a user
     */
    public function createUserWorkspace(User $user)
    {
        if (!$user) {
            return null;
        }

        $workspace = Workspace::create([
            'name' => 'Form Testing',
            'icon' => '🧪',
        ]);

        $user->workspaces()->sync([
            $workspace->id => [
                'role' => 'admin',
            ],
        ], false);

        return $workspace;
    }

    /**
     * Creates a form model instance without saving it to the database
     *
     * @param User $user The user who owns the form
     * @param Workspace $workspace The workspace the form belongs to
     * @param array $data Additional data for the form
     * @return Form The form model instance
     */
    public function makeForm(User $user, Workspace $workspace, array $data = [])
    {
        $dbProperties = [
            [
                'name' => 'Name',
                'type' => 'text',
                'hidden' => false,
                'required' => false,
            ],
            [
                'name' => 'Message',
                'type' => 'text',
                'hidden' => false,
                'required' => false,
                'multi_lines' => true,
            ],
            [
                'name' => 'Number',
                'type' => 'number',
                'hidden' => false,
                'required' => false,
            ],
            [
                'name' => 'Rating',
                'type' => 'rating',
                'hidden' => false,
                'required' => false,
                'rating_max_value' => 5
            ],
            [
                'name' => 'Scale',
                'type' => 'scale',
                'hidden' => false,
                'required' => false,
                'scale_min_value' => 1,
                'scale_max_value' => 10,
                'scale_step_value' => 1,
            ],
            [
                'name' => 'Slider',
                'type' => 'slider',
                'hidden' => false,
                'required' => false,
                'slider_min_value' => 1,
                'slider_max_value' => 100,
                'slider_step_value' => 1,
            ],
            [
                'name' => 'Select',
                'type' => 'select',
                'hidden' => false,
                'required' => false,
                'allow_creation' => false,
                'select' => [
                    'options' => [['id' => 'First', 'name' => 'First'], ['id' => 'Second', 'name' => 'Second']],
                ],
            ],
            [
                'name' => 'Multi Select',
                'type' => 'multi_select',
                'hidden' => false,
                'required' => false,
                'allow_creation' => false,
                'multi_select' => [
                    'options' => [['id' => 'One', 'name' => 'One'], ['id' => 'Two', 'name' => 'Two'], ['id' => 'Three', 'name' => 'Three']],
                ],
            ],
            [
                'name' => 'Date',
                'type' => 'date',
                'hidden' => false,
                'required' => false,
            ],
            [
                'name' => 'Checkbox',
                'type' => 'checkbox',
                'hidden' => false,
                'required' => false,
            ],
            [
                'name' => 'URL',
                'type' => 'url',
                'hidden' => false,
                'required' => false,
            ],
            [
                'name' => 'Email',
                'type' => 'email',
                'hidden' => false,
                'required' => false,
            ],
            [
                'name' => 'Phone Number',
                'type' => 'phone_number',
                'hidden' => false,
                'required' => false,
            ],
            [
                'name' => 'Files',
                'type' => 'files',
                'hidden' => false,
                'required' => false,
            ],
        ];

        return Form::factory()
            ->withProperties(FormFactory::formatProperties($dbProperties))
            ->forWorkspace($workspace)
            ->createdBy($user)
            ->make($data);
    }

    /**
     * Creates a form with the given data
     *
     * @param User $user The user who owns the form
     * @param Workspace $workspace The workspace the form belongs to
     * @param array $data Additional data for the form
     * @return Form The created form
     */
    public function createForm(User $user, Workspace $workspace, array $data = [])
    {
        $form = $this->makeForm($user, $workspace, $data);
        $form->save();

        return $form;
    }

    public function createUser(array $data = [])
    {
        return \App\Models\User::factory()->create($data);
    }

    public function createProUser()
    {
        $user = $this->createUser();

        $user->subscriptions()->create([
            'type' => 'default',
            'stripe_id' => Str::random(),
            'stripe_status' => 'active',
            'stripe_price' => Str::random(),
            'quantity' => 1,
        ]);

        return $user;
    }

    public function createTrialingUser()
    {
        $user = $this->createUser();
        $user->subscriptions()->create([
            'name' => 'default',
            'stripe_id' => Str::random(),
            'stripe_status' => 'trialing',
            'stripe_price' => Str::random(),
            'trial_ends_at' => now()->addDays(5),
            'quantity' => 1,
        ]);

        return $user;
    }

    public function actingAsUser(?User $user = null)
    {
        if ($user == null) {
            $user = $this->createUser();
        }

        $this->postJson('/login', [
            'email' => $user->email,
            'password' => 'Abcd@1234',
        ])->assertSuccessful();

        return $user;
    }

    /**
     * Creates a user with a Pro subscription
     *
     * @return User|\Illuminate\Database\Eloquent\Collection|\Illuminate\Database\Eloquent\Model|null
     */
    public function actingAsProUser(?User $user = null)
    {
        if ($user == null) {
            $user = $this->createProUser();
        }

        return $this->actingAsUser($user);
    }

    public function actingAsTrialingUser(User $user = null)
    {
        if ($user == null) {
            $user = $this->createTrialingUser();
        }

        return $this->actingAsUser($user);
    }

    public function actingAsGuest()
    {
        if (Auth::check()) {
            Auth::logout();
        }
        $this->assertGuest();
    }

    public function actingAsAdmin(?User $user = null)
    {
        if ($user == null) {
            // Create admin user by setting email in admin config
            $adminEmail = 'admin@test.com';
            config(['opnform.admin_emails' => [$adminEmail]]);
            $user = $this->createUser(['email' => $adminEmail]);
        }

        return $this->actingAsUser($user);
    }

    public function createFormIntegration($integrationId, $formId, $settings = [])
    {
        $data = [
            'status' => 'active',
            'integration_id' => $integrationId,
            'logic' => null,
            'data' => $settings
        ];

        $response = $this->postJson(route('open.forms.integrations.create', $formId), $data)
            ->assertSuccessful()
            ->assertJson([
                'type' => 'success',
                'message' => 'Form Integration was created.'
            ]);

        return (object) $response->json('form_integration.data');
    }

    /**
     * Generates fake form submission data for testing
     *
     * @param Form $form The form to generate data for
     * @param array $data Additional data to merge with the generated data
     * @param bool $asFormSubmissionData If true, formats data as stored in FormSubmission
     * @return array The generated submission data
     */
    public function generateFormSubmissionData(Form $form, array $data = [], bool $asFormSubmissionData = false)
    {
        $factory = new FormSubmissionDataFactory($form);

        if ($asFormSubmissionData) {
            $factory->asFormSubmissionData();
        }

        return $factory->createSubmissionData($data);
    }
}
