<?php

namespace App\Services\Progression;

use App\Services\ProgressionService;

/**
 * ダブルプログレッション(MVP からの既定の漸進法)。
 *
 * レップが目標上限(repMax)に達したら重量を increment 分上げてレップを
 * 下限(repMin)にリセットし、そうでなければ重量は据え置きでレップを
 * 1回増やす。
 *
 * Issue #27: このクラスは既存の {@see ProgressionService::nextTarget()} の
 * 計算そのものをそのまま使う(振る舞いを変えない)。トップセットの選定
 * ({@see ProgressionService::pickTopSet()})も同様に既存ロジックへ委譲する。
 * ProgressionService 自体には一切手を加えていない
 * (既存の35件のユニットテストが無変更で通ることの根拠)。
 */
final class DoubleProgressionStrategy extends AbstractProgressionStrategy
{
    protected function calculate(array $topSet, ProgressionContext $context): ?ProgressionTarget
    {
        $result = $this->progressionService->nextTarget(
            $topSet['weight'],
            $topSet['reps'],
            $context->weightIncrement,
            $context->repMin,
            $context->repMax,
        );

        if ($result === null) {
            // pickTopSet() が null を返さない限りここには来ないが、
            // ProgressionService::nextTarget() のシグネチャに合わせて防御的に扱う。
            return null;
        }

        return new ProgressionTarget($result['weight'], $result['reps'], $result['type']);
    }

    public function label(): string
    {
        return 'ダブルプログレッション';
    }
}
