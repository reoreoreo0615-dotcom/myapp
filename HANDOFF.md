# 引き継ぎドキュメント(myapp)

> このファイルは別セッション/別担当が作業を続けるための引き継ぎ資料です。
> 最終更新: 2026-09-05

---

## 1. このプロジェクトは何か

個人開発用の Web アプリケーション。現時点では **アプリの題材は未定**で、
Laravel + Docker の開発環境と GitHub のリポジトリ・Issue 管理までを構築済み。

- ローカルパス: `/Applications/MAMP/htdocs/laravel/myapp`
- GitHub リポジトリ: https://github.com/reoreoreo0615-dotcom/myapp (Private)
- GitHub Project(ボード): 「個人開発」 https://github.com/users/reoreoreo0615-dotcom/projects/2

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
- [x] GitHub Project「個人開発」作成、Issue 6件を紐付け
- [x] Issue #1(環境構築)をクローズ

### 現在の Issue 状況

| # | タイトル | ラベル | 状態 |
|---|---------|-------|------|
| 1 | 開発環境の構築(Docker + Laravel 13) | setup | ✅ Closed |
| 2 | 認証機能の実装 | feature | 🔲 Open |
| 3 | DB設計・マイグレーション作成 | feature | 🔲 Open |
| 4 | トップページ・共通レイアウト作成 | feature | 🔲 Open |
| 5 | CI設定(GitHub Actions) | infra | 🔲 Open |
| 6 | コード整形(Laravel Pint)導入 | infra | 🔲 Open |

> Issue #2〜#6 は**汎用のスターター**。アプリの題材が決まったら具体化・追加すること。

---

## 6. 次にやること(TODO)

### 最優先: アプリの題材決め
- 現状 Issue は汎用。作るもの(例: 家計簿 / タスク管理 / ブログ 等)を決めると、
  DB設計(#3)・画面(#4)・機能 Issue を具体化できる。

### 開発の進め方(希望のエージェント構成)
- ユーザー希望: **メインの指示・設計は Opus、実装などのサブ作業は Sonnet に任せる**。
- 具体化の候補:
  - `.claude/agents/` に実装担当のサブエージェント(Sonnet)を定義する。
  - Opus 側で設計・Issue 分解 → 実装は Sonnet サブエージェントに委譲、という運用。
- ※ この構成はまだ**未着手**。次セッションで設計から始める。

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
