#!/usr/bin/env bash
# Pruebas en el entorno de liberación (staging), complementan la prueba de
# humo: seguridad del servidor, control de acceso sin sesión y tiempo de
# respuesta. Solo leen; no modifican datos (no se intenta iniciar sesión
# para no llenar la tabla login_intentos).
#
# Uso: pruebas-liberacion.sh <url_base>
# Variables opcionales: LIMITE_MS (por defecto 2000), SIN_HTTPS=1

source "$(dirname "$0")/../lib/comun.sh"
set +e   # cada caso registra su resultado sin detener el script
requiere_cmd curl awk

URL="${1:?Indica la URL base}"
URL="${URL%/}"
LIMITE_MS="${LIMITE_MS:-2000}"
RUTAS="$(dirname "$0")/rutas-protegidas.txt"
fallos=0; total=0

caso() {
  total=$((total+1))
  if [ "$3" -eq 0 ]; then ok "$1 $2"; else error "$1 $2 ${4:-}"; fallos=$((fallos+1)); fi
}

# LIB-01 Encabezados de seguridad (los pone security_headers.php)
encabezados=$(curl -s -D - -o /dev/null --max-time 20 "$URL/" | tr -d '\r')
for h in X-Content-Type-Options X-Frame-Options Referrer-Policy Permissions-Policy; do
  grep -qi "^$h:" <<< "$encabezados"; caso LIB-01 "Encabezado $h presente" $?
done
if [ -z "${SIN_HTTPS:-}" ]; then
  grep -qi '^Strict-Transport-Security:' <<< "$encabezados"; caso LIB-01 "Encabezado HSTS presente" $?
fi

# LIB-02 La versión de PHP no se anuncia
! grep -qi '^X-Powered-By:' <<< "$encabezados"; caso LIB-02 "No se expone X-Powered-By" $?

# LIB-03 Cookie de sesión protegida (session_config.php la crea en check_session.php)
cookie=$(curl -s -D - -o /dev/null --max-time 20 "$URL/check_session.php" | tr -d '\r' | grep -i '^Set-Cookie:.*PHPSESSID' || true)
if [ -n "$cookie" ]; then
  grep -qi 'HttpOnly' <<< "$cookie"; caso LIB-03 "Cookie de sesión HttpOnly" $?
  grep -qi 'SameSite=Lax' <<< "$cookie"; caso LIB-03 "Cookie de sesión SameSite=Lax" $?
  if [ -z "${SIN_HTTPS:-}" ]; then grep -qi 'secure' <<< "$cookie"; caso LIB-03 "Cookie de sesión Secure" $?; fi
else
  caso LIB-03 "Se crea la cookie de sesión" 1 "(no llegó PHPSESSID)"
fi

# LIB-04 Rutas que exigen sesión: sin iniciar sesión deben responder 401 o 403
# (requireLogin / requireStaff / requireAdmin en auth_check.php). Equivale a CP-ACC-01.
if [ -f "$RUTAS" ]; then
  while IFS= read -r ruta; do
    [[ -z "$ruta" || "$ruta" == \#* ]] && continue
    c=$(curl -s -o /dev/null -w '%{http_code}' --max-time 20 "$URL/$ruta")
    if [[ "$c" =~ ^(401|403)$ ]]; then r=0; else r=1; fi; caso LIB-04 "Sin sesión se niega /$ruta" "$r" "(HTTP $c)"
  done < "$RUTAS"
fi

# LIB-05 Las fotos de las nodrizas no se pueden abrir directo (solo por foto.php)
c=$(curl -s -o /dev/null -w '%{http_code}' --max-time 20 "$URL/uploads_nodrizas/")
if [ "$c" != "200" ]; then r=0; else r=1; fi; caso LIB-05 "Sin acceso a /uploads_nodrizas/" "$r" "(HTTP $c)"
c=$(curl -s -o /dev/null -w '%{http_code}' --max-time 20 "$URL/uploads_nodrizas/00000000000000000000000000aa.jpg")
if [ "$c" = "403" ] || [ "$c" = "404" ]; then r=0; else r=1; fi; caso LIB-05 "Foto directa bloqueada" "$r" "(HTTP $c)"

# LIB-06 Tiempo de respuesta (promedio de 5 peticiones a health.php)
prom=$(for _ in 1 2 3 4 5; do
         curl -s -o /dev/null -w '%{time_total}\n' --max-time 20 "$URL/health.php"
       done | awk '{s+=$1} END {printf "%d", (s/NR)*1000}')
if [ "$prom" -le "$LIMITE_MS" ]; then r=0; else r=1; fi; caso LIB-06 "Tiempo de respuesta promedio ${prom} ms (límite ${LIMITE_MS} ms)" "$r"

# LIB-07 login.php solo acepta POST
c=$(curl -s -o /dev/null -w '%{http_code}' --max-time 20 "$URL/login.php")
if [ "$c" = "405" ]; then r=0; else r=1; fi; caso LIB-07 "login.php rechaza GET (405)" "$r" "(HTTP $c)"

echo
echo "tiempo_respuesta_ms=$prom" >> "${GITHUB_OUTPUT:-/dev/null}"
if [ "$fallos" -eq 0 ]; then
  ok "Pruebas de liberación aprobadas ($total/$total)"
else
  morir "Pruebas de liberación: $fallos de $total con observaciones"
fi
