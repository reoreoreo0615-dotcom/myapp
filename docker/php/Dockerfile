# 使用イメージの指定
FROM php:8.3-fpm

# composer イメージ内から特定のファイルをコピー
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# apt-getのアップデートと必要なライブラリ・PHP拡張のインストール
RUN apt-get update && apt-get install -y \
    git \
    zip unzip \
    curl \
    gnupg \
    libzip-dev \
    libonig-dev \
    && docker-php-ext-install pdo_mysql zip

# Node.js 20 のインストール
RUN curl -sL https://deb.nodesource.com/setup_20.x | bash -
RUN apt-get install -y nodejs

WORKDIR /var/www/html
