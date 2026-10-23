<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'full_name' => fake()->name(),
            'phone_number' => fake()->unique()->numerify('628##########'),
            'role' => 'member',
            'password_hash' => null,
            'is_active' => true,
            'consent_at' => now(),
            'consent_version' => '1.2',
        ];
    }
}
