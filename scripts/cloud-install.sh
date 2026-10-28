#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")/.."
# Each cloud task is already isolated. Reuse this checkout; do not create a worktree.
python3 scripts/init-env.py
engine=(env -u DOCKER_HOST -u DOCKER_CONTEXT -u DOCKER_TLS -u DOCKER_TLS_VERIFY -u DOCKER_CERT_PATH docker --host=unix:///var/run/docker.sock)
"${engine[@]}" info >/dev/null
build_flags=()
if [[ -n ${CODEX_PROXY_CERT:-} ]]; then build_flags+=(--secret "id=proxy_ca,src=$CODEX_PROXY_CERT"); fi
"${engine[@]}" build "${build_flags[@]}" -t istasyon-php -f docker/Dockerfile .
composer_flags=()
if [[ -f /etc/codex/network-policy.json ]]; then
 composer_flags+=(-v /etc/ssl/certs/ca-certificates.crt:/run/ca.pem:ro -e COMPOSER_CAFILE=/run/ca.pem)
fi
"${engine[@]}" run --rm --user 1000:1000 -v "$PWD:/app" "${composer_flags[@]}" -e COMPOSER_HOME=/app/storage/framework/cache/composer istasyon-php composer install --no-interaction --prefer-dist
npm ci
npm run build
# Never rotate an existing application key: encrypted data needs the original key.
if python3 -c 'from pathlib import Path; import sys; sys.exit(0 if any(l.startswith("APP_KEY=") and l.split("=",1)[1].strip().strip("\"\x27") for l in Path(".env").read_text().splitlines()) else 1)'; then
 echo 'Mevcut uygulama anahtarı korundu.'
else
 "${engine[@]}" compose run --rm --no-deps app php artisan key:generate
fi
"${engine[@]}" compose up -d --wait db
"${engine[@]}" compose run --rm app php artisan migrate --seed --force
"${engine[@]}" compose run --rm app php artisan test --compact
