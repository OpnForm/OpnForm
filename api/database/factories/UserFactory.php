<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class UserFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = User::class;

    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition()
    {
        return [
            'name' => $this->faker->name,
            'email' => $this->faker->unique()->safeEmail,
            'hear_about_us' => 'google',
            'email_verified_at' => now(),
            'password' => '$2y$10$Ey6obpHoTtSrMqGYjXf7Uu21i.pUqNy/rcG6etOAnKxtYCZmMHC1.', // Abcd@1234
            'remember_token' => Str::random(10),
        ];
    }
}
