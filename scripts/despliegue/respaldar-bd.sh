#!/usr/bin/env bash
# Respalda la base de datos antes de migrar. Si existe BACKUP_PASSPHRASE,
# el respaldo se cifra (AES-256) antes de salir del runner.
#
# Uso: respaldar-bd.sh <entorno> <version>
# Resultado: build/respaldos/<entorno>-<fecha>-<version>.sql.gz[.gpg]

source "$(dirname "$0")/../lib/comun.sh"
requiere_cmd gzip "$(cliente_dump)"

ENTORNO="${1:?Indica el entorno}"
VERSION="${2:-sin-version}"
DIR="$RAIZ/build/respaldos"
mkdir -p "$DIR"

crear_mycnf
FECHA="$(TZ=America/Hermosillo date +%Y%m%d-%H%M)"
ARCHIVO="$DIR/${ENTORNO}-${FECHA}-${VERSION}.sql.gz"

info "Respaldando $DB_NAME..."
# --single-transaction: copia consistente sin bloquear a los usuarios (InnoDB).
"$(cliente_dump)" --defaults-extra-file="$MYCNF" \
  --single-transaction --routines --triggers --no-tablespaces \
  --default-character-set=utf8mb4 "$DB_NAME" | gzip -9 > "$ARCHIVO"

# Un respaldo vacío o truncado no sirve: se valida antes de continuar.
gzip -t "$ARCHIVO" || morir "El respaldo está dañado"
zcat "$ARCHIVO" | tail -1 | grep -q 'Dump completed' || morir "El respaldo quedó incompleto"
TABLAS=$(zcat "$ARCHIVO" | grep -c '^CREATE TABLE' || true)
[ "$TABLAS" -gt 0 ] || aviso "El respaldo no contiene tablas (¿base nueva?)"

if [ -n "${BACKUP_PASSPHRASE:-}" ]; then
  requiere_cmd gpg
  gpg --batch --yes --pinentry-mode loopback --passphrase "$BACKUP_PASSPHRASE" \
      --symmetric --cipher-algo AES256 -o "$ARCHIVO.gpg" "$ARCHIVO"
  rm -f "$ARCHIVO"
  ARCHIVO="$ARCHIVO.gpg"
fi

ok "Respaldo: $(basename "$ARCHIVO") ($TABLAS tablas, $(du -h "$ARCHIVO" | cut -f1))"
echo "archivo=$ARCHIVO" >> "${GITHUB_OUTPUT:-/dev/null}"
