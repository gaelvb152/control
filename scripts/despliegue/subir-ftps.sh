#!/usr/bin/env bash
# Publica build/paquete en Hostinger por FTPS (FTP cifrado).
# - Solo sube archivos que cambiaron (compara tamaño y fecha).
# - Borra del servidor lo que ya no existe en el paquete, EXCEPTO la carpeta
#   uploads_nodrizas/ (fotos de los operadores), que nunca se toca.
# - version.txt se sube al final: si algo falla a la mitad, health.php sigue
#   reportando la versión anterior y la prueba de humo lo detecta.
#
# Uso: subir-ftps.sh
# Variables: FTP_HOST FTP_USER FTP_PASS FTP_DIR [FTP_VERIFY_CERT=yes]

source "$(dirname "$0")/../lib/comun.sh"
requiere_cmd lftp
requiere_vars FTP_HOST FTP_USER FTP_PASS FTP_DIR

PAQUETE="$RAIZ/build/paquete"
[ -f "$PAQUETE/version.txt" ] || morir "No hay paquete. Ejecuta primero empaquetar.sh"
[ -f "$PAQUETE/config.local.php" ] || morir "Falta config.local.php. Ejecuta generar-config.sh"

info "Subiendo $(cat "$PAQUETE/version.txt") a $FTP_HOST:$FTP_DIR..."
INICIO=$(date +%s)

lftp <<EOF
set ftp:ssl-force true
set ftp:ssl-protect-data true
set ssl:verify-certificate ${FTP_VERIFY_CERT:-yes}
set net:max-retries 3
set net:reconnect-interval-base 5
set net:timeout 30
open -u "$FTP_USER","$FTP_PASS" "$FTP_HOST"
mirror --reverse --delete --no-perms --parallel=4 \
  --exclude-glob uploads_nodrizas/ --exclude-glob fotos/ --exclude-glob firmas/ --exclude-glob uploads/ \
  --exclude-glob .well-known/ --exclude-glob error_log \
  --exclude-glob version.txt \
  "$PAQUETE/" "$FTP_DIR/"
put "$PAQUETE/version.txt" -o "$FTP_DIR/version.txt"
bye
EOF

ok "Archivos publicados en $(( $(date +%s) - INICIO )) s"
