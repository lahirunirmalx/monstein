# =============================================================================
# Monstein API - Makefile
# =============================================================================
# Quick commands for Docker operations
#
# Usage:
#   make help          - Show all available commands
#   make up            - Start all services
#   make down          - Stop all services
#   make logs          - View logs
# =============================================================================

.PHONY: help build up down restart logs shell db-shell migrate test clean

# Default target
help:
	@echo ""
	@echo "Monstein API - Docker Commands"
	@echo "=============================="
	@echo ""
	@echo "Setup:"
	@echo "  make setup        - Initial setup (copy .env, build, start)"
	@echo "  make build        - Build Docker images"
	@echo ""
	@echo "Running:"
	@echo "  make up           - Start all services"
	@echo "  make up-dev       - Start with development profile (includes Adminer)"
	@echo "  make down         - Stop all services"
	@echo "  make restart      - Restart all services"
	@echo ""
	@echo "Scaling:"
	@echo "  make scale N=3    - Scale app to N instances"
	@echo ""
	@echo "Logs:"
	@echo "  make logs         - View all logs"
	@echo "  make logs-app     - View app logs only"
	@echo "  make logs-db      - View database logs only"
	@echo "  make logs-lb      - View load balancer logs only"
	@echo ""
	@echo "Database:"
	@echo "  make db-shell     - Open database shell"
	@echo "  make migrate      - Run database migrations"
	@echo "  make migrate-rollback - Rollback last migration"
	@echo ""
	@echo "Development:"
	@echo "  make shell        - Open shell in app container"
	@echo "  make test         - Run tests"
	@echo ""
	@echo "Maintenance:"
	@echo "  make clean        - Remove all containers and volumes"
	@echo "  make prune        - Remove unused Docker resources"
	@echo ""

# =============================================================================
# Setup
# =============================================================================

setup:
	@if [ ! -f .env ]; then \
		echo "Creating .env from .env.docker..."; \
		cp .env.docker .env; \
		echo "⚠ Please edit .env with your settings before starting!"; \
	fi
	@make build
	@echo ""
	@echo "✓ Setup complete!"
	@echo "  1. Edit .env with your settings"
	@echo "  2. Run: make up"

build:
	docker-compose build --no-cache

build-dev:
	docker-compose build --target development

# =============================================================================
# Running Services
# =============================================================================

up:
	docker-compose up -d
	@echo ""
	@echo "✓ Monstein API started!"
	@echo "  API: http://localhost:$${LB_PORT:-80}"
	@make status

up-dev:
	docker-compose --profile dev up -d
	@echo ""
	@echo "✓ Monstein API started (development mode)!"
	@echo "  API:     http://localhost:$${LB_PORT:-80}"
	@echo "  Adminer: http://localhost:$${ADMINER_PORT:-8081}"

down:
	docker-compose down

restart:
	docker-compose restart

status:
	@echo ""
	@echo "Container Status:"
	@docker-compose ps

# =============================================================================
# Scaling
# =============================================================================

N ?= 2
scale:
	docker-compose up -d --scale app=$(N)
	@echo "✓ Scaled to $(N) app instances"

# =============================================================================
# Logs
# =============================================================================

logs:
	docker-compose logs -f

logs-app:
	docker-compose logs -f app

logs-db:
	docker-compose logs -f db

logs-lb:
	docker-compose logs -f loadbalancer

# =============================================================================
# Database
# =============================================================================

db-shell:
	docker-compose exec db mariadb -u$${DB_USER:-monstein} -p$${DB_PASS:-monstein_secret} $${DB_NAME:-monstein}

migrate:
	docker-compose exec app ./vendor/bin/phinx migrate

migrate-rollback:
	docker-compose exec app ./vendor/bin/phinx rollback

migrate-status:
	docker-compose exec app ./vendor/bin/phinx status

# =============================================================================
# Development
# =============================================================================

shell:
	docker-compose exec app sh

test:
	docker-compose exec app ./vendor/bin/phpunit

lint:
	docker-compose exec app find app -name "*.php" -print0 | xargs -0 -n1 php -l

# =============================================================================
# Maintenance
# =============================================================================

clean:
	docker-compose down -v --remove-orphans
	@echo "✓ Containers and volumes removed"

prune:
	docker system prune -f
	@echo "✓ Unused Docker resources removed"

# Health check
health:
	@curl -s http://localhost:$${LB_PORT:-80}/health | jq . || echo "Service not responding"
	@curl -s http://localhost:$${LB_PORT:-80}/lb-health | jq . || echo "Load balancer not responding"
