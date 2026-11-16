# Política de seguridad

## Cómo reportar una vulnerabilidad

**No abras un issue público.** Usa *Security → Report a vulnerability* de este repositorio en GitHub, o escribe directamente al responsable del proyecto. Incluye pasos para reproducirlo y el impacto que ves; se responde en un máximo de 3 días hábiles.

## Qué se considera sensible en este proyecto

- Contraseñas y credenciales (base de datos, FTP) y el archivo `config.local.php`.
- Fotos de nodrizas y firmas de operadores (`uploads_nodrizas/`, tabla `viajes`).
- Hashes de contraseñas y datos de empleados (tabla `usuarios`).

## Reglas

1. Ningún secreto real se sube al repositorio; `bin/check-secrets.sh` lo vigila en cada commit y en el CI.
2. Si un secreto se sube por error, **se rota de inmediato**; limpiar el historial no basta (procedimiento en `docs/`, sección 4.4).
3. Las bases de staging usan datos anonimizados; nunca copias con nombres o contraseñas reales.
4. Los defectos de seguridad se tratan como severidad *Crítica* o *Alta* (`docs/`, sección 2.5).

## Versiones con soporte

Solo la última versión publicada (`main`) recibe correcciones de seguridad.
