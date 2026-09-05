<?php

namespace Database\Seeders;

use App\Enums\Equipment;
use App\Enums\MovementType;
use App\Enums\MuscleGroup;
use App\Models\Exercise;
use Illuminate\Database\Seeder;

class ExerciseSeeder extends Seeder
{
    /**
     * 器具区分ごとの重量刻み幅(kg)。
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
     * コンパウンド種目のレップレンジ。
     */
    private const COMPOUND_REPS = ['min' => 6, 'max' => 10];

    /**
     * アイソレーション種目のレップレンジ。
     */
    private const ISOLATION_REPS = ['min' => 10, 'max' => 15];

    /**
     * Seed the exercises table with the default 種目マスタ (user_id = null).
     *
     * 冪等性のため name をキーに updateOrCreate する。
     */
    public function run(): void
    {
        foreach ($this->exercises() as $exercise) {
            $reps = $exercise['compound'] ? self::COMPOUND_REPS : self::ISOLATION_REPS;
            $increment = self::WEIGHT_INCREMENT[$exercise['equipment']->value];

            Exercise::updateOrCreate(
                [
                    'user_id' => null,
                    'name' => $exercise['name'],
                ],
                [
                    'muscle_group' => $exercise['muscle_group'],
                    'movement_type' => $exercise['movement_type'],
                    'equipment' => $exercise['equipment'],
                    'is_bodyweight' => $exercise['is_bodyweight'],
                    'weight_increment' => $increment,
                    'target_rep_min' => $reps['min'],
                    'target_rep_max' => $reps['max'],
                    'sort_order' => $exercise['sort_order'],
                ]
            );
        }
    }

