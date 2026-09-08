<?php

namespace Database\Factories;

use App\Enums\Equipment;
use App\Enums\MovementType;
use App\Enums\MuscleGroup;
use App\Enums\ProgressionStrategyType;
use App\Models\Exercise;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Exercise>
 */
class ExerciseFactory extends Factory
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
            'name' => fake()->unique()->words(2, true),
            'muscle_group' => fake()->randomElement(MuscleGroup::cases()),
            'movement_type' => fake()->randomElement(MovementType::cases()),
            'equipment' => fake()->randomElement(Equipment::cases()),
            'is_bodyweight' => false,
            'weight_increment' => 2.50,
            'target_rep_min' => 8,
            'target_rep_max' => 12,
            'progression_strategy' => ProgressionStrategyType::Double,
            'sort_order' => 0,
        ];
    }

    /**
     * リニアプログレッションを使う種目。
     */
    public function linearProgression(): static
    {
        return $this->state(fn (array $attributes) => [
            'progression_strategy' => ProgressionStrategyType::Linear,
        ]);
    }

    /**
     * 5×5 プログレッションを使う種目。
     */
    public function fiveByFive(): static
    {
        return $this->state(fn (array $attributes) => [
            'progression_strategy' => ProgressionStrategyType::FiveByFive,
        ]);
    }
}
