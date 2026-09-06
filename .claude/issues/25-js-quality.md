**現在テストは220件あるが、すべて PHP。JS/Vue のテストは0件。**

フロントに壊れても気づけないロジックが溜まっている。

| 対象 | 壊れても気づけない内容 |
| --- | --- |
| `NumberStepper.vue` | 長押しの連続増減、`pointerActive` フラグによる二重発火防止、min/max のクランプ、小数第2位の丸め |
| `IntervalTimer.vue` | 開始時刻との差分計算、`localStorage` からの復元、目標到達の通知抑制 |
| `Utils/format.js` | 数値整形 |
| `restTimerAlert.js` | `navigator.vibrate` の feature-detect |
| `FirstTimeTip.vue` | `localStorage` 失敗時のフォールバック |

これらは**すべて手動確認のみで通してきた。**

---

## ① Vitest 導入と主要部品のテスト

- [ ] `vitest` + `@vue/test-utils` + `jsdom` を devDependencies に追加
- [ ] `npm run test:js` を `package.json` に追加
- [ ] Makefile に `test-js` を追加

### 必ずテストすること

**`NumberStepper`**
- タップ1回で `step` ぶん増減する
- `min` / `max` でクランプされる
- 小数の加算で誤差が出ない(`0.1 + 0.2` 問題)
- 長押しで連続増減する(タイマーはフェイクタイマーで検証)
- `pointerdown` 経由の click が二重発火しない

**`IntervalTimer`**
- **経過時間が開始時刻との差分で計算される**
  (`Date.now()` をモックし、時間を飛ばしても正しいこと)
- `localStorage` から復元できる
- **離れていた間に目標を超えていた場合、復帰時に通知が鳴らない**
- `localStorage` が使えない環境でも壊れない

**`Utils/format.js`** — 桁区切り、小数の表示

## ② Prettier / ESLint

- [ ] Prettier 導入(Vue / JS)。既存コードを一括整形
- [ ] ESLint 導入(`eslint-plugin-vue`)
- [ ] `npm run lint:js` / `lint:js:fix` を追加
- [ ] Makefile に追加
- [ ] **一括整形は独立したコミットにすること。** 機能変更と混ぜると差分が読めなくなる

## ③ CI への組み込み

- [ ] `.github/workflows/ci.yml` に JS テストと lint を追加
- [ ] **`npm run build` より前に lint、後にテスト**の順にすること
      (#5 で build 前に PHP テストを置いて Vite manifest が無く落ちた前例がある)

## ④ カスタムエラーページ

- [ ] 404 / 403 / 500 の Inertia ページを作る
- [ ] デザイントークンに沿った見た目にする
- [ ] ダッシュボードへ戻る導線を置く

## 受入条件
- [ ] `npm run test:js` が動き、上記の観点がテストされている
- [ ] **`IntervalTimer` の差分計算が、時間をモックしたテストで検証されている**
- [ ] Prettier / ESLint が動作し、一括整形が独立コミットになっている
- [ ] CI で PHP テスト・JS テスト・両方の lint が走る
- [ ] 404 / 403 / 500 でカスタムページが出る
- [ ] 既存の220件の PHP テストが通る
