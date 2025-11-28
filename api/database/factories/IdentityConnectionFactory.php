<?php

namespace Database\Factories;

use App\Enterprise\Oidc\Models\IdentityConnection;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Enterprise\Oidc\Models\IdentityConnection>
 */
class IdentityConnectionFactory extends Factory
{
    protected $model = IdentityConnection::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'workspace_id' => null,
            'name' => 'Test Connection ' . $this->faker->randomNumber(4) . ' SSO',
            'slug' => 'test-connection-' . $this->faker->randomNumber(4),
            'domain' => 'example' . $this->faker->randomNumber(3) . '.com',
            'type' => IdentityConnection::TYPE_OIDC,
            'issuer' => 'https://idp.example' . $this->faker->randomNumber(3) . '.com',
            'client_id' => \Illuminate\Support\Str::uuid()->toString(),
            'client_secret' => \Illuminate\Support\Str::random(32),
            'scopes' => ['openid', 'profile', 'email'],
            'options' => [],
            'redirect_path' => null,
            'enabled' => true,
        ];
    }

    /**
     * Indicate that the connection is for a workspace.
     */
    public function forWorkspace(?Workspace $workspace = null): static
    {
        return $this->state(fn (array $attributes) => [
            'workspace_id' => $workspace?->id ?? Workspace::factory(),
        ]);
    }

    /**
     * Indicate that the connection is disabled.
     */
    public function disabled(): static
    {
        return $this->state(fn (array $attributes) => [
            'enabled' => false,
        ]);
    }

    /**
     * Set role mappings.
     */
    public function withRoleMappings(array $mappings): static
    {
        return $this->state(fn (array $attributes) => [
            'options' => array_merge($attributes['options'] ?? [], [
                'group_role_mappings' => $mappings,
            ]),
        ]);
    }

    /**
     * Set field mappings.
     */
    public function withFieldMappings(array $mappings): static
    {
        return $this->state(fn (array $attributes) => [
            'options' => array_merge($attributes['options'] ?? [], [
                'field_mappings' => $mappings,
            ]),
        ]);
    }
}
