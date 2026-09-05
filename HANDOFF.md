# 引き継ぎドキュメント(myapp)

> このファイルは別セッション/別担当が作業を続けるための引き継ぎ資料です。
> 最終更新: 2026-09-05

---

## 1. このプロジェクトは何か

**筋トレ管理アプリ「Overload」** — 個人開発の Web アプリケーション。

既存のトレーニング記録アプリ(Strong / Hevy / FitNote 等)はほぼすべて「記録帳」で止まり、
次に何をすべきかを言ってくれない。Overload は筋肥大の唯一の原則である**漸進的過負荷**を
アプリ側が計算し、**「今日は何kgを何回やればいいか」を先に提示する**ことを差別化軸に置く。

| 項目 | 内容 |
| --- | --- |
| 差別化軸 | ① 漸進的過負荷ナビ ② ジムでの入力速度(1セット=タップ1回) |
| ローカルパス | `/Applications/MAMP/htdocs/laravel/myapp` |
| GitHub | https://github.com/reoreoreo0615-dotcom/myapp (Private) |
| Project ボード | https://github.com/users/reoreoreo0615-dotcom/projects/2 |
| **設計仕様書(必読)** | https://claude.ai/code/artifact/960e32f1-ad73-400d-9f21-eddde8965ca0 |
| 仕様書のソース | `.claude/docs/overload-spec.html`(git管理下。編集したら再公開する) |

> **作業を引き継ぐ人は、まず上の設計仕様書を読むこと。**
> DB設計・コアロジック・画面モック・開発体制がすべて図とモックで説明されている。

---

## 2. 技術スタック

| 種別 | 内容 |
| --- | --- |
| フレームワーク | Laravel 13.30.1 (PHP 8.3) |
| Webサーバー | nginx |
| データベース | MySQL 8.0 |
| DB管理 | phpMyAdmin |
| フロント | Node.js 20 / Vite |
| 環境 | Docker Compose |

---

## 3. ディレクトリ構成

```
myapp/
├── .claude/          … Claude 用の規約・ドキュメント・作業記録
│   ├── CLAUDE.md     … 開発規約の本体(ルートの CLAUDE.md から読み込まれる)
│   ├── docs/         … 設計ドキュメント(overload-spec.html)
│   ├── issues/       … 各 Issue の本文 MD
│   └── worklog/      … 作業記録 MD(issue-<番号>_<YYYY-MM-DD>.md)
├── CLAUDE.md         … 自動読み込みの入口(@.claude/CLAUDE.md)
├── app/              … Laravel アプリ本体(ここが Laravel のルート)
├── db/               … MySQL データ(git管理外)
├── docker/
│   ├── nginx/        … nginx 設定(default.conf)
│   └── php/          … PHP(Dockerfile: PHP8.3-fpm + Node20)
├── phpmyadmin/
├── docker-compose.yml
├── Makefile          … よく使うコマンドのショートカット
├── README.md
└── HANDOFF.md        … このファイル
```

> 注意: git リポジトリは **myapp/ 直下**(docker設定 + app を丸ごと管理)。
> Laravel 本体は `app/` サブディレクトリにある。

---

## 4. 起動方法(別環境でクローンした場合)

```bash
git clone https://github.com/reoreoreo0615-dotcom/myapp.git
cd myapp
docker compose up -d --build
docker compose exec php composer install
cp app/.env.example app/.env
docker compose exec php php artisan key:generate
docker compose exec php php artisan migrate
```

## 4b. 既にこのマシンで構築済みの場合(通常の再開)

```bash
cd /Applications/MAMP/htdocs/laravel/myapp
make up          # または docker compose up -d
```

### アクセス先

| URL | 内容 |
| --- | --- |
| http://localhost | アプリ本体 |
| http://localhost:4040 | phpMyAdmin |

- MySQL 接続: host=`db` / db=`myapp` / user=`root` / pass=`root`(ホストからは `localhost:3306`)
- ※ pass は**ローカル開発専用**。本番では必ず変更すること。

### よく使うコマンド(Makefile)

```bash
make up        # 起動
make down      # 停止
make php       # PHPコンテナに入る
make artisan c="make:model Post -m"   # 任意のartisan
make fresh     # DB作り直し+マイグレーション
make logs      # ログ
```

---

## 5. これまでに完了した作業

- [x] Docker 環境構築(PHP8.3 / MySQL8 / nginx / phpMyAdmin)
- [x] Laravel 13 新規インストール
- [x] MySQL 接続設定 + マイグレーション(users/cache/jobs テーブル)
- [x] 日本語ローカライズ(`APP_LOCALE=ja` / `APP_TIMEZONE=Asia/Tokyo`)
- [x] README・Makefile・.gitignore 整備
- [x] GitHub リポジトリ作成 + 初回 push(main ブランチ)
- [x] GitHub Project「個人開発」作成
- [x] Issue #1(環境構築)をクローズ
- [x] **アプリの題材決定(筋トレ管理アプリ Overload)**
- [x] **技術選定の確定(Inertia + Vue 3 / 公式スターターキット)**
- [x] **DB設計・コアロジック・画面設計の確定**
- [x] **設計仕様書の作成(`.claude/docs/overload-spec.html` / Artifact 公開済み)**
- [x] **Issue #2〜#4 の具体化、#7〜#12 の新規作成**

