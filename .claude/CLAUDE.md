# CLAUDE.md — Overload(筋トレ管理アプリ)

このリポジトリで作業する際の規約。**着手前に必ず読むこと。**

## 1. このプロジェクト

**Overload** — 漸進的過負荷(progressive overload)をアプリ側が計算して
「今日は何kgを何回やればいいか」を先に提示する筋トレ管理アプリ。

既存アプリ(Strong / Hevy / FitNote)はほぼすべて「記録帳」で止まり、次に何をすべきかを言わない。
そこが差別化軸。

| 差別化軸 | 内容 |
| --- | --- |
| ① 漸進的過負荷ナビ | 種目を開いた瞬間に今日の目標が数値で出ている |
| ② 入力速度 | 目標値が入力欄にプリセット済み。**1セット記録=タップ1回** |

**設計仕様書(必読)**
- 公開URL: https://claude.ai/code/artifact/960e32f1-ad73-400d-9f21-eddde8965ca0
- ソース: `.claude/docs/overload-spec.html`

DB設計・コアロジック・画面モック・開発体制がすべて図とモックで説明されている。
**仕様に関する疑問は、まずこの仕様書を参照すること。**

## 2. 技術スタック

| 種別 | 内容 |
| --- | --- |
| フレームワーク | Laravel 13.30.1 / PHP 8.3 |
| フロント | **Inertia.js + Vue 3** |
| CSS | Tailwind CSS v4 |
| 認証 | Laravel 公式スターターキット |
| DB | MySQL 8.0 |
| 環境 | Docker Compose(nginx / php-fpm / mysql / phpMyAdmin) |

> **重要:** git リポジトリは `myapp/` 直下だが、**Laravel 本体は `app/` サブディレクトリ**にある。
> artisan / composer / npm はすべて `app/` を基準に実行する。

## 3. ディレクトリ規約

```
myapp/
├── .claude/
│   ├── CLAUDE.md        … このファイル(規約)
│   ├── docs/            … 設計ドキュメント(HTML/MD)
│   ├── issues/          … 各 Issue の本文 MD(<番号>-<slug>.md)
│   └── worklog/         … 作業記録 MD(issue-<番号>_<YYYY-MM-DD>.md)
├── app/                 … Laravel 本体
├── db/                  … MySQL データ(git管理外)
├── docker/
├── docker-compose.yml
├── Makefile
└── HANDOFF.md           … 引き継ぎ資料
```

**Claude が作成する MD / HTML はすべて `.claude/` 配下に置く。**
プロジェクトルートや `app/` を散らかさないこと。

## 4. 開発ルール

### Issue 運用(Claude に一任されている)

- Issue の新規作成・更新・クローズは自分の判断で行ってよい。
- **Issue を新規作成したら必ず `reoreoreo0615-dotcom` をアサインする。**
  `gh issue create ... --assignee reoreoreo0615-dotcom`
- コミット・push は確認を取らずに実行してよい(リポジトリは Private)。
- **対応完了時は、対象 Issue のコメントに Markdown 形式で作業記録を投稿してからクローズする。**
- 同じ内容を `.claude/worklog/issue-<番号>_<YYYY-MM-DD>.md` にも保存する。

作業記録に必ず含める項目:

```markdown
## 対応日
YYYY-MM-DD

## 実装内容
(何をどう実装したかの要約)

## 変更ファイル
- `path/to/file` — 変更内容

## 設計上の判断
(なぜその選択をしたか。トレードオフがあれば明記)

## 残課題
(積み残し。無ければ「なし」)
```

投稿コマンド:
```bash
gh issue comment <番号> --body-file .claude/worklog/issue-<番号>_<YYYY-MM-DD>.md
gh issue close <番号>
```

### 開発体制(Opus 主導 + Sonnet 委譲)

**境界は「判断が要るか」で引く。**

| Opus が持つ | Sonnet サブエージェントに渡す |
| --- | --- |
| スキーマ設計・命名・トレードオフの決定 | マイグレーション・モデルの記述 |
| Issue 分解と受入条件の定義 | 確定した仕様のコード化 |
| レビューと統合 | テストコードの記述 |
| Issue への MD 記録とクローズ | |

