#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")/.."
engine=(env -u DOCKER_HOST -u DOCKER_CONTEXT -u DOCKER_TLS -u DOCKER_TLS_VERIFY -u DOCKER_CERT_PATH docker --host=unix:///var/run/docker.sock)
"${engine[@]}" info >/dev/null
"${engine[@]}" compose up -d --wait db app
"${engine[@]}" compose exec -T app php artisan migrate --seed --force
"${engine[@]}" compose exec -T app php artisan migrate:status
"${engine[@]}" compose exec -T app php -r '$h = file_get_contents("http://127.0.0.1:8000/login"); if(!str_contains($h,"Güvenli giriş"))exit(1); echo "Giriş ekranı doğrulandı.\n";'
