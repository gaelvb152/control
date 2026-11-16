.DEFAULT_GOAL := help
COMPOSE ?= docker compose

help: ## Muestra esta ayuda
	@grep -E '^[a-zA-Z_-]+:.*?## ' $(MAKEFILE_LIST) | awk 'BEGIN {FS = ":.*?## "}; {printf "  \033[36m%-12s\033[0m %s\n", $$1, $$2}'

up: ## Levanta el entorno local (http://localhost:8080, phpMyAdmin en :8081)
	$(COMPOSE) up -d --build

down: ## Detiene el entorno local (conserva la base de datos)
	$(COMPOSE) down

reset: ## Detiene el entorno y BORRA la base de datos local
	$(COMPOSE) down -v

migrate: ## Aplica las migraciones SQL pendientes en la base local
	$(COMPOSE) exec app php /var/www/bin/migrate.php

admin: ## Crea el primer administrador local:  make admin NUM=9001 NOMBRE="Tu Nombre" CLAVE='clave-larga-123'
	$(COMPOSE) exec -e CB_ADMIN_PASSWORD='$(CLAVE)' app php /var/www/bin/create-admin.php $(NUM) "$(NOMBRE)"

hooks: ## Activa los hooks de git (formato de commits + secretos)
	bin/setup-hooks.sh

lint: ## Sintaxis PHP + búsqueda de secretos + estilo
	composer lint && bin/check-secrets.sh && composer cs

test: ## Pruebas de API (requiere CB_DB_* apuntando a una BD de pruebas)
	bin/run-api-tests.sh

ci: ## Todo lo que corre GitHub Actions, en tu máquina
	composer ci


# ---------- Liberación (Fase 2) ----------
COMPOSE_LIB = $(COMPOSE) -f scripts/entorno/docker-compose.liberacion.yml
LIB_BD      = DB_HOST=127.0.0.1 DB_PORT=3308 DB_NAME=control_bandas_liberacion DB_USER=app DB_PASS=app_local

paquete: ## Arma build/paquete igual que el pipeline
	VERSION=local-$$(git rev-parse --short HEAD 2>/dev/null || echo dev) scripts/despliegue/empaquetar.sh

liberacion: paquete ## Levanta el paquete con Apache + MariaDB en http://localhost:8082
	$(LIB_BD) APP_DB_HOST=bd APP_DB_PORT=3306 scripts/entorno/generar-config.sh build/paquete local
	$(COMPOSE_LIB) up -d --build --wait
	$(LIB_BD) CB_DB_HOST=127.0.0.1 CB_DB_PORT=3308 CB_DB_NAME=control_bandas_liberacion CB_DB_USER=app CB_DB_PASS=app_local php bin/migrate.php

liberacion-pruebas: ## Prueba de humo y de liberación contra http://localhost:8082
	SIN_HTTPS=1 scripts/pruebas/humo.sh http://localhost:8082 $$(cat build/paquete/version.txt)
	SIN_HTTPS=1 scripts/pruebas/pruebas-liberacion.sh http://localhost:8082

liberacion-down: ## Apaga y borra el entorno de liberación
	$(COMPOSE_LIB) down -v

.PHONY: help up down reset migrate admin hooks lint test ci paquete liberacion liberacion-pruebas liberacion-down
