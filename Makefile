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
	@echo "🐳 Docker Management:"
	@echo "  make up              - Start all services"
	@echo "  make down            - Stop all services"
	@echo "  make restart         - Restart all services"
	@echo "  make build           - Rebuild containers"
	@echo "  make logs            - View all logs"
	@echo "  make logs-app        - View backend logs"
	@echo "  make logs-frontend   - View frontend logs"
	@echo "  make logs-nginx      - View nginx logs"
	@echo "  make status          - Show service status and URLs"
	@echo ""
	@echo "🚀 Backend (Laravel):"
	@echo "  make backend-shell   - Access backend container shell"
	@echo "  make artisan CMD=... - Run artisan command"
	@echo "  make migrate         - Run database migrations"
	@echo "  make seed            - Seed database"
	@echo "  make fresh           - Fresh database with seed"
	@echo "  make test            - Run tests"
	@echo "  make cache-clear     - Clear all caches"
	@echo "  make queue           - Run queue worker"
	@echo "  make schedule        - Run scheduler once"
	@echo ""
	@echo "🎨 Frontend (React):"
	@echo "  make frontend-shell  - Access frontend container shell"
	@echo "  make frontend-install - Install npm dependencies"
	@echo "  make frontend-build  - Build for production"
	@echo ""
	@echo "🗄️  Database:"
	@echo "  make db-shell        - Access PostgreSQL shell"
	@echo "  make db-backup       - Backup database"
	@echo "  make db-restore      - Restore database from backup.sql"
	@echo ""
	@echo "🔴 Redis:"
	@echo "  make redis-cli       - Access Redis CLI"
	@echo "  make redis-flush     - Flush Redis cache"
	@echo ""
	@echo "🔒 Security:"
	@echo "  make generate-certs  - Generate SSL certificates"
	@echo "  make verify-encryption - Verify database encryption"
	@echo ""
	@echo "🧹 Cleanup:"
	@echo "  make clean           - Stop and remove all containers/volumes"
	@echo "  make clean-backend   - Remove backend cache files"

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

logs-backend:
	$(DOCKER_COMPOSE) logs -f app

logs-frontend:
	$(DOCKER_COMPOSE) logs -f frontend

logs-nginx:
	$(DOCKER_COMPOSE) logs -f nginx

# Initial Setup
install: up
	@echo "📦 Installing backend dependencies..."
	@if [ -f backend/composer.json ]; then \
		$(DOCKER_COMPOSE) exec app composer install; \
	else \
		echo "⚠️  No backend/composer.json found. Run: composer create-project laravel/laravel:^12.0 backend"; \
	fi
	@echo ""
	@echo "📦 Installing frontend dependencies..."
	@if [ -f frontend/package.json ]; then \
		cd frontend && npm install; \
	else \
		echo "⚠️  No frontend/package.json found. Run: npm create vite@latest frontend -- --template react"; \
	fi
	@echo ""
	@echo "✅ Installation complete!"
	@echo "Run 'make status' to see your service URLs"

# Backend Commands
backend-shell:
	$(DOCKER_COMPOSE) exec app bash

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
frontend-shell:
	$(DOCKER_COMPOSE) exec frontend sh

frontend-install:
	cd frontend && npm install

frontend-build:
	cd frontend && npm run build

frontend-dev:
	cd frontend && npm run dev

# Queue & Scheduler (run manually when needed)
queue:
	$(DOCKER_COMPOSE) exec app php artisan queue:work

schedule:
	$(DOCKER_COMPOSE) exec app php artisan schedule:run

# Cleanup
clean:
	@echo "⚠️  This will remove all containers and volumes. Are you sure? [y/N] " && read ans && [ $${ans:-N} = y ]
	$(DOCKER_COMPOSE) down -v
	@echo "✅ Cleanup complete"

clean-backend:
	@if [ -d backend/storage ]; then \
		rm -rf backend/storage/framework/cache/*; \
		rm -rf backend/storage/framework/sessions/*; \
		rm -rf backend/storage/framework/views/*; \
		rm -rf backend/bootstrap/cache/*.php; \
		echo "✅ Backend cache cleared"; \
	else \
		echo "⚠️  Backend not installed yet"; \
	fi

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
	@echo "Note: Ports may vary if using multi-developer setup"
	@echo ""
	@echo "🎨 Frontend:        http://localhost:5173 (or check your dev ID)"
	@echo "🚀 API:             http://localhost/api (or check your dev ID)"
	@echo "📧 MailHog:         http://localhost:8025 (or check your dev ID)"
	@echo "🗄️  pgAdmin:         http://localhost:5050 (or check your dev ID)"
	@echo "🔴 Redis Commander: http://localhost:8081 (or check your dev ID)"


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
