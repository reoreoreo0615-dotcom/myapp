<?php

namespace App\Http\Controllers;

use App\Enums\Equipment;
use App\Enums\MovementType;
use App\Enums\MuscleGroup;
use App\Http\Requests\StoreExerciseRequest;
use App\Models\Exercise;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Redirect;

class ExerciseController extends Controller
{
    /**
     * 器具区分ごとの重量刻み幅(kg)。ExerciseSeeder と同じ既定値を使う。
     *
     * @var array<string, float>
     */
    private const WEIGHT_INCREMENT = [
        'barbell' => 2.50,
        'dumbbell' => 2.00,
        'machine' => 5.00,
        'cable' => 2.50,
        'bodyweight' => 1.25,
    ];

    /**
     * Create a custom exercise owned by the current user.
     *
     * movement_type / is_bodyweight / weight_increment / target_rep_min-max は
     * MVP の入力フォームでは求めず、muscle_group と equipment から妥当な既定値を
     * 自動で導出する(movement_type は Phase 2 まで未使用のため、正確な分類より
     * NOT NULL 制約を満たす妥当な値であることを優先した)。
     */
    public function store(StoreExerciseRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $muscleGroup = MuscleGroup::from($data['muscle_group']);
        $equipment = Equipment::from($data['equipment']);

        Exercise::create([
            'user_id' => $request->user()->id,
            'name' => $data['name'],
            'muscle_group' => $muscleGroup,
            'movement_type' => $this->movementTypeFor($muscleGroup),
            'equipment' => $equipment,
            'is_bodyweight' => $equipment === Equipment::Bodyweight,
            'weight_increment' => self::WEIGHT_INCREMENT[$equipment->value],
            'target_rep_min' => 8,
            'target_rep_max' => 12,
            'sort_order' => 999,
        ]);

        return Redirect::back()->with('success', '種目を追加しました。');
    }

    private function movementTypeFor(MuscleGroup $muscleGroup): MovementType
    {
        return match ($muscleGroup) {
            MuscleGroup::Chest, MuscleGroup::Shoulders, MuscleGroup::Arms => MovementType::Push,
            MuscleGroup::Back => MovementType::Pull,
            MuscleGroup::Legs => MovementType::Legs,
            MuscleGroup::Core => MovementType::Core,
        };
    }
}