    /**
     * 投入する31種目の定義。
     *
     * NOTE: Issue #7 本文は「主要30種目」と題しつつ、部位別の内訳を数えると
     * 実際には31種目(chest 7 / back 6 / shoulders 5 / legs 6 / arms 5 / core 2)
     * が列挙されている。名称はすべて明示されているため、恣意的に1件を除外せず
     * 列挙された全31種目をそのまま投入する。件数の矛盾は呼び出し側へ報告する。
     *
     * @return list<array{
     *     name: string,
     *     muscle_group: MuscleGroup,
     *     movement_type: MovementType,
     *     equipment: Equipment,
     *     is_bodyweight: bool,
     *     compound: bool,
     *     sort_order: int,
     * }>
     */
    private function exercises(): array
    {
        return [
            // --- chest (7) ---
            ['name' => 'ベンチプレス', 'muscle_group' => MuscleGroup::Chest, 'movement_type' => MovementType::Push, 'equipment' => Equipment::Barbell, 'is_bodyweight' => false, 'compound' => true, 'sort_order' => 10],
            ['name' => 'インクラインベンチプレス', 'muscle_group' => MuscleGroup::Chest, 'movement_type' => MovementType::Push, 'equipment' => Equipment::Barbell, 'is_bodyweight' => false, 'compound' => true, 'sort_order' => 20],
            ['name' => 'ダンベルプレス', 'muscle_group' => MuscleGroup::Chest, 'movement_type' => MovementType::Push, 'equipment' => Equipment::Dumbbell, 'is_bodyweight' => false, 'compound' => true, 'sort_order' => 30],
            ['name' => 'インクラインダンベルプレス', 'muscle_group' => MuscleGroup::Chest, 'movement_type' => MovementType::Push, 'equipment' => Equipment::Dumbbell, 'is_bodyweight' => false, 'compound' => true, 'sort_order' => 40],
            ['name' => 'チェストプレス', 'muscle_group' => MuscleGroup::Chest, 'movement_type' => MovementType::Push, 'equipment' => Equipment::Machine, 'is_bodyweight' => false, 'compound' => true, 'sort_order' => 50],
            ['name' => 'ディップス', 'muscle_group' => MuscleGroup::Chest, 'movement_type' => MovementType::Push, 'equipment' => Equipment::Bodyweight, 'is_bodyweight' => true, 'compound' => true, 'sort_order' => 60],
            ['name' => 'ペックフライ', 'muscle_group' => MuscleGroup::Chest, 'movement_type' => MovementType::Push, 'equipment' => Equipment::Machine, 'is_bodyweight' => false, 'compound' => false, 'sort_order' => 70],

            // --- back (6) ---
            ['name' => 'デッドリフト', 'muscle_group' => MuscleGroup::Back, 'movement_type' => MovementType::Pull, 'equipment' => Equipment::Barbell, 'is_bodyweight' => false, 'compound' => true, 'sort_order' => 10],
            ['name' => '懸垂', 'muscle_group' => MuscleGroup::Back, 'movement_type' => MovementType::Pull, 'equipment' => Equipment::Bodyweight, 'is_bodyweight' => true, 'compound' => true, 'sort_order' => 20],
            ['name' => 'ラットプルダウン', 'muscle_group' => MuscleGroup::Back, 'movement_type' => MovementType::Pull, 'equipment' => Equipment::Cable, 'is_bodyweight' => false, 'compound' => true, 'sort_order' => 30],
            ['name' => 'ベントオーバーロウ', 'muscle_group' => MuscleGroup::Back, 'movement_type' => MovementType::Pull, 'equipment' => Equipment::Barbell, 'is_bodyweight' => false, 'compound' => true, 'sort_order' => 40],
            ['name' => 'シーテッドロウ', 'muscle_group' => MuscleGroup::Back, 'movement_type' => MovementType::Pull, 'equipment' => Equipment::Cable, 'is_bodyweight' => false, 'compound' => true, 'sort_order' => 50],
            ['name' => 'ダンベルロウ', 'muscle_group' => MuscleGroup::Back, 'movement_type' => MovementType::Pull, 'equipment' => Equipment::Dumbbell, 'is_bodyweight' => false, 'compound' => true, 'sort_order' => 60],

            // --- shoulders (5) ---
            ['name' => 'ショルダープレス', 'muscle_group' => MuscleGroup::Shoulders, 'movement_type' => MovementType::Push, 'equipment' => Equipment::Barbell, 'is_bodyweight' => false, 'compound' => true, 'sort_order' => 10],
            ['name' => 'ダンベルショルダープレス', 'muscle_group' => MuscleGroup::Shoulders, 'movement_type' => MovementType::Push, 'equipment' => Equipment::Dumbbell, 'is_bodyweight' => false, 'compound' => true, 'sort_order' => 20],
            ['name' => 'サイドレイズ', 'muscle_group' => MuscleGroup::Shoulders, 'movement_type' => MovementType::Push, 'equipment' => Equipment::Dumbbell, 'is_bodyweight' => false, 'compound' => false, 'sort_order' => 30],
            ['name' => 'リアレイズ', 'muscle_group' => MuscleGroup::Shoulders, 'movement_type' => MovementType::Pull, 'equipment' => Equipment::Dumbbell, 'is_bodyweight' => false, 'compound' => false, 'sort_order' => 40],
            ['name' => 'アップライトロウ', 'muscle_group' => MuscleGroup::Shoulders, 'movement_type' => MovementType::Pull, 'equipment' => Equipment::Barbell, 'is_bodyweight' => false, 'compound' => false, 'sort_order' => 50],

            // --- legs (6) ---
            ['name' => 'スクワット', 'muscle_group' => MuscleGroup::Legs, 'movement_type' => MovementType::Legs, 'equipment' => Equipment::Barbell, 'is_bodyweight' => false, 'compound' => true, 'sort_order' => 10],
            ['name' => 'レッグプレス', 'muscle_group' => MuscleGroup::Legs, 'movement_type' => MovementType::Legs, 'equipment' => Equipment::Machine, 'is_bodyweight' => false, 'compound' => true, 'sort_order' => 20],
            ['name' => 'ルーマニアンデッドリフト', 'muscle_group' => MuscleGroup::Legs, 'movement_type' => MovementType::Legs, 'equipment' => Equipment::Barbell, 'is_bodyweight' => false, 'compound' => true, 'sort_order' => 30],
            ['name' => 'レッグエクステンション', 'muscle_group' => MuscleGroup::Legs, 'movement_type' => MovementType::Legs, 'equipment' => Equipment::Machine, 'is_bodyweight' => false, 'compound' => false, 'sort_order' => 40],
            ['name' => 'レッグカール', 'muscle_group' => MuscleGroup::Legs, 'movement_type' => MovementType::Legs, 'equipment' => Equipment::Machine, 'is_bodyweight' => false, 'compound' => false, 'sort_order' => 50],
            ['name' => 'カーフレイズ', 'muscle_group' => MuscleGroup::Legs, 'movement_type' => MovementType::Legs, 'equipment' => Equipment::Machine, 'is_bodyweight' => false, 'compound' => false, 'sort_order' => 60],

            // --- arms (5) ---
            ['name' => 'バーベルカール', 'muscle_group' => MuscleGroup::Arms, 'movement_type' => MovementType::Pull, 'equipment' => Equipment::Barbell, 'is_bodyweight' => false, 'compound' => false, 'sort_order' => 10],
            ['name' => 'ダンベルカール', 'muscle_group' => MuscleGroup::Arms, 'movement_type' => MovementType::Pull, 'equipment' => Equipment::Dumbbell, 'is_bodyweight' => false, 'compound' => false, 'sort_order' => 20],
            ['name' => 'ハンマーカール', 'muscle_group' => MuscleGroup::Arms, 'movement_type' => MovementType::Pull, 'equipment' => Equipment::Dumbbell, 'is_bodyweight' => false, 'compound' => false, 'sort_order' => 30],
            ['name' => 'トライセプスプレスダウン', 'muscle_group' => MuscleGroup::Arms, 'movement_type' => MovementType::Push, 'equipment' => Equipment::Cable, 'is_bodyweight' => false, 'compound' => false, 'sort_order' => 40],
            ['name' => 'スカルクラッシャー', 'muscle_group' => MuscleGroup::Arms, 'movement_type' => MovementType::Push, 'equipment' => Equipment::Barbell, 'is_bodyweight' => false, 'compound' => false, 'sort_order' => 50],

            // --- core (2) ---
            ['name' => 'アブローラー', 'muscle_group' => MuscleGroup::Core, 'movement_type' => MovementType::Core, 'equipment' => Equipment::Bodyweight, 'is_bodyweight' => true, 'compound' => false, 'sort_order' => 10],
            ['name' => 'ハンギングレッグレイズ', 'muscle_group' => MuscleGroup::Core, 'movement_type' => MovementType::Core, 'equipment' => Equipment::Bodyweight, 'is_bodyweight' => true, 'compound' => false, 'sort_order' => 20],
        ];
    }
}
