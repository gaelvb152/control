#!/usr/bin/env bash
# bin/smoke-test.sh — verificación rápida DESPUÉS de desplegar. No modifica datos ni inicia sesión.
#
#   bin/smoke-test.sh https://staging.tudominio.com
#   SMOKE_SKIP_APACHE=1 bin/smoke-test.sh http://127.0.0.1:8080   (servidor php -S: ignora .htaccess)
#
# Sale con código 1 si algo falla; el workflow de despliegue usa ese código para marcar el release como fallido.
set -uo pipefail

BASE="${1:-}"
if [ -z "$BASE" ]; then echo "Uso: $0 <url-base>" >&2; exit 2; fi
BASE="${BASE%/}"
fail=0

code() { curl -s -o /dev/null -w '%{http_code}' --max-time 20 "$@"; }
body() { curl -s --max-time 20 "$@"; }

check_code() { # descripcion, esperado(s) separados por |, url
  local got; got=$(code "$3")
  if [[ "|$2|" == *"|$got|"* ]]; then echo "✓ $1 ($got)"; else echo "✗ $1: esperaba $2 y llegó $got"; fail=1; fi
}

echo "Smoke test de $BASE"

# 1. La página principal carga (hasta 5 intentos: justo después de un despliegue el servidor puede tardar en calentar)
html=""
for attempt in 1 2 3 4 5; do
  html=$(body "$BASE/")
  if grep -q "Control de bandas" <<<"$html"; then break; fi
  [ "$attempt" -lt 5 ] && sleep 3
done
if grep -q "Control de bandas" <<<"$html"; then echo "✓ La página principal carga"; else echo "✗ La página principal no contiene 'Control de bandas'"; fail=1; fi

# 2. La API de sesión responde JSON y sin sesión dice success:false
json=$(body "$BASE/check_session.php")
if grep -q '"success":false' <<<"$json"; then echo "✓ check_session.php responde sin sesión"; else echo "✗ check_session.php inesperado: $json"; fail=1; fi

# 3. La base de datos responde: el login con datos vacíos llega a 'Faltan datos' (consulta no ejecutada) o 429/200 JSON
lg=$(body -X POST -H 'Content-Type: application/json' -d '{"num_empleado":"","password":""}' "$BASE/login.php")
if grep -q 'Faltan datos' <<<"$lg"; then echo "✓ login.php responde"; else echo "✗ login.php inesperado: $lg"; fail=1; fi

# 4. Los endpoints protegidos exigen sesión
check_code "api_inventario.php exige sesión" "401" "$BASE/api_inventario.php"
check_code "api_viajes.php exige sesión" "401" "$BASE/api_viajes.php"

# 5. Reglas de .htaccess (solo Apache)
if [ "${SMOKE_SKIP_APACHE:-0}" != "1" ]; then
  for f in db.php lib.php config.php config.local.php auth_check.php session_config.php; do
    check_code "$f no es accesible desde el navegador" "403|404" "$BASE/$f"
  done
  check_code "uploads_nodrizas/ no es accesible" "403|404" "$BASE/uploads_nodrizas/"
  case "$BASE" in
    https://*)
      loc=$(curl -s -o /dev/null -w '%{http_code} %{redirect_url}' --max-time 20 "http://${BASE#https://}/")
      if [[ "$loc" == 301* && "$loc" == *https://* ]]; then echo "✓ HTTP redirige a HTTPS"; else echo "✗ HTTP no redirige a HTTPS: $loc"; fail=1; fi ;;
  esac
fi

if [ "$fail" -eq 0 ]; then echo "SMOKE TEST OK"; else echo "SMOKE TEST FALLÓ"; fi
exit "$fail"
