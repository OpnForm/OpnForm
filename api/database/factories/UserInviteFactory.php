<?php

namespace Database\Factories;

use App\Models\UserInvite;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class UserInviteFactory extends Factory
{
    protected $model = UserInvite::class;

    public function definition(): array
    {
        return [
            'email' => $this->faker->unique()->safeEmail(),
            'role' => 'user',
            'workspace_id' => Workspace::factory(),
            'token' => Str::random(100),
            'status' => UserInvite::PENDING_STATUS,
            'valid_until' => now()->addDays(7),
        ];
    }

    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'valid_until' => now()->subDays(1),
        ]);
    }

    public function accepted(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => UserInvite::ACCEPTED_STATUS,
        ]);
    }

    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => UserInvite::PENDING_STATUS,
        ]);
    }
}