### 確定した技術選定

| 項目 | 選択 | 理由 |
| --- | --- | --- |
| フロント | **Inertia + Vue 3** | フロント技術の市場価値を取る判断。Livewire より学習量は増えるので機能スコープを絞って相殺する |
| 認証 | **Laravel 公式スターターキット** | 認証の再発明に時間を使わない |
| CSS | Tailwind CSS v4 | 既に `app/package.json` に導入済み |
| テスト | PHPUnit(Pest 併用は実装時に判断) | |

> 注意: 現状の `app/` は**素の Laravel 13 スケルトン**で、Inertia / Vue は未導入。
> スターターキットは `laravel new` 時に選ぶものなので、**既存プロジェクトへの後付け手順の調査が Issue #2 の最初のタスク**。

### 現在の Issue 状況

| # | タイトル | ラベル | 状態 | 段階 |
|---|---------|-------|------|------|
| 1 | 開発環境の構築(Docker + Laravel 13) | setup | ✅ Closed | — |
| 3 | DB設計・マイグレーション作成(Overload 6テーブル) | feature | 🔲 Open | **基盤・最優先** |
| 2 | 認証機能の実装(スターターキット + Inertia/Vue3 基盤) | feature | 🔲 Open | 基盤 |
| 6 | コード整形(Laravel Pint)導入 | infra | 🔲 Open | 基盤 |
| 5 | CI設定(GitHub Actions) | infra | 🔲 Open | 基盤 |
| 7 | 種目マスタのシーダー作成(主要30種目) | feature | 🔲 Open | 中核 |
| 8 | ProgressionService — 漸進的過負荷ロジック + 推定1RM | feature | 🔲 Open | **中核** |
| 9 | ルーティン(メニュー)管理機能 | feature | 🔲 Open | 機能 |
| 10 | ワークアウト記録画面(最重要) | feature | 🔲 Open | 機能 |
| 11 | 種目別履歴・推定1RM推移グラフ | feature | 🔲 Open | 機能 |
| 4 | ダッシュボード・共通レイアウト作成 | feature | 🔲 Open | 機能 |
| 12 | サブエージェント構成の整備(.claude/agents/) | infra | 🔲 Open | 体制 |

**着手順の根拠**
- #3(スキーマ)が固まらないと他が全部書き直しになるので最優先。
- #6 Pint と #5 CI を機能実装より前に入れるのは、後から一括整形すると差分が巨大になりレビュー不能になるため。
- #8 ProgressionService はアプリの価値そのもの。画面(#10)より先にロジックとテストを固める。

---

## 6. 次にやること(TODO)

> 最終更新: 2026-09-05(セッション1終了時点)

### 完了済み

| # | 内容 | 状態 |
|---|---|---|
| #1 | 開発環境の構築 | ✅ |
| #12 | サブエージェント構成(`.claude/agents/` 3体) | ✅ |
| #3 | DB設計・マイグレーション(6テーブル) | ✅ |
| #7 | 種目マスタのシーダー(31種目) | ✅ |
| #8 | ProgressionService + テスト35件 | ✅ |
| #2 | 認証 + Inertia/Vue3 基盤(Breeze vue) | ✅ |

**現在 http://localhost/ でログイン画面が表示され、認証が動作する。**
ログイン: `admin@example.com` / `(パスワードは各自で設定)`(id=7)

テストは58件全パス(MySQL の `myapp_testing` で実行)。

### 進行中(次セッションで最初に確認すること)

**Issue #13「管理者権限の基盤 + ユーザー管理」を Sonnet エージェントに委譲済み。**
セッション終了時点で実行中だった可能性がある。

次セッションの最初にやること:
1. `git status` で未コミットの変更を確認する
2. 変更があれば Issue #13 の実装結果として**レビューする**
   (エージェントの報告は残っていないので、コード自体を読んで検証すること)
3. 問題なければコミット → Issue #13 に作業記録を投稿 → クローズ

### Issue #13 のレビュー時に必ず確認すること

**このIssueの本丸はデータ漏洩バグの修正。**

```sql
-- 外部キーが CASCADE になったか
SELECT k.TABLE_NAME, k.COLUMN_NAME, r.DELETE_RULE
FROM information_schema.KEY_COLUMN_USAGE k
JOIN information_schema.REFERENTIAL_CONSTRAINTS r
  ON r.CONSTRAINT_NAME = k.CONSTRAINT_NAME AND r.CONSTRAINT_SCHEMA = k.CONSTRAINT_SCHEMA
WHERE k.CONSTRAINT_SCHEMA = 'myapp' AND k.TABLE_NAME = 'exercises';
-- 期待: exercises.user_id → CASCADE(修正前は SET NULL)
```

