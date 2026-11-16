# Control de Bandas

Aplicación web de TransMex Nogales para asignar viajes a los operadores, controlar el
inventario de bandas de las nodrizas y registrar la firma de conformidad desde el celular.
PHP 8.2 + MariaDB, publicada en Hostinger: https://midnightblue-skunk-575305.hostingersite.com

## Estructura

```
public_html/                 Lo que se publica en el servidor
  index.php                  Interfaz (aplicación de una sola página)
  login.php, logout.php, check_session.php
  api_*.php                  Endpoints JSON: viajes, inventario, usuarios, reportes,
                             estadísticas, notificaciones y cuenta
  foto.php                   Fotos y firmas, solo para quien tiene permiso
  config.php, db.php         Conexión (lee config.local.php o variables CB_DB_*)
  health.php                 Estado de la app y la base (pipeline y monitoreo)
  .htaccess                  HTTPS, archivos internos bloqueados, sin listado de carpetas
  uploads_nodrizas/          Fotos de los operadores (no se versiona ni se despliega)
database/migrations/         0001_baseline.sql y los cambios siguientes, numerados
bin/                         migrate.php, create-admin.php, run-api-tests.sh,
                             check-secrets.sh, setup-hooks.sh, smoke-test.sh
tests/                       103 pruebas automáticas de API (PHPUnit)
.github/workflows/
  ci.yml                     Revisión de código, pruebas y prueba en Docker
  cd.yml                     Staging automático, producción con aprobación
  rollback.yml               Regresar un entorno a una versión anterior
  monitoreo.yml              Revisa producción cada 30 min; las caídas quedan como issues
  respaldo-diario.yml        Respaldo cifrado diario y métricas de uso
scripts/
  entorno/                   generar-config.sh, verificar-entorno.sh, preparar-staging.sh,
                             anonimizar.sql, docker-compose.liberacion.yml, Dockerfile.liberacion
  pruebas/                   humo.sh, pruebas-liberacion.sh, rutas-protegidas.txt
  despliegue/                empaquetar.sh, respaldar-bd.sh, restaurar-bd.sh,
                             subir-ftps.sh, desplegar.sh, rollback.sh
  monitoreo/                 metricas-uso.sh, metricas-uso.sql
docs/                        Documentos de la Fase 1 y la Fase 2, diagramas
```

## Desarrollo local

```bash
make up          # app en http://localhost:8080 y phpMyAdmin en :8081
make migrate     # aplica las migraciones
make admin NUM=9001 NOMBRE="Tu Nombre" CLAVE='clave-larga-123'
make hooks       # activa los hooks de commits y de secretos
make test        # pruebas de API (CB_DB_* apuntando a una base *_test)
```

## Entorno de liberación (igual al hosting)

```bash
make liberacion            # arma el paquete y lo levanta con Apache en http://localhost:8082
make liberacion-pruebas    # prueba de humo y pruebas de liberación
make liberacion-down
```

## Configuración en GitHub (una sola vez)

1. **Settings → Environments**: crear `staging` y `production`.
2. `production`: **Required reviewers**, **Prevent self-review** y en *Deployment branches
   and tags* solo etiquetas `v*.*.*`. `staging`: ramas `develop` y `release/*`.
3. En **cada** entorno, los mismos nombres con sus propios valores:

   | Tipo | Nombre | Valor |
   |---|---|---|
   | Secret | `DB_HOST` | host de MySQL remoto que muestra hPanel |
   | Secret | `DB_NAME` | base del entorno |
   | Secret | `DB_USER` | usuario de la base |
   | Secret | `DB_PASS` | contraseña |
   | Secret | `FTP_HOST` | servidor FTP del sitio (hPanel → Archivos → Cuentas FTP) |
   | Secret | `FTP_USER` / `FTP_PASS` | cuenta FTP del sitio |
   | Secret | `BACKUP_PASSPHRASE` | frase larga para cifrar los respaldos |
   | Variable | `APP_URL` | dirección del sitio, sin `/` al final |
   | Variable | `FTP_DIR` | `/public_html` (o la ruta que muestre la cuenta FTP) |
   | Variable | `MIGRACIONES_BASE` | `0001` |

4. **Settings → Branches**: en `main` y `develop` exigir los checks
   *1. Revisión de código* y *2. Pruebas automáticas*.

## Configuración en Hostinger (hPanel)

- **Bases de datos → MySQL remoto**: permitir la conexión remota (el pipeline respalda y migra).
- **Sitios**: un segundo sitio o subdominio para staging, con su propia base.
- **Configuración de PHP**: PHP 8.2 y `display_errors` apagado.

## Uso diario

| Acción | Resultado |
|---|---|
| Pull request a `develop` | Corre el CI; no se puede fusionar si falla |
| Fusionar en `develop` o `release/*` | Se publica en staging |
| `git tag v2.0.1 && git push origin v2.0.1` | Espera aprobación y publica en producción |
| Actions → Rollback → Run workflow | Regresa a la versión indicada |

## Notas

- `config.local.php` lo genera el pipeline en cada despliegue con los secretos del entorno;
  nunca se sube a Git y `.htaccess` lo bloquea.
- `version.txt` lo escribe el pipeline y se consulta en `health.php` (el `.htaccess` bloquea los `.txt`).
- El despliegue nunca sube ni borra `uploads_nodrizas/`.
- Ver `CONTRIBUTING.md` para ramas, commits y revisiones, y `SECURITY.md` para reportar problemas.
