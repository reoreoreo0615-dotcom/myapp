<?php

namespace Database\Factories;

use App\Models\Exercise;
use App\Models\Workout;
use App\Models\WorkoutSet;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WorkoutSet>
 */
class WorkoutSetFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'workout_id' => Workout::factory(),
            'exercise_id' => Exercise::factory(),
            'set_number' => 1,
            'weight' => 60.00,
            'reps' => 10,
            'rpe' => null,
            'is_warmup' => false,
            'memo' => null,
        ];
    }

    /**
     * Mark this set as a warmup set.
     */
    public function warmup(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_warmup' => true,
        ]);
    }
}