## 4b. 機密情報をリポジトリに書かない

**公開する・しないに関わらず、以下をコミットしないこと。**
git の履歴は後から消すのが非常に面倒で、一度 push すると取り消せない。

| 書かないもの | 代わりに書くこと |
| --- | --- |
| 実在のメールアドレス | `admin@example.com` などのプレースホルダ |
| 実際のパスワード | 「各自で設定」と書くだけ。値は書かない |
| LAN の IP アドレス | `<このマシンのLAN IP>` |
| APIキー / トークン | `.env` に置く(`.gitignore` 済み) |

**ドキュメントやIssueの本文も対象。** コードだけの話ではない。
HANDOFF.md にログイン情報を書くのは便利だが、**そこが一番漏れやすい**。

2026-09-09 に実際に、メールアドレスがコミット履歴に69箇所、
パスワードとLAN IPがドキュメントに残っている状態が見つかった。

## 5. 実装上の必須ルール

### 集計は必ず `is_warmup = false` で絞る

ウォームアップセットを混ぜると、総ボリューム・自己ベスト・漸進的過負荷ナビの
計算がすべて狂う。**集計クエリを書くたびに確認すること。**

### N+1 を放置しない

記録画面は種目ごとに「前回の記録」を引くため、素直に書くと種目数だけクエリが飛ぶ。
Eager Loading + サブクエリで解決し、クエリ数を確認してから完了とする。

### 重量は decimal で扱う

`decimal(5,2)`。浮動小数の誤差を避けるため、比較・加算に float を使わない。

### モバイルファースト

ジムでスマホから片手で使うのが主用途。狭い画面(375px)を基準に設計し、
デスクトップは後から広げる。タップ領域は最低 44×44px。

### Phase 2 用の列を勝手に消さない

`exercises.movement_type` と `workout_sets.rpe` は MVP では未使用だが、
Phase 2 で使うため意図的に用意してある。「使われていないから」で削除しないこと。

## 6. よく使うコマンド

```bash
make up        # コンテナ起動
make down      # 停止
make php       # PHPコンテナに入る
make artisan c="make:model Workout -m"
make fresh     # DB作り直し + マイグレーション
make logs      # ログ

docker compose exec php php artisan test     # テスト
docker compose exec php ./vendor/bin/pint    # 整形
```

アクセス先: http://localhost(アプリ) / http://localhost:4040(phpMyAdmin)

## 7. コードスタイル

- PHP は Laravel Pint(Issue #6 で設定確定)。**コミット前に必ず実行する。**
- 設定ファイルは `app/pint.json`。プリセットは `laravel` をベースに、以下を明示的に固定している
  (いずれも `laravel` プリセットの既定動作と同じだが、将来のプリセット変更に影響されないよう
  明示的に pin している):
  - `ordered_imports`(`use` 文をアルファベット順に並べる)
  - `no_unused_imports`(未使用の `use` を削除)
  - `trailing_comma_in_multiline`(複数行の配列・引数リストの末尾にカンマ)
  - `exclude`: `vendor` / `storage` / `bootstrap/cache`
  - `declare_strict_types` は**採用していない**。全84ファイルに影響する変更になり、
    既存コードの型強制の挙動を個別に検証するコストに対してメリットが小さいと判断した。
    導入する場合は別Issueで、全ファイル適用後に全テストが通ることを確認してから行う。
  - 実行コマンド:
    ```bash
    make lint       # 整形を実行(app/pint.json を使用)
    make lint-test  # 整形が必要かチェックのみ(修正はしない。CI向け)
    ```
    内部的には `docker compose exec php ./vendor/bin/pint`
    /  `... pint --test` を呼んでいる。
- ビジネスロジックはコントローラに書かず、`app/Services/` に切り出す。
  特に `ProgressionService` は Eloquent に依存しない純粋なロジックとして書き、
  ユニットテストを厚くかけるようにする。
- Vue コンポーネントは `resources/js/Pages/`(Inertia ページ)と
  `resources/js/Components/`(再利用部品)に分ける。
  **Vue/JS の整形(Prettier / ESLint)は別Issueで扱う。ここでは対象外。**
