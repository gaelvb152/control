#!/usr/bin/env bash
# Despliegue completo en un solo comando (el pipeline ejecuta los mismos
# pasos por separado para que se vean en GitHub Actions).
#
# Uso: desplegar.sh <staging|production> <version>
# Variables: DB_* FTP_* APP_URL [BACKUP_PASSPHRASE]

source "$(dirname "$0")/../lib/comun.sh"

ENTORNO="${1:?Indica el entorno: staging o production}"
VERSION="${2:?Indica la versión (p. ej. v1.4.0)}"
S="$RAIZ/scripts"

INICIO=$(date +%s)
info "=== Desplegando $VERSION en $ENTORNO ==="

"$S/entorno/verificar-entorno.sh"
VERSION="$VERSION" "$S/despliegue/empaquetar.sh"
"$S/despliegue/respaldar-bd.sh" "$ENTORNO" "$VERSION"
[ -n "${MIGRACIONES_BASE:-}" ] && php "$RAIZ/bin/migrate.php" --mark-applied="$MIGRACIONES_BASE"
php "$RAIZ/bin/migrate.php"
"$S/entorno/generar-config.sh" "$RAIZ/build/paquete" "$ENTORNO"
"$S/despliegue/subir-ftps.sh"

if ! "$S/pruebas/humo.sh" "$APP_URL" "$VERSION"; then
  error "Falló la prueba de humo: revisa y ejecuta rollback.sh con la versión anterior"
  exit 1
fi
"$S/pruebas/pruebas-liberacion.sh" "$APP_URL" || aviso "Hay observaciones en las pruebas de liberación"

ok "=== $VERSION publicada en $ENTORNO en $(( $(date +%s) - INICIO )) s ==="
