# Registro de cambios

Formato basado en [Keep a Changelog](https://keepachangelog.com/es-ES/1.1.0/) y versionado [SemVer](https://semver.org/lang/es/).

## [Sin publicar]

## [2.0.0] - 2026-11-15

### Added
- Rol **administrativo** (estadísticas, reportes, calendario e inventario), módulo de estadísticas (`api_estadisticas.php`) y cuenta de usuario (`api_cuenta.php`).
- Carga de asignaciones con alta automática de nodrizas nuevas y de operadores (`lib_asignaciones.php`).
- Cambio de contraseña obligatorio para operadores (`debe_cambiar_pwd`).
- Bloqueo de inicio de sesión por intentos fallidos guardado en la base (`login_intentos`).
- Encabezados de seguridad comunes (`security_headers.php`) y caducidad de sesión por inactividad (8 h) y total (14 h).
- `health.php` para el pipeline y el monitoreo.
- Pipeline de liberación continua: `.github/workflows/` (CI, CD, rollback, monitoreo, respaldo diario) y `scripts/` (entorno, pruebas, despliegue, monitoreo).
- Entorno de liberación con Docker (`make liberacion`).

### Changed
- `0001_baseline.sql` ahora es la estructura real de producción.
- Las pruebas de API se ajustaron a la v2 (encabezado anti-CSRF en el login, alta automática de nodrizas).
- `config.local.php` lo genera el pipeline en cada despliegue.


## [1.0.0] - 2026-10-16

### Added
- Repositorio con flujo `main` / `develop`, ramas de trabajo, hooks de git y plantillas de PR e issues.
- Esquema de base de datos versionado (`database/migrations/0001_baseline.sql`) y ejecutor `bin/migrate.php`.
- `bin/create-admin.php` para crear el primer administrador.
- Suite de 103 pruebas automáticas de API (PHPUnit) y `bin/run-api-tests.sh`.
- CI (lint, escáner de secretos, PHPUnit en PHP 8.2 y 8.3) y despliegue por FTPS a staging y producción con aprobación manual.
- `bin/smoke-test.sh` para verificar un sitio recién desplegado.
- Entorno local con Docker Compose.
- Documento de la Fase 1 (herramientas, plan y casos de prueba, versiones, despliegue).

### Changed
- `db.php` lee la conexión de `config.local.php` o de variables `CB_DB_*`.
- `.htaccess` omite la redirección a HTTPS en `localhost` y bloquea `config*.php`, `.env*` y el archivo de estado del despliegue.

### Security
- **Se retiró del código la contraseña de la base de datos que estaba en texto plano en `db.php`.** Debe rotarse en hPanel: el valor anterior se considera comprometido.
- Escáner de secretos en el hook `pre-commit` y en el CI.
