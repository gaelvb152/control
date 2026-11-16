#!/usr/bin/env bash
# Arma el paquete que se publica: solo public_html/, sin contraseñas, fotos,
# firmas ni archivos de desarrollo. Escribe version.txt para que la prueba
# de humo confirme qué versión quedó en línea.
#
# Uso: VERSION=v1.4.0 empaquetar.sh
# Resultado: build/paquete/  y  build/paquete.tar.gz

source "$(dirname "$0")/../lib/comun.sh"

ORIGEN="${ORIGEN_PUBLIC:-$RAIZ/public_html}"   # el rollback usa otra carpeta
SALIDA="$RAIZ/build/paquete"
VERSION="${VERSION:-$(git -C "$RAIZ" describe --tags --always 2>/dev/null || echo sin-version)}"

[ -d "$ORIGEN" ] || morir "No existe $ORIGEN"

rm -rf "$SALIDA"
mkdir -p "$SALIDA"

# Copia excluyendo lo que nunca debe publicarse.
tar -C "$ORIGEN" \
  --exclude='config.local.php' \
  --exclude='*.example.php' \
  --exclude='uploads_nodrizas' --exclude='fotos' --exclude='firmas' --exclude='uploads' \
  --exclude='htaccess' --exclude='LEEME*.txt' --exclude='version.txt' \
  --exclude='tests' --exclude='*.md' --exclude='.git*' \
  --exclude='*.log' --exclude='.DS_Store' \
  -cf - . | tar -C "$SALIDA" -xf -

# Revisión de sintaxis de todo el PHP del paquete.
errores=0
while IFS= read -r -d '' f; do
  php -l "$f" >/dev/null 2>&1 || { error "Error de sintaxis: ${f#"$SALIDA"/}"; errores=$((errores+1)); }
done < <(find "$SALIDA" -name '*.php' -print0)
[ "$errores" -eq 0 ] || morir "$errores archivo(s) con errores de sintaxis"

# Ninguna contraseña escrita en el código. Detecta las dos formas comunes:
#   $password = "secreto";      y      getenv("APP_DB_PASS") ?: "secreto";
if grep -rInE "(pass(word)?|pwd)['\"]?\s*(=|=>)\s*['\"][^'\"\$]{4,}['\"]|(PASS|pass)[^;]*\?:\s*['\"][^'\"]{4,}['\"]" \
     "$SALIDA" --include='*.php' | cut -d: -f1,2; then   # solo archivo:línea, nunca el valor
  morir "Se encontró una posible contraseña escrita en el código"
fi

echo "$VERSION" > "$SALIDA/version.txt"
tar -C "$SALIDA" -czf "$RAIZ/build/paquete.tar.gz" .

ARCHIVOS=$(find "$SALIDA" -type f | wc -l)
ok "Paquete $VERSION listo: $ARCHIVOS archivos ($(du -sh "$RAIZ/build/paquete.tar.gz" | cut -f1))"
