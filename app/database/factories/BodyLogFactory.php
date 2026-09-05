<?php

namespace Database\Factories;

use App\Models\BodyLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BodyLog>
 */
class BodyLogFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'measured_on' => fake()->unique()->date(),
            'weight_kg' => fake()->randomFloat(2, 50, 90),
            'body_fat_percentage' => null,
            'memo' => null,
        ];
    }
}
