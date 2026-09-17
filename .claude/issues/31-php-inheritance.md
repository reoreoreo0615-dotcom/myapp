社内評価(ADCP)の学習項目「PHP: オブジェクト指向 — 継承」に対応する**自作の継承階層がコードに存在しない**。

## 発覚した経緯
ADCP の記載と成果物を照合した結果、以下が判明した。

- PHP の `extends` はすべてフレームワークの基底クラス(Controller / FormRequest / Model 等)のみ
- ポリモーフィズムは `interface` / `implements` で実現しており、継承とは別物
- **スプレッドシートの「PHP: 継承」の行が JavaScript のコード(`resources/js/Utils/notifiers/`)を参照していた**(素案作成時の誤り)

## 対処方針
漸進法の3つの戦略クラスに**実際に重複しているコード**がある。これを抽象クラスに集約する。

```php
// Double / Linear / FiveByFive の3クラスすべてに同じ処理がある
$topSet = $this->progressionService->pickTopSet($context->lastWorkingSets);
if (! $topSet) { return null; }
```

**取ってつけた継承ではなく、重複を除くための正当な継承にする。**(Template Method パターン)

```
ProgressionStrategy (interface)          ← ポリモーフィズム
  └─ AbstractProgressionStrategy (abstract) ← 継承。共通処理を1箇所に
       ├─ DoubleProgressionStrategy
       ├─ LinearProgressionStrategy
       └─ FiveByFiveStrategy
```

- [ ] `AbstractProgressionStrategy` が `nextTarget()` を `final` で実装し、
      「履歴がなければ null」の判定を1箇所に集約する
- [ ] サブクラスはトップセットを受け取って目標を計算する `calculate()` だけを実装する
- [ ] 重量の丸め処理も基底クラスに置く

## 受入条件
- [ ] 3クラスが抽象クラスを継承し、重複していた処理が消えている
- [ ] **既存のテストを1件も変更せずに全件通る**(振る舞いが変わっていない証明)
- [ ] 抽象クラスの振る舞いを検証するテストを追加
- [ ] スプレッドシートの「PHP: 継承」の行を正しい参照先に修正
