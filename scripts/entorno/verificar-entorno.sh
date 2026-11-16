#!/usr/bin/env bash
# Verifica que el entorno de liberación esté listo ANTES de desplegar:
# variables completas, base de datos accesible con la versión y
# codificación correctas, y servidor FTPS disponible.
#
# Uso: verificar-entorno.sh
# Variables: DB_* FTP_HOST FTP_USER FTP_PASS FTP_DIR APP_URL

source "$(dirname "$0")/../lib/comun.sh"
requiere_cmd curl lftp "$(cliente_sql)"
requiere_vars DB_HOST DB_NAME DB_USER DB_PASS FTP_HOST FTP_USER FTP_PASS FTP_DIR APP_URL

fallos=0

# 1. Base de datos
crear_mycnf
SQL="$(cliente_sql)"
if VERSION_BD=$("$SQL" --defaults-extra-file="$MYCNF" -N -e "SELECT VERSION()" "$DB_NAME" 2>/dev/null); then
  ok "Conexión a la base de datos ($VERSION_BD)"
  case "$VERSION_BD" in
    *MariaDB*|8.*) ;;
    *) aviso "Versión de base de datos no esperada: $VERSION_BD" ;;
  esac
  CHARSET=$("$SQL" --defaults-extra-file="$MYCNF" -N -e \
    "SELECT DEFAULT_CHARACTER_SET_NAME FROM information_schema.SCHEMATA WHERE SCHEMA_NAME='$DB_NAME'")
  if [ "$CHARSET" = "utf8mb4" ]; then ok "Codificación utf8mb4"; else aviso "Codificación de la base: $CHARSET (se esperaba utf8mb4)"; fi
  MOTORES=$("$SQL" --defaults-extra-file="$MYCNF" -N -e \
    "SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA='$DB_NAME' AND ENGINE<>'InnoDB'")
  if [ "$MOTORES" = "0" ]; then ok "Todas las tablas usan InnoDB"; else aviso "$MOTORES tabla(s) no usan InnoDB"; fi
else
  error "No hay conexión a la base de datos (revisa MySQL remoto en hPanel)"
  fallos=$((fallos+1))
fi

# 2. Servidor FTPS (solo lista la carpeta, no cambia nada)
if lftp -c "set ftp:ssl-force true; set ftp:ssl-protect-data true; \
            set ssl:verify-certificate ${FTP_VERIFY_CERT:-yes}; set net:max-retries 2; set net:timeout 20; \
            open -u \"$FTP_USER\",\"$FTP_PASS\" \"$FTP_HOST\"; cls -1 \"$FTP_DIR\"" >/dev/null 2>&1; then
  ok "Acceso FTPS a $FTP_DIR"
else
  error "No hay acceso FTPS a $FTP_HOST:$FTP_DIR"
  fallos=$((fallos+1))
fi

# 3. Sitio y certificado
if curl -fsS -o /dev/null --max-time 20 "$APP_URL/"; then
  ok "El sitio responde con certificado válido ($APP_URL)"
else
  aviso "El sitio no respondió (normal si es el primer despliegue)"
fi

[ "$fallos" -eq 0 ] || morir "El entorno no está listo ($fallos problema(s))"
ok "Entorno listo para desplegar"
