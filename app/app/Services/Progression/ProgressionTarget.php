<?php

namespace App\Services\Progression;

/**
 * 漸進法戦略が返す「今日の目標」の値オブジェクト。
 *
 * イミュータブル(readonly)。
 */
final readonly class ProgressionTarget
{
    /**
     * @param  'weight'|'reps'  $type  前回からの改善の種別。フロントの
     *                                 TargetDisplay はこの値で「重量が上がった」ことだけを強調表示する。
     */
    public function __construct(
        public float $weight,
        public int $reps,
        public string $type,
    ) {}

    /**
     * workouts.progression_snapshot(JSON)への保存・Inertia へのシリアライズ用。
     *
     * @return array{weight: float, reps: int, type: string}
     */
    public function toArray(): array
    {
        return [
            'weight' => $this->weight,
            'reps' => $this->reps,
            'type' => $this->type,
        ];
    }
}
