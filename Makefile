.PHONY: help up down restart build logs shell artisan migrate seed fresh test cache-clear db-shell redis-cli install

# Detect docker-compose command (prefer docker-compose if available, otherwise use docker compose)
DOCKER_COMPOSE := $(shell command -v docker-compose 2> /dev/null)
ifndef DOCKER_COMPOSE
	DOCKER_COMPOSE := docker compose
endif

# Check for SSL certificates
check-certs:
	@if [ ! -f ./docker/timescaledb/ssl/server.crt ] || [ ! -f ./docker/timescaledb/ssl/server.key ]; then \
		echo "⚠️  SSL certificates not found!"; \
		echo "   Generating certificates now..."; \
		echo ""; \
		./docker/timescaledb/scripts/generate-ssl-certs.sh; \
		echo ""; \
	fi

# Default target
help:
	@echo "Multi-Tenant Reporting Platform - Development Commands"
	@echo ""
	@echo "Docker Management:"
	@echo "  make up              - Start all services"
	@echo "  make down            - Stop all services"
	@echo "  make restart         - Restart all services"
	@echo "  make build           - Rebuild containers"
	@echo "  make logs            - View all logs"
	@echo "  make logs-app        - View app logs"
	@echo "  make logs-queue      - View queue worker logs"
	@echo "  make logs-scheduler  - View scheduler logs"
	@echo ""
	@echo "Application:"
	@echo "  make install         - Initial setup (composer, npm, key, migrate)"
	@echo "  make shell           - Access app container shell"
	@echo "  make artisan CMD=... - Run artisan command"
	@echo "  make migrate         - Run database migrations"
	@echo "  make seed            - Seed database"
	@echo "  make fresh           - Fresh database with seed"
	@echo "  make test            - Run tests"
	@echo "  make cache-clear     - Clear all caches"
	@echo ""
	@echo "Database:"
	@echo "  make db-shell        - Access PostgreSQL shell"
	@echo "  make db-backup       - Backup database"
	@echo "  make db-restore      - Restore database from backup.sql"
	@echo "  make db-verify-ssl   - Verify SSL encryption is enabled"
	@echo ""
	@echo "Security:"
	@echo "  make generate-certs  - Generate SSL certificates for PostgreSQL"
	@echo "  make verify-encryption - Verify database encryption settings"
	@echo ""
	@echo "Redis:"
	@echo "  make redis-cli       - Access Redis CLI"
	@echo "  make redis-flush     - Flush Redis cache"
	@echo ""
	@echo "Frontend:"
	@echo "  make frontend-install - Install frontend dependencies"
	@echo "  make frontend-build   - Build frontend for production"
	@echo ""
	@echo "Cleanup:"
	@echo "  make clean           - Stop and remove all containers/volumes"
	@echo "  make clean-cache     - Remove all cache files"

# Docker Management
up: check-certs
	$(DOCKER_COMPOSE) up -d

down:
	$(DOCKER_COMPOSE) down

restart:
	$(DOCKER_COMPOSE) restart

build: check-certs
	$(DOCKER_COMPOSE) up -d --build

logs:
	$(DOCKER_COMPOSE) logs -f

logs-app:
	$(DOCKER_COMPOSE) logs -f app

logs-queue:
	$(DOCKER_COMPOSE) logs -f queue

logs-scheduler:
	$(DOCKER_COMPOSE) logs -f scheduler

logs-nginx:
	$(DOCKER_COMPOSE) logs -f nginx

# Initial Setup
install: up
	@echo "Installing Composer dependencies..."
	$(DOCKER_COMPOSE) exec app composer install
	@echo "Copying .env file..."
	@if [ ! -f .env ]; then cp .env.example .env; fi
	@echo "Generating application key..."
	$(DOCKER_COMPOSE) exec app php artisan key:generate
	@echo "Running migrations..."
	$(DOCKER_COMPOSE) exec app php artisan migrate
	@echo "Installing frontend dependencies..."
	cd frontend && npm install
	@echo ""
	@echo "✅ Installation complete!"
	@echo "Frontend: http://localhost:5173"
	@echo "API: http://localhost/api"
	@echo "MailHog: http://localhost:8025"
	@echo "pgAdmin: http://localhost:5050"

# Application Commands
shell:
	$(DOCKER_COMPOSE) exec app bash

artisan:
	$(DOCKER_COMPOSE) exec app php artisan $(CMD)

migrate:
	$(DOCKER_COMPOSE) exec app php artisan migrate

migrate-fresh:
	$(DOCKER_COMPOSE) exec app php artisan migrate:fresh

seed:
	$(DOCKER_COMPOSE) exec app php artisan db:seed

fresh:
	$(DOCKER_COMPOSE) exec app php artisan migrate:fresh --seed

test:
	$(DOCKER_COMPOSE) exec app php artisan test

