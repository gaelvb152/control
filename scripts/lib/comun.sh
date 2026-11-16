#!/usr/bin/env bash
# Funciones compartidas por todos los scripts de Control de Bandas.
# Uso: source "$(dirname "$0")/../lib/comun.sh"

set -euo pipefail

RAIZ="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
export RAIZ

# La aplicación y bin/migrate.php usan CB_DB_*; los scripts usan DB_*.
# Se aceptan ambos nombres para no repetir los secretos.
for _v in HOST PORT NAME USER PASS; do
  _db="DB_$_v"; _cb="CB_DB_$_v"
  if [ -z "${!_db:-}" ] && [ -n "${!_cb:-}" ]; then export "$_db=${!_cb}"; fi
  if [ -z "${!_cb:-}" ] && [ -n "${!_db:-}" ]; then export "$_cb=${!_db}"; fi
done
unset _v _db _cb

info()  { printf '\033[0;34m[INFO]\033[0m %s\n' "$*"; }
ok()    { printf '\033[0;32m[ OK ]\033[0m %s\n' "$*"; }
aviso() { printf '\033[0;33m[AVISO]\033[0m %s\n' "$*" >&2; }
error() { printf '\033[0;31m[ERROR]\033[0m %s\n' "$*" >&2; }
morir() { error "$*"; exit 1; }

# Verifica que existan las variables indicadas (sin mostrar su valor).
requiere_vars() {
  local faltan=0
  for v in "$@"; do
    if [ -z "${!v:-}" ]; then
      error "Falta la variable de entorno $v"
      faltan=1
    fi
  done
  [ "$faltan" -eq 0 ] || exit 1
}

# Verifica que existan los comandos indicados.
requiere_cmd() {
  for c in "$@"; do
    command -v "$c" >/dev/null 2>&1 || morir "Falta el comando '$c'"
  done
}

# Archivo temporal de opciones para mysql/mysqldump: así la contraseña
# no aparece en la lista de procesos ni en los registros.
crear_mycnf() {
  requiere_vars DB_HOST DB_NAME DB_USER DB_PASS
  MYCNF="$(mktemp)"
  chmod 600 "$MYCNF"
  cat > "$MYCNF" <<EOF
[client]
host=${DB_HOST}
port=${DB_PORT:-3306}
user=${DB_USER}
password=${DB_PASS}
default-character-set=utf8mb4
EOF
  trap 'rm -f "$MYCNF"' EXIT
  export MYCNF
}

# Cliente mysql: usa mariadb si existe, si no mysql.
cliente_sql() {
  if command -v mariadb >/dev/null 2>&1; then echo mariadb; else echo mysql; fi
}
cliente_dump() {
  if command -v mariadb-dump >/dev/null 2>&1; then echo mariadb-dump; else echo mysqldump; fi
}
