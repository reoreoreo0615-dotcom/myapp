インターバルタイマーの通知手段を **JavaScript のクラスと継承**で差し替え可能にする。

## 実用上の動機(練習のための実装ではない)

Issue #20 で判明した問題:

> **iOS Safari は Vibration API 未実装。iPhone ではバイブが鳴らない。**

現在 `restTimerAlert.js` は音・バイブ・視覚の処理が1つの関数に混ざっており、
**環境に応じて通知手段を差し替えられない。**

ジムは騒がしく、音だけでは気づかない。iPhone ではバイブも使えない。
**環境ごとに使える手段を組み合わせて通知する構造**が必要。

## 設計

```
RestNotifier (基底クラス)
├── isSupported()   … この環境で使えるか
├── notify()        … 通知する
└── label()         … 設定画面での表示名

  ├─ SoundNotifier    … Web Audio API。AudioContext のアンロック処理を持つ
  ├─ VibrationNotifier … navigator.vibrate。iOS では isSupported() が false
  └─ VisualNotifier    … 画面のフラッシュ・色変化。どの環境でも必ず使える

CompositeNotifier … 複数の Notifier を束ね、使えるものすべてで通知する
```

## JS の OOP の要点(この Issue の学習目的)

- [ ] **`class` と `extends` を使うこと。** 関数とオブジェクトリテラルで済ませない
- [ ] 基底クラスで**共通の振る舞い**(未対応時に例外を投げず黙って何もしない等)を定義し、
      サブクラスは**差分だけ**を書く
- [ ] **`CompositeNotifier` は個々の Notifier の型を知らずに扱えること**(ポリモーフィズム)
      ```js
      // ✗ これでは意味がない
      if (notifier instanceof VibrationNotifier) { ... }

      // ✓ 型を知らずに一様に扱う
      this.notifiers.filter(n => n.isSupported()).forEach(n => n.notify());
      ```
- [ ] 新しい通知手段(通知API、画面点滅など)を**既存クラスを変更せずに**追加できること

## 既存の振る舞いを変えないこと

`IntervalTimer.vue` から見た挙動は現状と同じであること。
**#25 で書いた JSテスト36件が無変更で通ること**が受入条件。

## 追加でやること
- [ ] **どの通知手段が使えるかを画面に出す。** iPhone で「バイブは使えません」と
      分かれば、ユーザーは音量を上げるなどの対処ができる。
      現状は黙って何もしないため気づけない
- [ ] 通知手段ごとに ON / OFF を切り替えられるようにするか判断すること
      (`localStorage` 保存。過剰なら不要と報告すること)

## 受入条件
- [ ] `class` と `extends` を使った Notifier の階層
- [ ] `CompositeNotifier` が個々の型を知らずに扱う(`instanceof` で分岐しない)
- [ ] 各クラスの Vitest テスト(`isSupported()` が false のとき何もしないこと含む)
- [ ] **既存の JSテスト36件が無変更で通る**
- [ ] iOS 相当の環境(`navigator.vibrate` 未定義)をモックしたテスト
- [ ] 使える通知手段が画面に表示される
- [ ] `npm run lint:js` が通る
- [ ] 既存の338件のPHPテストが通る
