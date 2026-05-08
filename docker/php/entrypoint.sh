#!/usr/bin/env sh
set -e

if [ ! -f .env ] && [ -f .env.example ]; then
    cp .env.example .env
fi

if [ -n "${APP_KEY:-}" ]; then
    php artisan config:cache --no-interaction || true
fi

if [ "${ENSURE_S3_BUCKET:-false}" = "true" ] && [ "${FILESYSTEM_DISK:-}" = "s3" ]; then
    attempts=0

    until php artisan storage:ensure-s3-bucket --no-interaction; do
        attempts=$((attempts + 1))

        if [ "$attempts" -ge 10 ]; then
            exit 1
        fi

        sleep 2
    done
fi

if [ "${RUN_MIGRATIONS:-false}" = "true" ]; then
    php artisan migrate --force --no-interaction
fi

exec "$@"
