FROM node:22-bookworm-slim AS frontend
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci
COPY resources ./resources
COPY vite.config.js ./
COPY public ./public
RUN npm run build

FROM dunglas/frankenphp:1-php8.3-bookworm

RUN install-php-extensions pdo_pgsql intl gd pcntl opcache \
    && apt-get update \
    && apt-get install -y --no-install-recommends python3 python3-venv \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
WORKDIR /app

COPY composer.json composer.lock ./
RUN apt-get update \
    && apt-get install -y --no-install-recommends unzip \
    && rm -rf /var/lib/apt/lists/* \
    && composer install --no-dev --no-interaction --no-progress --prefer-dist --no-scripts

COPY . .
COPY --from=frontend /app/public/build ./public/build
RUN composer dump-autoload --no-dev --optimize --no-scripts \
    && php artisan package:discover --ansi \
    && python3 -m venv /opt/spot-venv \
    && /opt/spot-venv/bin/pip install --no-cache-dir -r requirements.txt \
    && chown -R www-data:www-data storage bootstrap/cache \
    && mkdir -p /data /config \
    && chown -R www-data:www-data /data /config \
    && ln -sfn /opt/spot-venv/bin/python /usr/local/bin/spot-python

ENV APP_ENV=production \
    APP_DEBUG=false \
    PYTHON_BINARY=/opt/spot-venv/bin/python

COPY docker/Caddyfile /etc/frankenphp/Caddyfile

USER www-data
EXPOSE 80
