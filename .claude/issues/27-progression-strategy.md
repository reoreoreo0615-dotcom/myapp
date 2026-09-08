漸進法を差し替え可能にする。**インターフェースによるポリモーフィズムの実装。**

## 実用上の動機(練習のための実装ではない)

**トレーニングの漸進法は1つではない。** 現在は `ProgressionService` に
ダブルプログレッションが直接書かれており、他の方法に切り替えられない。

| 漸進法 | 内容 | 向く人 |
| --- | --- | --- |
| **ダブルプログレッション**(現行) | レップが上限に達したら重量を上げてレップをリセット | 中級者、一般的な種目 |
| **リニアプログレッション** | 毎回一定量ずつ重量を上げる | 初心者。伸びしろが大きく毎回上げられる |
| **5×5** | 5回×5セットを全て達成したら重量を上げる | 高重量・低レップの筋力向上目的 |

初心者はリニアで伸び続けるのに、ダブルプログレッションだとレップを増やす方向に
誘導されて重量が上がらない。**種目ごとに使い分けられるべき。**

## 設計

### インターフェース
```php
interface ProgressionStrategy
{
    /** 次に狙う目標を返す。履歴が無ければ null */
    public function nextTarget(ProgressionContext $context): ?ProgressionTarget;

    /** 画面に出す戦略名(「ダブルプログレッション」等) */
    public function label(): string;
}
```

- [ ] `ProgressionContext`(前回のトップセット・種目設定・履歴)と
      `ProgressionTarget`(重量・レップ・種別)を値オブジェクトとして定義するか、
      配列のままにするか**判断して理由を報告すること**
- [ ] 実装クラス3つ: `DoubleProgressionStrategy` / `LinearProgressionStrategy` /
      `FiveByFiveStrategy`
- [ ] **既存の `ProgressionService` の振る舞いを変えないこと。**
      `DoubleProgressionStrategy` は現在のロジックをそのまま移す。
      既存の35件のユニットテストが**無変更で通ること**が受入条件

### 種目ごとの戦略選択
- [ ] `exercises` に `progression_strategy`(enum, default double)を追加
- [ ] 種目マスタ管理画面(#14)から変更できるようにする
- [ ] 記録画面に現在の戦略名を小さく表示する

## ポリモーフィズムの要点(この Issue の学習目的)

**呼び出し側が実装クラスを知らずに使えること。**

```php
// ✗ これでは意味がない
if ($exercise->progression_strategy === 'linear') { ... } else { ... }

// ✓ 呼び出し側は interface しか知らない
$strategy = $this->strategyFactory->for($exercise);
$target = $strategy->nextTarget($context);
```

- [ ] **戦略の分岐は Factory 1箇所に閉じること。** 呼び出し側に if / match を散らさない
- [ ] 新しい戦略を追加するとき、既存クラスを変更せずに済むこと(開放閉鎖の原則)

## 受入条件
- [ ] `ProgressionStrategy` インターフェースと実装3クラス
- [ ] **既存の35件のユニットテストが無変更で通る**(振る舞いが変わっていない証明)
- [ ] 各戦略のユニットテスト(境界値を含む)
- [ ] 戦略の分岐が Factory 1箇所に閉じている
- [ ] 種目ごとに戦略を選べ、記録画面の目標が切り替わる(Featureテストで検証)
- [ ] `migrate:fresh` / `rollback` を `myapp_testing` で検証し、
      **開発DB `myapp` に `migrate` を適用すること**
- [ ] 既存の338件のPHPテストが通る
