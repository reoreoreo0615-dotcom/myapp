# myapp

個人開発用の Laravel アプリケーション。Docker で開発環境を構築しています。

## 技術スタック

| 種別 | 内容 |
| --- | --- |
| フレームワーク | Laravel 13 (PHP 8.3) |
| Webサーバー | nginx |
| データベース | MySQL 8.0 |
| DB管理 | phpMyAdmin |
| フロント | Node.js 20 / Vite |

## 構成

```
myapp/
├── app/              … Laravel アプリ本体
├── db/               … MySQL データ(git管理外)
├── docker/
│   ├── nginx/        … nginx 設定
│   └── php/          … PHP(Dockerfile)
├── phpmyadmin/
└── docker-compose.yml
```

## 起動方法

```bash
# コンテナ起動
docker compose up -d

# 初回のみ:依存インストール & マイグレーション
docker compose exec php composer install
docker compose exec php php artisan migrate
```

## アクセス先

| URL | 内容 |
| --- | --- |
| http://localhost | アプリ本体 |
| http://localhost:4040 | phpMyAdmin |

- MySQL 接続情報: host=`db` / db=`myapp` / user=`root` / pass=`root`(ホストからは `localhost:3306`)

## よく使うコマンド

`Makefile` にショートカットを用意しています。

```bash
make up        # 起動
make down      # 停止
make php       # PHPコンテナに入る
make artisan c=migrate   # 任意のartisanコマンド (例: make artisan c="make:model Post -m")
make fresh     # DBを作り直してマイグレーション
make logs      # ログ表示
```

## セットアップ(新しい環境でクローンした場合)

```bash
git clone <このリポジトリ>
cd myapp
docker compose up -d --build
docker compose exec php composer install
cp app/.env.example app/.env
docker compose exec php php artisan key:generate
docker compose exec php php artisan migrate
```
