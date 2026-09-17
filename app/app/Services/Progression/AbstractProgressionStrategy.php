<?php

namespace App\Services\Progression;

use App\Services\ProgressionService;

/**
 * 漸進法の共通処理を持つ抽象クラス。
 *
 * すべての漸進法は「前回のトップセットを取り出し、履歴が無ければ目標を出さない」
 * という手順を共有している。以前は Double / Linear / FiveByFive の3クラスに
 * 同じ処理が重複して書かれていたため、ここに一度だけ実装する(Template Method)。
 *
 * - nextTarget() は final。手順そのものはサブクラスに変えさせない
 * - サブクラスは「トップセットから目標をどう計算するか」だけを calculate() に書く
 *
 * ポリモーフィズムは ProgressionStrategy インターフェースが担い、
 * この抽象クラスは実装の共有(継承)を担う。呼び出し側は引き続き
 * インターフェースだけを知っていればよい。
 */
abstract class AbstractProgressionStrategy implements ProgressionStrategy
{
    public function __construct(
        protected readonly ProgressionService $progressionService,
    ) {}

    final public function nextTarget(ProgressionContext $context): ?ProgressionTarget
    {
        $topSet = $this->progressionService->pickTopSet($context->lastWorkingSets);

        if ($topSet === null) {
            return null;
        }

        return $this->calculate($topSet, $context);
    }

    /**
     * トップセットから今日の目標を計算する。
     *
     * 履歴が無いケースは基底クラスで処理済みなので、ここに来る時点で
     * $topSet は必ず存在する。
     *
     * @param  array{weight: float, reps: int, is_warmup: bool}  $topSet
     */
    abstract protected function calculate(array $topSet, ProgressionContext $context): ?ProgressionTarget;

    /**
     * 重量を decimal(5,2) の精度に丸める。浮動小数の誤差を持ち込まないため。
     */
    protected function roundWeight(float $weight): float
    {
        return round($weight, 2);
    }
}
