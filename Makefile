.PHONY: up down restart build php db logs ps artisan composer npm fresh migrate seed tinker clear lint lint-test test-js lint-js lint-js-fix

# コンテナ起動 / 停止
up:
	docker compose up -d

down:
	docker compose down

restart:
	docker compose restart

build:
	docker compose up -d --build

ps:
	docker compose ps

logs:
	docker compose logs -f

# コンテナに入る
php:
	docker compose exec php bash

db:
	docker compose exec db bash

# artisan / composer / npm ショートカット
# 例: make artisan c="make:model Post -m"
artisan:
	docker compose exec php php artisan $(c)

composer:
	docker compose exec php composer $(c)

npm:
	docker compose exec php npm $(c)

# DB系
fresh:
	docker compose exec php php artisan migrate:fresh

migrate:
	docker compose exec php php artisan migrate

seed:
	docker compose exec php php artisan db:seed

tinker:
	docker compose exec php php artisan tinker

# キャッシュ全消し
clear:
	docker compose exec php php artisan optimize:clear

# コード整形(Laravel Pint)
lint:
	docker compose exec php ./vendor/bin/pint

# 整形が必要かチェックのみ(修正はしない。CIで使う)
lint-test:
	docker compose exec php ./vendor/bin/pint --test

# JSテスト(Vitest)
test-js:
	docker compose exec php npm run test:js

# JS/Vue の Lint(ESLint + Prettier のチェックのみ。修正はしない)
lint-js:
	docker compose exec php npm run lint:js

# JS/Vue の Lint を自動修正
lint-js-fix:
	docker compose exec php npm run lint:js:fix
