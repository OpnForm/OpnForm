<?php

namespace Database\Factories;

use App\Enterprise\Oidc\Models\IdentityConnection;
use App\Enterprise\Oidc\Models\UserIdentity;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Enterprise\Oidc\Models\UserIdentity>
 */
class UserIdentityFactory extends Factory
{
    protected $model = UserIdentity::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'connection_id' => IdentityConnection::factory(),
            'subject' => $this->faker->uuid(),
            'email' => $this->faker->email(),
            'claims' => [
                'sub' => $this->faker->uuid(),
                'email' => $this->faker->email(),
                'name' => $this->faker->name,
            ],
        ];
    }
}
