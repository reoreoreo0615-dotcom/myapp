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

### 状態: 全 Issue 完了(29/29)

| 指標 | 値 |
|---|---|
| PHPテスト | **373件** |
| JSテスト | **70件** |
| 未完了 Issue | **0** |
| CI | グリーン |

**ログイン**: http://localhost/ — `admin@example.com` / `(パスワードは各自で設定)`(管理者)
スマホからは `http://<このマシンのLAN IP>`(同じWi-Fi内、PC起動中のみ)

### 動く機能

```
ダッシュボード  総挙上重量 / 連続記録 / 記録更新 / 直近PR / 体重 /
                停滞している種目 / 部位バランス(push:pull) / 次にやること
記録            メニュー選択 → 目標提示 → タップ1回で記録 → インターバルタイマー
                → 終了。過去日での記録、終了後の修正も可能
履歴            推定1RM推移グラフ(自重種目はレップ数軸)/ 全セット一覧 /
                自己ベスト / 体重比 / 体重タブ
メニュー        作成・編集・並び替え・独自種目追加
管理            ユーザー管理 / 種目マスタ管理(管理者のみ)
プロフィール    アカウント編集 / CSVエクスポート
```

### **学習項目とコードの対応**

| 学習項目 | 対応する実装 |
|---|---|
| PHP: クラス | Service / Repository / Policy / FormRequest を多数自作 |
| PHP: 継承 | 上記 + `RestNotifier` 階層 |
| PHP: **ポリモーフィズム** | **#27** `ProgressionStrategy` インターフェース + 実装3クラス。分岐は Factory 1箇所 |
| PHP: セッション管理 / XSS / CSRF | **#29** `.claude/docs/security.md`(640行)。すべて実検証で裏取り |
| JS: **OOP(クラス・継承・ポリモーフィズム)** | **#28** `RestNotifier` を基底に4クラスを `extends`。`CompositeNotifier` は型を知らない |

**AIツールの申告**: ChatGPT ではなく **Claude Code** を使用。
コミットに `Co-Authored-By: Claude` が入っており GitHub で確認できる。
設計・レビュー・判断は自分が持ち、実装をサブエージェントに委譲する体制。

**提出前に決める必要があること**
- **リポジトリは現在 Private。** 評価者を collaborator に追加するか、Public にするか未決定。
  Public にする場合はコミット履歴に機密情報が混ざっていないかの確認が必要
- スプレッドシートに貼る学習記録の作成(未着手)

### 次の候補(Issue 未起票)

| 候補 | 内容 |
|---|---|
| **PWA / オフライン対応** | ジムは電波が悪い。ローカル運用でも効果が大きい |
| **RPE をナビに反映** | #26 で記録できるようにしただけ。前回 RPE 7 なら2段階上げる等 |
| ESLint 警告29件の解消 | `vue/require-default-prop` / `vue/attributes-order` |
| ゴミ箱画面 | 現状「元に戻す」はフラッシュメッセージ内のみ |
| 停滞種目の件数上限 | ダッシュボードで全件返しており種目数が多いと冗長 |
| 公開サーバーへのデプロイ | 現状ローカルのみ。外出先から使えない |

### 既知の残課題

- **RPE欄を開いた直後、`NumberStepper` の min(6)により「6」が仮表示される**が
  実際の値は null。選択済みに見える可能性がありUIの改善余地あり
- `workouts.sets.restore` の `withTrashed()` が両パラメータに効く
  (コントローラの authorize と workout_id 一致チェックで実害は抑えている)
- 375px の実ブラウザ目視確認は多くの画面で未実施
- **iOS Safari では `navigator.vibrate` が未実装のためタイマーのバイブが鳴らない**
  (フォールバックとして超過時に文字色が warn 色に変わる)
- `concurrently@10.0.5` 等が Node 22+ を要求し `npm install` で EBADENGINE 警告

### この3セッションで学んだ運用上の教訓

1. **`git add -A` はサブエージェント稼働中に使わない。** 書きかけを巻き込む(実際に事故)
2. **開発DB `myapp` に `migrate:fresh` を実行しない。** ただし `migrate`(前進)は必ず適用する。
   規約が厳しすぎて `migrate` まで控えられ、テーブルが無く画面が落ちた(実際に事故)
3. **`DB::table()` は Eloquent と違い論理削除を自動除外しない。**
   SoftDeletes 導入時は生クエリを全部洗い出す
4. **エージェントは指示に忠実すぎることがある。** 規約に矛盾があると都合の良い方を選ぶ
5. **仕様が雑だとエージェントが指摘してくる。** 「30種目」と書いて31種目列挙していた、
   「3週間」が頻度で意味が変わる、など。判断を委ねて根拠を報告させると質が上がる

### 運用ルール

- Issue の追加・更新・クローズは一任されている
- **新規 Issue には `reoreoreo0615-dotcom` をアサインし、プロジェクト「個人開発」に追加する**
- **完了時は Issue に日付付き Markdown で作業記録を投稿してからクローズ。**
  同じ内容を `.claude/worklog/issue-<番号>_<YYYY-MM-DD>.md` にも保存
- コミット・push は確認不要
- サブエージェント稼働中は `git add -A` を使わない
- 開発DB `myapp` に `migrate:fresh` を実行しない(`migrate` は必ず適用する)

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
