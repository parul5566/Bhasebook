#!/usr/bin/env bash
set -e

cd /workspace

# Install dependencies (skipped when already present)
if [ ! -d vendor ]; then
  composer install --no-interaction --prefer-dist --no-progress
fi
if [ ! -d node_modules ]; then
  npm ci --no-progress || npm install --no-progress
fi

# Build frontend assets
npm run build

# Link public storage
php artisan storage:link || true

# Migrate database
php artisan migrate --force

# Optimise
php artisan config:cache || true
php artisan route:cache || true
php artisan view:cache || true
