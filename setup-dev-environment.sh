#!/bin/bash

echo "=== Multi-Developer Environment Setup ==="
echo ""
echo "This script configures unique ports for each developer to avoid conflicts."
echo "Generated files:"
echo "  - docker-compose.override.yml (gitignored)"
echo ""

# Prompt for developer ID
read -p "Enter your developer ID (1-20,69): " DEV_ID

# Validate input
if ! [[ "$DEV_ID" =~ ^[0-9]+$ ]] || [ "$DEV_ID" -lt 1 ] || [ "$DEV_ID" -gt 69 ]; then
    echo "❌ Invalid developer ID. Must be between 1 and 20 or 69."
    exit 1
fi

# Calculate ports (base_port + developer_id)
NGINX_PORT=$((8000 + DEV_ID))
POSTGRES_PORT=$((5432 + DEV_ID))
REDIS_PORT=$((6379 + DEV_ID))
FRONTEND_PORT=$((5173 + DEV_ID))
MAILHOG_SMTP_PORT=$((1025 + DEV_ID))
MAILHOG_UI_PORT=$((8025 + DEV_ID))
PGADMIN_PORT=$((5050 + DEV_ID))
REDIS_CMD_PORT=$((8081 + DEV_ID))

# Create docker-compose.override.yml
cat > docker-compose.override.yml <<EOF
version: '3.8'

# Auto-generated for Developer ${DEV_ID}
# Do not commit this file (it's in .gitignore)

services:
  nginx:
    container_name: reporting-nginx-dev${DEV_ID}
    ports:
      - "${NGINX_PORT}:80"
      - "$((NGINX_PORT + 400)):443"
  
  app:
    container_name: reporting-app-dev${DEV_ID}
  
  timescaledb:
    container_name: reporting-timescaledb-dev${DEV_ID}
    ports:
      - "${POSTGRES_PORT}:5432"
  
  redis:
    container_name: reporting-redis-dev${DEV_ID}
    ports:
      - "${REDIS_PORT}:6379"
  
  frontend:
    container_name: reporting-frontend-dev${DEV_ID}
    ports:
      - "${FRONTEND_PORT}:5173"
    environment:
      - VITE_API_URL=http://localhost:${NGINX_PORT}/api
  
  mailhog:
    container_name: reporting-mailhog-dev${DEV_ID}
    ports:
      - "${MAILHOG_SMTP_PORT}:1025"
      - "${MAILHOG_UI_PORT}:8025"
  
  pgadmin:
    container_name: reporting-pgadmin-dev${DEV_ID}
    ports:
      - "${PGADMIN_PORT}:80"
  
  redis-commander:
    container_name: reporting-redis-commander-dev${DEV_ID}
    ports:
      - "${REDIS_CMD_PORT}:8081"

networks:
  reporting-network:
    name: reporting-network-dev${DEV_ID}

volumes:
  timescaledb-data:
    name: reporting-timescaledb-data-dev${DEV_ID}
  redis-data:
    name: reporting-redis-data-dev${DEV_ID}
  pgadmin-data:
    name: reporting-pgadmin-data-dev${DEV_ID}
  frontend-node-modules:
    name: reporting-frontend-node-modules-dev${DEV_ID}
EOF

echo ""
echo "✅ Environment configured for Developer ${DEV_ID}"
echo ""
echo "📋 Your service URLs:"
echo "  🎨 Frontend:        http://localhost:${FRONTEND_PORT}"
echo "  🚀 API:             http://localhost:${NGINX_PORT}/api"
echo "  📧 MailHog:         http://localhost:${MAILHOG_UI_PORT}"
echo "  🗄️  pgAdmin:         http://localhost:${PGADMIN_PORT}"
echo "  🔴 Redis Commander: http://localhost:${REDIS_CMD_PORT}"
echo ""
echo "🔌 Database connection:"
echo "  Host:     localhost"
echo "  Port:     ${POSTGRES_PORT}"
echo "  Database: reporting"
echo "  User:     reporting_user"
echo "  Password: reporting_password"
echo ""
echo "📝 Next steps:"
echo "     Run: make install"
echo ""
