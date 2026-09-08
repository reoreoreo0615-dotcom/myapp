<?php

namespace App\Services\Progression;

use App\Services\ProgressionService;

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
final class LinearProgressionStrategy implements ProgressionStrategy
{
    public function __construct(
        private readonly ProgressionService $progressionService,
    ) {}

    public function nextTarget(ProgressionContext $context): ?ProgressionTarget
    {
        $topSet = $this->progressionService->pickTopSet($context->lastWorkingSets);

        if ($topSet === null) {
            return null;
        }

        return new ProgressionTarget(
            weight: round($topSet['weight'] + $context->weightIncrement, 2),
            reps: $context->repMin,
            type: 'weight',
        );
    }

    public function label(): string
    {
        return 'リニアプログレッション';
    }
}
