#!/usr/bin/env bash
# Prepara la base de datos de staging con una copia de producción SIN datos
# personales, para que las pruebas manuales usen datos realistas.
#
# Uso: preparar-staging.sh <respaldo_produccion.sql.gz>
# Variables: DB_* de STAGING (nunca las de producción)
#
# El respaldo se obtiene del pipeline (artefacto "respaldo-production-...").

source "$(dirname "$0")/../lib/comun.sh"
requiere_cmd gunzip "$(cliente_sql)"

RESPALDO="${1:?Indica el respaldo de producción (.sql.gz)}"
[ -f "$RESPALDO" ] || morir "No existe $RESPALDO"
requiere_vars DB_NAME

# Protección: nunca correr contra producción.
case "${DB_NAME,,}" in
  *stag*|*prueba*|*test*) ;;
  *) morir "La base '$DB_NAME' no parece de staging. Se cancela por seguridad." ;;
esac

crear_mycnf
SQL="$(cliente_sql)"

info "Importando copia de producción en $DB_NAME..."
gunzip -c "$RESPALDO" | "$SQL" --defaults-extra-file="$MYCNF" "$DB_NAME"

info "Quitando datos personales..."
# --force: si alguna tabla no existe en tu esquema, sigue con las demás.
"$SQL" --defaults-extra-file="$MYCNF" --force "$DB_NAME" < "$(dirname "$0")/anonimizar.sql"

info "Aplicando migraciones pendientes..."
php "$RAIZ/bin/migrate.php"

ok "Staging listo con datos anonimizados"