cache-clear:
	$(DOCKER_COMPOSE) exec app php artisan cache:clear
	$(DOCKER_COMPOSE) exec app php artisan config:clear
	$(DOCKER_COMPOSE) exec app php artisan route:clear
	$(DOCKER_COMPOSE) exec app php artisan view:clear

optimize:
	$(DOCKER_COMPOSE) exec app php artisan config:cache
	$(DOCKER_COMPOSE) exec app php artisan route:cache
	$(DOCKER_COMPOSE) exec app php artisan view:cache

# Database Commands
db-shell:
	$(DOCKER_COMPOSE) exec timescaledb psql -U reporting_user -d reporting

db-backup:
	@echo "Creating database backup..."
	$(DOCKER_COMPOSE) exec timescaledb pg_dump -U reporting_user reporting > backup-$(shell date +%Y%m%d-%H%M%S).sql
	@echo "✅ Backup created: backup-$(shell date +%Y%m%d-%H%M%S).sql"

db-restore:
	@echo "Restoring database from backup.sql..."
	$(DOCKER_COMPOSE) exec -T timescaledb psql -U reporting_user reporting < backup.sql
	@echo "✅ Database restored"

# Redis Commands
redis-cli:
	$(DOCKER_COMPOSE) exec redis redis-cli

redis-flush:
	$(DOCKER_COMPOSE) exec redis redis-cli FLUSHALL

# Frontend Commands
frontend-install:
	cd frontend && npm install

frontend-build:
	cd frontend && npm run build

frontend-dev:
	cd frontend && npm run dev

# Queue & Scheduler
queue-restart:
	$(DOCKER_COMPOSE) restart queue

queue-work:
	$(DOCKER_COMPOSE) exec app php artisan queue:work

scheduler-run:
	$(DOCKER_COMPOSE) exec app php artisan schedule:run

# Cleanup
clean:
	@echo "⚠️  This will remove all containers and volumes. Are you sure? [y/N] " && read ans && [ $${ans:-N} = y ]
	$(DOCKER_COMPOSE) down -v
	@echo "✅ Cleanup complete"

clean-cache:
	rm -rf storage/framework/cache/*
	rm -rf storage/framework/sessions/*
	rm -rf storage/framework/views/*
	rm -rf bootstrap/cache/*.php

# Development Helpers
tinker:
	$(DOCKER_COMPOSE) exec app php artisan tinker

routes:
	$(DOCKER_COMPOSE) exec app php artisan route:list

models:
	$(DOCKER_COMPOSE) exec app php artisan model:show

status:
	@echo "=== Docker Services Status ==="
	@$(DOCKER_COMPOSE) ps
	@echo ""
	@echo "=== Application URLs ==="
	@echo "Frontend:        http://localhost:5173"
	@echo "API:             http://localhost/api"
	@echo "MailHog:         http://localhost:8025"
	@echo "pgAdmin:         http://localhost:5050"
	@echo "Redis Commander: http://localhost:8081"

# MCP Server
mcp-logs:
	$(DOCKER_COMPOSE) logs -f mcp

mcp-restart:
	$(DOCKER_COMPOSE) restart mcp

# Security & Encryption
generate-certs:
	@echo "Generating SSL certificates for PostgreSQL..."
	./docker/timescaledb/scripts/generate-ssl-certs.sh
	@echo "✅ Certificates generated. Restart TimescaleDB to apply:"
	@echo "   make restart"

verify-encryption:
	@echo "=== Verifying Database Encryption ==="
	@echo ""
	@echo "Checking SSL status..."
	@$(DOCKER_COMPOSE) exec timescaledb psql -U reporting_user -d reporting -c "SHOW ssl;"
	@echo ""
	@echo "Checking password encryption..."
	@$(DOCKER_COMPOSE) exec timescaledb psql -U reporting_user -d reporting -c "SHOW password_encryption;"
	@echo ""
	@echo "Checking active SSL connections..."
	@$(DOCKER_COMPOSE) exec timescaledb psql -U reporting_user -d reporting -c "SELECT datname, usename, ssl, version, cipher FROM pg_stat_ssl JOIN pg_stat_activity ON pg_stat_ssl.pid = pg_stat_activity.pid WHERE datname = 'reporting';"
	@echo ""
	@echo "✅ Encryption verification complete"

db-verify-ssl:
	@echo "Verifying SSL connection from application..."
	@$(DOCKER_COMPOSE) exec app php -r "$$pdo = new PDO('pgsql:host=timescaledb;dbname=reporting', 'reporting_user', 'reporting_password'); $$result = $$pdo->query('SELECT version FROM pg_stat_ssl WHERE pid = pg_backend_pid()')->fetch(); echo 'SSL Version: ' . ($$result['version'] ?? 'Not connected via SSL') . PHP_EOL;"
