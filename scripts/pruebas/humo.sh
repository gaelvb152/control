#!/usr/bin/env bash
# Prueba de humo: se ejecuta justo después de cada despliegue.
# Confirma que el sitio abre, que la base responde, que quedó la versión
# correcta y que los archivos internos siguen protegidos.
# Si algo falla, termina con código 1 y el pipeline hace rollback.
#
# Uso: humo.sh <url_base> [version_esperada]
#      SIN_HTTPS=1 humo.sh http://localhost:8081   (entorno local)

source "$(dirname "$0")/../lib/comun.sh"
set +e   # cada caso registra su resultado sin detener el script
requiere_cmd curl

URL="${1:?Indica la URL base, p. ej. https://midnightblue-skunk-575305.hostingersite.com}"
URL="${URL%/}"
ESPERADA="${2:-}"
fallos=0
total=0

codigo() { curl -s -o /dev/null -w '%{http_code}' --max-time 20 "$@"; }

caso() {  # caso <id> <descripcion> <resultado: 0 ok / 1 falla> [detalle]
  total=$((total+1))
  if [ "$3" -eq 0 ]; then ok "$1 $2"; else error "$1 $2 ${4:-}"; fallos=$((fallos+1)); fi
}

# HUMO-01 La aplicación (index.php) carga
html=$(curl -s --max-time 20 "$URL/")
grep -q "Control de bandas" <<< "$html"; caso HUMO-01 "Página principal carga" $?

# HUMO-02 Salud: la aplicación y la base de datos responden.
# Se reintenta porque el hosting puede tardar unos segundos en refrescar caché.
salud=""
for intento in 1 2 3 4 5; do
  salud=$(curl -s --max-time 20 "$URL/health.php?nc=$RANDOM" || true)
  grep -q '"estado":"ok"' <<< "$salud" && break
  sleep $((intento * 3))
done
grep -q '"estado":"ok"' <<< "$salud"; caso HUMO-02 "Aplicación y base de datos en línea" $? "($salud)"

# HUMO-03 Quedó publicada la versión esperada (la reporta health.php,
# porque .htaccess bloquea los .txt)
if [ -n "$ESPERADA" ]; then
  v=$(sed -n 's/.*"version":"\([^"]*\)".*/\1/p' <<< "$salud")
  if [ "$v" = "$ESPERADA" ]; then r=0; else r=1; fi; caso HUMO-03 "Versión publicada = $ESPERADA" "$r" "(se encontró '$v')"
fi

# HUMO-04 Archivos internos protegidos (no deben devolver 200)
for archivo in db.php config.php config.local.php lib.php lib_asignaciones.php auth_check.php \
               session_config.php security_headers.php version.txt .htaccess .git/HEAD; do
  c=$(codigo "$URL/$archivo")
  if [ "$c" != "200" ]; then r=0; else r=1; fi; caso HUMO-04 "Bloqueado: /$archivo" "$r" "(HTTP $c)"
done

# HUMO-05 La sesión funciona: sin iniciar sesión, check_session responde success=false
r=$(curl -s --max-time 20 "$URL/check_session.php")
grep -q '"success":false' <<< "$r"; caso HUMO-05 "check_session.php responde sin sesión" $? "($r)"

# HUMO-06 login.php llega a la validación (no truena con datos vacíos)
r=$(curl -s --max-time 20 -X POST -H 'Content-Type: application/json' -H 'X-Requested-With: fetch' -d '{"num_empleado":"","password":""}' "$URL/login.php")
grep -q 'Faltan datos' <<< "$r"; caso HUMO-06 "login.php responde" $? "($r)"

# HUMO-07 Se fuerza HTTPS
if [ -z "${SIN_HTTPS:-}" ]; then
  http_url="http://${URL#https://}"
  destino=$(curl -s -o /dev/null -w '%{redirect_url}' --max-time 20 "$http_url/")
  if [[ "$destino" == https://* ]]; then r=0; else r=1; fi; caso HUMO-07 "http:// redirige a https://" "$r" "(destino: '$destino')"
fi

echo
if [ "$fallos" -eq 0 ]; then
  ok "Prueba de humo aprobada ($total/$total)"
else
  morir "Prueba de humo fallida: $fallos de $total casos"
fi
