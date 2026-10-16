#!/usr/bin/env bash
# bin/run-api-tests.sh — levanta el servidor PHP contra la BD de pruebas y corre PHPUnit.
#
# Requisitos: PHP con pdo_mysql y gd, una BD MySQL/MariaDB vacía con permisos para el usuario de pruebas
# y las variables CB_DB_* apuntando a ella. Aplica las migraciones automáticamente.
#
#   export CB_DB_HOST=127.0.0.1 CB_DB_NAME=control_bandas_test CB_DB_USER=cb_test CB_DB_PASS=cb_test_pw
#   bin/run-api-tests.sh [argumentos de phpunit, p. ej. --filter AuthTest]
#
# CUIDADO: las pruebas VACÍAN las tablas de la base indicada. Nunca apuntes a producción.
set -euo pipefail

cd "$(dirname "$0")/.."

: "${CB_DB_NAME:?Define CB_DB_NAME (base de datos de PRUEBAS)}"
: "${CB_DB_USER:?Define CB_DB_USER}"
export CB_DB_HOST="${CB_DB_HOST:-127.0.0.1}"
export CB_DB_PORT="${CB_DB_PORT:-3306}"
export CB_DB_PASS="${CB_DB_PASS:-}"
PORT="${CB_TEST_PORT:-8080}"
export CB_TEST_BASE_URL="http://127.0.0.1:${PORT}"

case "$CB_DB_NAME" in
  *test*|*ci*|*dev*) ;;
  *) echo "Por seguridad, el nombre de la BD debe contener 'test', 'ci' o 'dev' (recibido: $CB_DB_NAME)." >&2; exit 2 ;;
esac

PHPUNIT="vendor/bin/phpunit"
if [ ! -x "$PHPUNIT" ]; then PHPUNIT="$(command -v phpunit || true)"; fi
if [ -z "$PHPUNIT" ]; then echo "No se encontró PHPUnit (composer install)." >&2; exit 2; fi

php bin/migrate.php

PHP_CLI_SERVER_WORKERS=4 php -S "127.0.0.1:${PORT}" -t public_html >/tmp/cb-test-server.log 2>&1 &
SERVER_PID=$!
cleanup() { kill "$SERVER_PID" 2>/dev/null || true; }
trap cleanup EXIT

for _ in $(seq 1 30); do
  if curl -fs "http://127.0.0.1:${PORT}/check_session.php" >/dev/null 2>&1; then break; fi
  sleep 0.5
done

"$PHPUNIT" "$@"
