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

.PHONY: help up down reset migrate admin hooks lint test ci
