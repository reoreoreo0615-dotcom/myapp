<?php

namespace App\Services\Progression;

use App\Services\ProgressionService;

/**
 * 漸進法の判定に必要な入力をまとめた値オブジェクト。
 *
 * {@see ProgressionService} と同じ理由(Eloquent に依存しない
 * 純粋なロジックの境界)で、値はすべてこのオブジェクトの生成時に確定させ、
 * 各 {@see ProgressionStrategy} 実装はこれ以外の入力(DB・Eloquent モデル)
 * を一切参照しない。
 *
 * イミュータブル(readonly)。生成後に内容を変更することはない。
 */
final readonly class ProgressionContext
{
    /**
     * @param  array<int, array{weight: float, reps: int, is_warmup: bool}>  $lastWorkingSets  前回このワークアウト以前に記録された、この種目のウォームアップを除く全セット(トップセットの1件だけではない。5×5 戦略のように「全セット」の達成状況が必要な戦略のため)。
     * @param  float  $weightIncrement  種目ごとの重量刻み幅(exercises.weight_increment)。
     * @param  int  $repMin  種目ごとの目標レップ数(下限)。
     * @param  int  $repMax  種目ごとの目標レップ数(上限)。
     */
    public function __construct(
        public array $lastWorkingSets,
        public float $weightIncrement,
        public int $repMin,
        public int $repMax,
    ) {}
}
