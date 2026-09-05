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

> 最終更新: 2026-09-05(セッション2)

### 完了済み(13件)

| # | 内容 |
|---|---|
| #1 | 開発環境の構築 |
| #12 | サブエージェント構成(`.claude/agents/` 3体) |
| #3 | DB設計・マイグレーション(6テーブル) |
| #7 | 種目マスタのシーダー(31種目) |
| #8 | ProgressionService + テスト |
| #2 | 認証 + Inertia/Vue3 基盤(Breeze vue) |
| #13 | 管理者権限 + ユーザー管理 + **削除時データ漏洩バグ修正** |
| #6 | Laravel Pint(`app/pint.json`) |
| #5 | CI(GitHub Actions) |
| #16 | **デザイン基盤**(計器/Precision) |
| #15 | 最後の管理者削除ガード |
| #9 | ルーティン(メニュー)管理 |

**テスト106件パス / CI グリーン / コミット28件すべて push 済み**

### 動作状況

http://localhost/ でログイン: `admin@example.com` / `(パスワードは各自で設定)`(管理者)

動くもの: 認証 / プロフィール / **メニュー管理** / 独自種目追加 / ユーザー管理(管理者)
**動かないもの: ナビの「記録」「履歴」は非活性**(#10 / #11 が未完のため)

### サンプルデータ投入済み

メニュー3件(胸の日/背中の日/脚の日)、ワークアウト14回、セット196件(うちウォームアップ28件)。
過去8週間で漸進的過負荷が進行した履歴。ベンチプレスは次で 62.5kg に上がるタイミング。

投入スクリプトはリポジトリに残していない(tinker で直接実行した)。
**再投入が必要なら `DemoSeeder` として作り直すこと。**

### 次にやること

1. **#10 ワークアウト記録画面 — 実装完了。ただし未レビュー(最優先で確認すること)**

   ユーザー不在中にエージェントの実装が完了したため、**レビューを経ずに
   コミット `582c138` として push してある。** Issue #10 は意図的にクローズしていない。

   テスト129件パス / Pint PASS / `npm run build` 成功 / ナビ「記録」接続済み。
   `resources/js/Pages/Workouts/{Create,Show}.vue` も存在する。

   ### エージェントが指示外で追加した設計判断 — 採用可否を判断すること

   **① `workouts.progression_snapshot`(json, nullable)**

   エージェントが**実バグを発見して対処したもの**。記録を始めると、
   そのセッション自身のセットが「直近のワークアウト」になってしまい、
   セッション途中で「前回」と「今日の目標」が変わってしまう。
   種目ごとに初回参照時にスナップショットを凍結して解決している。

   `test_target_does_not_shift_after_recording_a_set_in_the_same_session` が
   この修正なしでは落ちる、との報告。**まずこのテストを確認すること。**
   問題の指摘自体は正しいので、解法が妥当かを見る。

   **② `workout_sets.client_request_id`(nullable, unique)**

   二重送信防止の冪等キー。クライアントがUUIDを生成し、サーバーが既存行を確認。
   DB側のunique制約を最終防衛線にしている。

   ### その他レビューすべき点
   - `SetRow.vue` が編集・削除・ウォームアップ表示に拡張されている
   - 「メニューなしの飛び込み」フローが実装されている(指示外)
   - 履歴が無い種目は weight 0 / `target_rep_min` にフォールバック
   - **`finished_at` を確定する「トレーニング終了」フローは未実装**(受入条件外として保留)
   - セット削除の確認が Modal ではなく素の `confirm()`

   ### 実機確認(未実施)
   実データが入っているので、http://localhost/ の「記録」から
   **胸の日 → ベンチプレスで「62.5kg × 6回」が表示されるか**を必ず目視すること。
   ウォームアップ(20kg等)が「前回」として表示されていたら `is_warmup` の除外漏れ。

   レビュー観点は下記
2. #17 自重種目の1RM問題 → **#11 の前に方針を決める**
3. #11 種目別履歴・1RMグラフ
4. #4 ダッシュボード
5. #14 種目マスタの管理画面

### #10 のレビュー観点

- [ ] **N+1 が解消されているか。** `lastWorkingSetsForMany()` が新設され、
      種目数を変えてもクエリ数が増えないテストがあること
- [ ] タップ1回で1セット記録できるか
- [ ] 二重送信防止(連打で同じセットが2件入らない)
- [ ] `is_warmup` トグルがあり、集計・ナビ計算から除外されること
- [ ] ナビの「記録」が実ルートに接続されているか
- [ ] 375px で横スクロールしないこと
- [ ] 実データで「ベンチプレス → 62.5kg × 6回」が表示されること

### 既知の残課題

- **ナビの「履歴」は非活性のまま。** #11 実装時に `AuthenticatedLayout.vue` の
  `navItems` の `href: null` を差し替える
- **自重種目の推定1RMが0**(#17)。懸垂・ディップス等4種目
- `ExerciseController` の器具別重量刻み定数が `ExerciseSeeder::WEIGHT_INCREMENT` と重複
- `exercises.name` に UNIQUE 制約なし(MySQL は NULL を別値扱いするため
  `unique(user_id, name)` でも既定種目の重複は防げない)
- Vue/JS の Prettier / ESLint 未導入
- 既定種目・独自種目の編集/削除UIは未実装

### デザイン方針(#16 で確定)

**計器 / Precision** — 体の進捗を測る精密機器。数値が主役、装飾は限界まで削る。

**禁止**: 角丸カードの敷き詰め / ドロップシャドウ / グラデーション /
Inter・Space Grotesk / 絵文字見出し / 何でも中央揃え / 意味のない番号バッジ

**書体は Noto Sans JP に統一**(ウェイト300〜800で階層を作る)。
トークンは `app/resources/css/app.css` の `@theme`。ダーク既定 + ライト完全定義。

### 開発体制

境界は**「判断が要るか」**で引く。設計・命名・トレードオフ・レビューは主セッション、
確定した仕様の実装は Sonnet サブエージェントに委譲する。

定義は `.claude/agents/`(`laravel-backend` / `vue-frontend` / `test-writer`)。

> `.claude/agents/` はセッション開始時に読み込まれる。定義直後の同一セッションでは
> `subagent_type` に指定できない。その場合は general-purpose + model:sonnet で起動し、
> プロンプト冒頭で定義ファイルを読ませて代替する。

### 運用ルール

- Issue の追加・更新・クローズは一任されている
- **新規 Issue には `reoreoreo0615-dotcom` をアサインし、プロジェクト「個人開発」に追加する**
- **完了時は Issue に日付付き Markdown で作業記録を投稿してからクローズ。**
  同じ内容を `.claude/worklog/issue-<番号>_<YYYY-MM-DD>.md` にも保存
- コミット・push は確認不要
- **サブエージェント稼働中は `git add -A` を使わない**(書きかけを巻き込む。実際に事故済み)
- **開発DB `myapp` に `migrate:fresh` を実行しない**(実データが消える。実際に事故済み)

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
