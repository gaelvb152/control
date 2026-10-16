#!/usr/bin/env bash
# bin/check-secrets.sh — falla si encuentra contraseñas o llaves escritas en el código.
#
# Existe porque el db.php original traía la contraseña de la base de datos en texto plano.
# Se ejecuta en el hook pre-commit y en el CI (job "lint").
#
#   bin/check-secrets.sh            revisa todo el árbol de trabajo
set -uo pipefail

cd "$(dirname "$0")/.." || exit 2
fail=0

# Archivos que revisamos (no revisamos plantillas, documentación ni pruebas, que usan datos de ejemplo)
mapfile -t files < <(find . -type f \
  \( -name '*.php' -o -name '*.sh' -o -name '*.yml' -o -name '*.yaml' -o -name '*.json' -o -name '*.sql' -o -name '*.ini' -o -name '.htaccess' \) \
  -not -path './vendor/*' -not -path './node_modules/*' -not -path './.git/*' \
  -not -path './tests/*' -not -path './docs/*' \
  -not -name 'config.example.php' -not -name 'composer.lock' -not -name 'check-secrets.sh')

report() {
  echo "✗ $1"
  # shellcheck disable=SC2001
  echo "$2" | sed 's/^/    /'
  fail=1
}

# Descarta los renglones que son comentarios (ejemplos en docblocks, // o #); deja pasar todo lo demás
no_comments() { grep -vE '^[^:]+:[0-9]+:[[:space:]]*(\*|//|#|/\*)' || true; }

# 1) $password = 'algo';  $pass = "algo";  (asignación literal)
hits=$(grep -nEH "\\\$(db_?)?(password|passwd|pass|pwd)[[:space:]]*=[[:space:]]*['\"][^'\"]+['\"]" "${files[@]}" 2>/dev/null | no_comments)
[ -n "$hits" ] && report "Contraseña escrita en una variable PHP:" "$hits"

# 2) 'password' => 'algo'  /  "pass": "algo"  (arreglos o JSON con valor literal)
hits=$(grep -nEH "['\"](db_)?(password|passwd|pass)['\"][[:space:]]*(=>|:)[[:space:]]*['\"][^'\"\$]{3,}['\"]" "${files[@]}" 2>/dev/null | no_comments)
[ -n "$hits" ] && report "Contraseña escrita en un arreglo/JSON:" "$hits"

# 3) Llaves privadas
hits=$(grep -nEH -e '-----BEGIN ([A-Z]+ )?PRIVATE KEY-----' "${files[@]}" 2>/dev/null || true)
[ -n "$hits" ] && report "Llave privada en el repositorio:" "$hits"

# 4) Archivos que jamás deben estar versionados
if git rev-parse --git-dir >/dev/null 2>&1; then
  hits=$(git ls-files | grep -E '(^|/)(config\.local\.php|\.env|\.env\.[^e].*|.*\.pem|.*\.key)$' || true)
  [ -n "$hits" ] && report "Archivos de secretos versionados:" "$hits"
fi

if [ "$fail" -eq 0 ]; then
  echo "✓ Sin secretos en el código (${#files[@]} archivos revisados)."
fi
exit "$fail"
