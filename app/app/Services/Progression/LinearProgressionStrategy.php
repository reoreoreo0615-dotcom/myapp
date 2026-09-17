<?php

namespace App\Services\Progression;

/**
 * リニアプログレッション。
 *
 * 初心者向け: レップ数のいかんに関わらず、前回のトップセットの重量に
 * increment を毎回足す。レップ数は種目の目標下限(repMin)に固定する
 * (毎回重量が伸びるため、レップを積み増す方向には誘導しない)。
 *
 * ダブルプログレッションと違い「レップアップ」フェーズが存在しないため、
 * type は常に 'weight'。
 */
final class LinearProgressionStrategy extends AbstractProgressionStrategy
{
    protected function calculate(array $topSet, ProgressionContext $context): ?ProgressionTarget
    {
        return new ProgressionTarget(
            weight: $this->roundWeight($topSet['weight'] + $context->weightIncrement),
            reps: $context->repMin,
            type: 'weight',
        );
    }

    public function label(): string
    {
        return 'リニアプログレッション';
    }
}
