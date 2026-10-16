# Cómo contribuir

Resumen operativo. El detalle y las razones están en `docs/Fase1-Control-de-Bandas.md`, sección 4.

## Antes de empezar

```bash
bin/setup-hooks.sh     # activa la validación de commits y la búsqueda de secretos
composer install
```

## Ramas

| Rama | Uso |
|:--|:--|
| `main` | Producción. Solo recibe versiones (`release/*` y `hotfix/*`). Protegida. |
| `develop` | Integración; se despliega sola a staging. Protegida. |
| `feature/<issue>-<descripcion>` | Funcionalidad nueva (nace de `develop`) |
| `fix/<issue>-<descripcion>` | Corrección no urgente (nace de `develop`) |
| `release/vX.Y.Z` | Estabilizar una versión (nace de `develop`) |
| `hotfix/<descripcion>` | Corrección urgente en producción (nace de `main`) |

Nombres en minúsculas, con guiones y sin acentos.

## Commits (Conventional Commits)

`tipo(alcance): descripción en minúsculas, sin punto final` (máx. 72 caracteres).
Tipos: `feat` `fix` `docs` `style` `refactor` `perf` `test` `build` `ci` `chore`. Un `!` o `BREAKING CHANGE:` indica cambio incompatible.

```text
feat(viajes): permite cancelar una solicitud pendiente
fix(login): corrige el bloqueo que seguía activo tras un acierto
```

## Pull requests

1. Parte de `develop` y mantén tu rama al día: `git fetch && git rebase origin/develop` (solo en ramas propias).
2. **Escribe o actualiza la prueba** que cubre tu cambio y corre `bin/run-api-tests.sh`.
3. Abre el PR hacia `develop` con la plantilla; el título sigue Conventional Commits.
4. Espera el CI en verde y la revisión. Fusiona con *Squash and merge*.

Reglas que se revisan siempre: **sin secretos**, migraciones solo nuevas y compatibles hacia atrás, inventario siempre con transacción y bitácora, mensajes de error sin detalles internos.

## Base de datos

Todo cambio de esquema es un archivo nuevo `database/migrations/NNNN_descripcion.sql`. **Nunca se edita una migración ya fusionada.** Se prueba con `php bin/migrate.php`.

## Versiones

SemVer: `MAJOR` = cambio incompatible, `MINOR` = funcionalidad compatible, `PATCH` = corrección. Cada PR agrega su línea a *Sin publicar* en `CHANGELOG.md`. Publicar = lista de verificación de release (plantilla de issue) → etiqueta `vX.Y.Z` sobre `main`.

## Urgencias

Defecto crítico en producción: rama `hotfix/*` desde `main`, **primero la prueba que lo reproduce**, PR a `main`, etiqueta de PATCH y PR de vuelta a `develop`. Para volver a una versión anterior: Actions → *Deploy a producción* → *Run workflow* → `tag`.
