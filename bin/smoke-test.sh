#!/usr/bin/env bash
# bin/smoke-test.sh — verificación rápida DESPUÉS de desplegar. No modifica datos ni inicia sesión.
# Desde la Fase 2 la prueba completa vive en scripts/pruebas/humo.sh (la usa el pipeline).
#
#   bin/smoke-test.sh https://midnightblue-skunk-575305.hostingersite.com
#   bin/smoke-test.sh http://localhost:8082 [version]      (entorno de liberación local)
set -euo pipefail
case "${1:-}" in http://*) export SIN_HTTPS=1 ;; esac
exec "$(dirname "$0")/../scripts/pruebas/humo.sh" "$@"
