<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\Workout;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Workout>
 */
class WorkoutFactory extends Factory
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
            'routine_id' => null,
            'performed_on' => fake()->date(),
            'started_at' => null,
            'finished_at' => null,
            'memo' => null,
        ];
    }
}
