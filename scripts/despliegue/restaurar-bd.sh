#!/usr/bin/env bash
# Restaura un respaldo de la base de datos (parte del rollback).
#
# Uso: restaurar-bd.sh <archivo.sql.gz | archivo.sql.gz.gpg>
# Variables: DB_*  y BACKUP_PASSPHRASE si el respaldo está cifrado.

source "$(dirname "$0")/../lib/comun.sh"
requiere_cmd gunzip "$(cliente_sql)"

ARCHIVO="${1:?Indica el respaldo a restaurar}"
[ -f "$ARCHIVO" ] || morir "No existe $ARCHIVO"

if [ "${CONFIRMAR:-}" != "si" ]; then
  morir "Esto reemplaza la base $DB_NAME. Ejecuta con CONFIRMAR=si para continuar."
fi

crear_mycnf

if [[ "$ARCHIVO" == *.gpg ]]; then
  requiere_vars BACKUP_PASSPHRASE
  requiere_cmd gpg
  LECTOR=(gpg --batch --quiet --pinentry-mode loopback --passphrase "$BACKUP_PASSPHRASE" -d "$ARCHIVO")
else
  LECTOR=(cat "$ARCHIVO")
fi

info "Restaurando $(basename "$ARCHIVO") en $DB_NAME..."
INICIO=$(date +%s)
"${LECTOR[@]}" | gunzip -c | "$(cliente_sql)" --defaults-extra-file="$MYCNF" "$DB_NAME"
ok "Base restaurada en $(( $(date +%s) - INICIO )) s"
