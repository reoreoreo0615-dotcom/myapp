<?php

namespace App\Services\Progression;

use App\Services\ProgressionService;

/**
 * 漸進法(progressive overload)の戦略インターフェース。
 *
 * 呼び出し側(WorkoutProgressionSnapshotService 等)はこのインターフェースだけを
 * 知っていればよく、どの実装クラス(ダブルプログレッション/リニア/5×5)が
 * 使われているかを意識しない。実装クラスの選択は
 * {@see ProgressionStrategyFactory} の1箇所に閉じる。
 *
 * 実装クラスは {@see ProgressionService} と同じく Eloquent /
 * DB に依存しない純粋なロジックとして書くこと。
 */
interface ProgressionStrategy
{
    /**
     * 次に狙う目標を返す。履歴(前回の作業セット)が無ければ null。
     */
    public function nextTarget(ProgressionContext $context): ?ProgressionTarget;

    /**
     * 画面に出す戦略名(「ダブルプログレッション」等)。
     */
    public function label(): string;
}
