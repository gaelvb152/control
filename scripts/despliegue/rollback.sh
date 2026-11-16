#!/usr/bin/env bash
# Regresa el sitio a una versión anterior (etiqueta de Git).
# Opcionalmente restaura también la base de datos desde un respaldo.
#
# Uso: rollback.sh <staging|production> <etiqueta> [respaldo.sql.gz(.gpg)]
# Variables: DB_* FTP_* APP_URL [BACKUP_PASSPHRASE]

source "$(dirname "$0")/../lib/comun.sh"
requiere_cmd git

ENTORNO="${1:?Indica el entorno}"
ETIQUETA="${2:?Indica la etiqueta a la que se regresa (p. ej. v1.3.2)}"
RESPALDO="${3:-}"

git -C "$RAIZ" rev-parse -q --verify "refs/tags/$ETIQUETA" >/dev/null \
  || morir "No existe la etiqueta $ETIQUETA"

INICIO=$(date +%s)
info "=== Rollback de $ENTORNO a $ETIQUETA ==="

# Se arma el paquete desde la etiqueta en una copia aparte, sin tocar
# la rama actual.
TMP="$(mktemp -d)"
trap 'git -C "$RAIZ" worktree remove --force "$TMP" >/dev/null 2>&1 || true' EXIT
git -C "$RAIZ" worktree add --detach "$TMP" "$ETIQUETA" >/dev/null

# Se usan las reglas de empaquetado actuales sobre el código de la etiqueta.
ORIGEN_PUBLIC="$TMP/public_html" VERSION="$ETIQUETA" "$RAIZ/scripts/despliegue/empaquetar.sh"

if [ -n "$RESPALDO" ]; then
  CONFIRMAR=si "$RAIZ/scripts/despliegue/restaurar-bd.sh" "$RESPALDO"
fi

"$RAIZ/scripts/entorno/generar-config.sh" "$RAIZ/build/paquete" "$ENTORNO"
"$RAIZ/scripts/despliegue/subir-ftps.sh"
"$RAIZ/scripts/pruebas/humo.sh" "$APP_URL" "$ETIQUETA"

ok "=== Rollback terminado en $(( $(date +%s) - INICIO )) s ==="
