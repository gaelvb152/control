#!/usr/bin/env bash
# Imprime las métricas de uso del sistema (solo lectura).
# En GitHub Actions también las agrega al resumen de la ejecución.
#
# Uso: metricas-uso.sh
# Variables: DB_*

source "$(dirname "$0")/../lib/comun.sh"
requiere_cmd "$(cliente_sql)"
crear_mycnf

RESULTADO=$("$(cliente_sql)" --defaults-extra-file="$MYCNF" -N "$DB_NAME" < "$(dirname "$0")/metricas-uso.sql")

printf '%-48s %s\n' "Métrica" "Valor"
while IFS=$'\t' read -r metrica valor; do
  printf '%-48s %s\n' "$metrica" "$valor"
done <<< "$RESULTADO"

if [ -n "${GITHUB_STEP_SUMMARY:-}" ]; then
  {
    echo "## Métricas de uso ($(TZ=America/Hermosillo date '+%Y-%m-%d %H:%M'))"
    echo "| Métrica | Valor |"
    echo "|---|---|"
    while IFS=$'\t' read -r metrica valor; do echo "| $metrica | $valor |"; done <<< "$RESULTADO"
  } >> "$GITHUB_STEP_SUMMARY"
fi
