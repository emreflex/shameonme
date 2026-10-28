# syntax=docker/dockerfile:1
FROM composer:2 AS composer
FROM php:8.4-cli-bookworm
RUN --mount=type=secret,id=proxy_ca \
    if [ -f /run/secrets/proxy_ca ]; then cp /run/secrets/proxy_ca /usr/local/share/ca-certificates/session.crt; update-ca-certificates; fi && \
    apt-get update && apt-get install -y --no-install-recommends git unzip libzip-dev libpng-dev libxml2-dev libonig-dev libicu-dev libsqlite3-dev && \
    docker-php-ext-install -j4 pdo_mysql pdo_sqlite mbstring zip gd intl bcmath && \
    rm -f /usr/local/share/ca-certificates/session.crt && update-ca-certificates && rm -rf /var/lib/apt/lists/*
COPY --from=composer /usr/bin/composer /usr/local/bin/composer
WORKDIR /app
CMD ["php", "artisan", "serve", "--host=0.0.0.0"]
