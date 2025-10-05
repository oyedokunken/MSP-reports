.PHONY: help up down restart build logs shell artisan migrate seed fresh test cache-clear db-shell redis-cli install

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
up:
	docker-compose up -d

down:
	docker-compose down

restart:
	docker-compose restart

build:
	docker-compose up -d --build

logs:
	docker-compose logs -f

logs-app:
	docker-compose logs -f app

logs-queue:
	docker-compose logs -f queue

logs-scheduler:
	docker-compose logs -f scheduler

logs-nginx:
	docker-compose logs -f nginx

# Initial Setup
install: up
	@echo "Installing Composer dependencies..."
	docker-compose exec app composer install
	@echo "Copying .env file..."
	@if [ ! -f .env ]; then cp .env.example .env; fi
	@echo "Generating application key..."
	docker-compose exec app php artisan key:generate
	@echo "Running migrations..."
	docker-compose exec app php artisan migrate
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
	docker-compose exec app bash

artisan:
	docker-compose exec app php artisan $(CMD)

migrate:
	docker-compose exec app php artisan migrate

migrate-fresh:
	docker-compose exec app php artisan migrate:fresh

seed:
	docker-compose exec app php artisan db:seed

fresh:
	docker-compose exec app php artisan migrate:fresh --seed

test:
	docker-compose exec app php artisan test

cache-clear:
	docker-compose exec app php artisan cache:clear
	docker-compose exec app php artisan config:clear
	docker-compose exec app php artisan route:clear
	docker-compose exec app php artisan view:clear

optimize:
	docker-compose exec app php artisan config:cache
	docker-compose exec app php artisan route:cache
	docker-compose exec app php artisan view:cache

# Database Commands
db-shell:
	docker-compose exec timescaledb psql -U reporting_user -d reporting

db-backup:
	@echo "Creating database backup..."
	docker-compose exec timescaledb pg_dump -U reporting_user reporting > backup-$(shell date +%Y%m%d-%H%M%S).sql
	@echo "✅ Backup created: backup-$(shell date +%Y%m%d-%H%M%S).sql"

db-restore:
	@echo "Restoring database from backup.sql..."
	docker-compose exec -T timescaledb psql -U reporting_user reporting < backup.sql
	@echo "✅ Database restored"

# Redis Commands
redis-cli:
	docker-compose exec redis redis-cli

redis-flush:
	docker-compose exec redis redis-cli FLUSHALL

# Frontend Commands
frontend-install:
	cd frontend && npm install

frontend-build:
	cd frontend && npm run build

frontend-dev:
	cd frontend && npm run dev

# Queue & Scheduler
queue-restart:
	docker-compose restart queue

queue-work:
	docker-compose exec app php artisan queue:work

scheduler-run:
	docker-compose exec app php artisan schedule:run

# Cleanup
clean:
	@echo "⚠️  This will remove all containers and volumes. Are you sure? [y/N] " && read ans && [ $${ans:-N} = y ]
	docker-compose down -v
	@echo "✅ Cleanup complete"

clean-cache:
	rm -rf storage/framework/cache/*
	rm -rf storage/framework/sessions/*
	rm -rf storage/framework/views/*
	rm -rf bootstrap/cache/*.php

# Development Helpers
tinker:
	docker-compose exec app php artisan tinker

routes:
	docker-compose exec app php artisan route:list

models:
	docker-compose exec app php artisan model:show

status:
	@echo "=== Docker Services Status ==="
	@docker-compose ps
	@echo ""
	@echo "=== Application URLs ==="
	@echo "Frontend:        http://localhost:5173"
	@echo "API:             http://localhost/api"
	@echo "MailHog:         http://localhost:8025"
	@echo "pgAdmin:         http://localhost:5050"
	@echo "Redis Commander: http://localhost:8081"

# MCP Server
mcp-logs:
	docker-compose logs -f mcp

mcp-restart:
	docker-compose restart mcp