- [ ] `exercises.user_id` が CASCADE になっている
- [ ] ユーザー削除後、そのユーザーの独自種目が `user_id = NULL` で残らないテストがある
- [ ] 既定種目31件は消えないことも同時に検証されている
- [ ] `ProfileController@destroy`(自己削除)も `DeleteUserService` を使っている
- [ ] `migrate:rollback` が通る(FK貼り直しは rollback で失敗しやすい)
- [ ] 既存58テストが壊れていない

### 残りの Issue(着手順)

| # | 内容 | 段階 |
|---|---|---|
| #13 | 管理者権限 + ユーザー管理 + **削除バグ修正** | 進行中 |
| #14 | 種目マスタの管理画面 | #13 の後 |
| #6 | Laravel Pint 導入 | 基盤(早めに) |
| #5 | CI(GitHub Actions) | 基盤(早めに) |
| #9 | ルーティン(メニュー)管理 | 機能 |
| #10 | ワークアウト記録画面(最重要) | 機能 |
| #11 | 種目別履歴・1RM推移グラフ | 機能 |
| #4 | ダッシュボード・共通レイアウト | 機能 |

**#6 Pint と #5 CI は機能実装より前に入れること。**
後から一括整形すると差分が巨大になりレビュー不能になる。

### 既知の残課題

- **`lastWorkingSetsFor` は種目1件ごとに2クエリ。** #10 の記録画面でそのまま使うと
  N+1(10〜16クエリ)になる。`lastWorkingSetsForMany()` の新設が #10 の必須要件(Issue に追記済み)。
- **`exercises.name` に UNIQUE 制約が無い。** `unique(user_id, name)` を張っても
  MySQL は NULL を互いに異なる値として扱うため既定種目の重複は防げない。#9 で再検討。
- **`verified` ミドルウェア**が dashboard に付いているが `User` が `MustVerifyEmail` 未実装のため実質無効。
- **`laravel/sanctum`** が Breeze の依存で入ったが未使用。

### 開発体制(Opus 主導 + Sonnet 委譲)

**境界は「判断が要るか」で引く。**

| Opus が持つ | Sonnet サブエージェントに渡す |
| --- | --- |
| スキーマ設計・命名・トレードオフの決定 | マイグレーション・モデルの記述 |
| Issue 分解と受入条件の定義 | 確定した仕様のコード化 |
| レビューと統合 | テストコードの記述 |
| Issue への MD 記録とクローズ | |

定義は `.claude/agents/` に3体(`laravel-backend` / `vue-frontend` / `test-writer`、すべて model: sonnet)。

> **注意:** `.claude/agents/` はセッション開始時に読み込まれる。
> 定義直後の同一セッションでは `subagent_type` に指定できない。
> その場合は general-purpose + model:sonnet で起動し、
> プロンプト冒頭で定義ファイルを読ませることで代替できる(セッション1ではこの方法を使った)。

### 運用ルール(ユーザー指示)

- GitHub Issue の追加・更新・クローズは Claude に一任されている。
- **新規 Issue には必ず `reoreoreo0615-dotcom` をアサインし、プロジェクト「個人開発」に追加する。**
- **対応完了時は、対象 Issue のコメントに日付付きの Markdown で作業記録を投稿してからクローズする。**
  同じ内容を `.claude/worklog/issue-<番号>_<YYYY-MM-DD>.md` にも保存する。
- コミット・push は確認不要(リポジトリは Private)。
- **サブエージェント稼働中は `git add -A` を使わない。** 書きかけのファイルを巻き込む。
  セッション1で実際に事故が起きた(コミット 60d68a8)。パスを明示指定すること。

---

## 7. 引き継ぎ時の注意点・ハマりどころ

構築中に遭遇し解決した問題(再発時の対処メモ):

1. **Docker Desktop の VM ハング**
   → プロセス全終了 (`osascript -e 'quit app "Docker"'` + `pkill -f com.docker`) してから再起動。

2. **ファイル共有パス未登録エラー**(Mounts denied)
   → Docker Desktop の設定 `FilesharingDirectories` に
     `/Applications/MAMP/htdocs/laravel` を追加済み。新規プロジェクトもこの配下ならOK。

3. **ポート 3306 競合**
   → 旧 `docker-env` の `curriculum-db` が `restart: always` で自動起動し占有していた。
     旧コンテナは削除済み。再発したら `docker ps` で確認。

4. **`gh` CLI のインストールが遅い**
   → このmacOS(Monterey/x86_64)は Homebrew のビルド済みバイナリが無く go をソースビルドして激遅。
     GitHub Releases から**ビルド済みバイナリを直接DL**して `/usr/local/bin/gh` に配置した(2.100.0)。

5. **gh 認証**
   → キーチェーンのトークン抜き出しは分類器にブロックされる(正しい保護)。
     `gh auth login --web` の device flow でユーザーがブラウザ認証する方式で解決済み。
     現在 `repo` / `project` / `read:org` スコープで認証済み。

---

## 8. 参考: 旧プロジェクト

- `/Applications/MAMP/htdocs/laravel/docker-env` に旧 Q&Aサイト(Laravel 6)がある。
  **これは使用しない**方針(ユーザー確認済み)。この myapp が新規開発の本体。
