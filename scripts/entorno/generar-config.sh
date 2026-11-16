#!/usr/bin/env bash
# Genera public_html/config.local.php para el entorno de liberación a partir de
# los secretos de GitHub. Tiene el mismo formato que config.example.php y lo lee
# config.php. Este archivo NUNCA se sube a Git.
#
# Uso: generar-config.sh <carpeta_destino> <entorno: staging|production|local>
# Variables: DB_HOST DB_NAME DB_USER DB_PASS [DB_PORT]
#            [APP_DB_HOST] [APP_DB_PORT]: si la app ve la base con otro nombre de
#            host que el pipeline (p. ej. "bd" dentro de Docker).

source "$(dirname "$0")/../lib/comun.sh"
requiere_cmd php

DESTINO="${1:?Indica la carpeta destino (p. ej. build/paquete)}"
ENTORNO="${2:?Indica el entorno: staging, production o local}"

case "$ENTORNO" in
  staging|production|local) ;;
  *) morir "Entorno no válido: $ENTORNO" ;;
esac

requiere_vars DB_HOST DB_NAME DB_USER DB_PASS
[ -d "$DESTINO" ] || morir "No existe la carpeta $DESTINO"

export CFG_ENTORNO="$ENTORNO"
ARCHIVO="$DESTINO/config.local.php"

# var_export escapa los valores: una comilla en la contraseña no rompe el archivo.
php -r '
$cfg = [
    "host"    => getenv("APP_DB_HOST") ?: getenv("DB_HOST"),
    "port"    => (string)(getenv("APP_DB_PORT") ?: getenv("DB_PORT") ?: ""),
    "name"    => getenv("DB_NAME"),
    "user"    => getenv("DB_USER"),
    "pass"    => getenv("DB_PASS"),
    "entorno" => getenv("CFG_ENTORNO"),
];
$php  = "<?php\n\n";
$php .= "// config.local.php — generado por el pipeline el " . date("Y-m-d H:i") . ".\n";
$php .= "// No editar en el servidor ni subir a Git.\n\n";
$php .= "return " . var_export($cfg, true) . ";\n";
file_put_contents($argv[1], $php);
' "$ARCHIVO"

chmod 640 "$ARCHIVO"
php -l "$ARCHIVO" >/dev/null || morir "config.local.php generado con errores"
ok "config.local.php generado para $ENTORNO"
