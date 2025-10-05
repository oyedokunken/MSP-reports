# Multi-Developer Environment Setup

## Problem

When multiple developers work on the same remote desktop server, Docker port conflicts occur:
- Developer A starts services on port 80, 5432, 6379, etc.
- Developer B tries to start the same services → **Port conflict errors**

## Solution: Per-Developer Port Mapping

Each developer gets their own port range to avoid conflicts.

---

## Port Assignment Strategy

### Developer Port Ranges

Assign each developer a unique ID (1-20) and calculate their ports:

| Service | Base Port | Developer 1 | Developer 2 | Developer 3 | Formula |
|---------|-----------|-------------|-------------|-------------|---------|
| NGINX | 80 | 8001 | 8002 | 8003 | 8000 + ID |
| PostgreSQL | 5432 | 5433 | 5434 | 5435 | 5432 + ID |
| Redis | 6379 | 6380 | 6381 | 6382 | 6379 + ID |
| Frontend | 5173 | 5174 | 5175 | 5176 | 5173 + ID |
| MailHog UI | 8025 | 8026 | 8027 | 8028 | 8025 + ID |
| MailHog SMTP | 1025 | 1026 | 1027 | 1028 | 1025 + ID |
| pgAdmin | 5050 | 5051 | 5052 | 5053 | 5050 + ID |
| Redis Commander | 8081 | 8082 | 8083 | 8084 | 8081 + ID |
| MCP Server | 3000 | 3001 | 3002 | 3003 | 3000 + ID |

---

## Setup Instructions

### Option 1: Environment Variable Override (Recommended)

**1. Create Developer-Specific Environment File**

Each developer creates their own `.env.ports` file:

```bash
# .env.ports (Developer 1)
DEVELOPER_ID=1
```

```bash
# .env.ports (Developer 2)
DEVELOPER_ID=2
```

**2. Use docker-compose.override.yml**

Create a personal `docker-compose.override.yml` (gitignored):

```yaml
# docker-compose.override.yml (Developer 1)
version: '3.8'

services:
  nginx:
    ports:
      - "8001:80"
      - "8443:443"
  
  timescaledb:
    ports:
      - "5433:5432"
  
  redis:
    ports:
      - "6380:6379"
  
  frontend:
    ports:
      - "5174:5173"
  
  mailhog:
    ports:
      - "1026:1025"
      - "8026:8025"
  
  pgadmin:
    ports:
      - "5051:80"
  
  redis-commander:
    ports:
      - "8082:8081"
  
  mcp:
    ports:
      - "3001:3000"
```

**3. Start Services**

```bash
docker-compose up -d
# Automatically merges docker-compose.yml + docker-compose.override.yml
```

---

### Option 2: Automated Script (Easiest)

**1. Create Setup Script**

Save as `setup-dev-environment.sh`:

```bash
#!/bin/bash

echo "=== Multi-Developer Environment Setup ==="
echo ""

# Prompt for developer ID
read -p "Enter your developer ID (1-20): " DEV_ID

# Validate input
if ! [[ "$DEV_ID" =~ ^[0-9]+$ ]] || [ "$DEV_ID" -lt 1 ] || [ "$DEV_ID" -gt 20 ]; then
    echo "❌ Invalid developer ID. Must be between 1 and 20."
    exit 1
fi

# Calculate ports
NGINX_PORT=$((8000 + DEV_ID))
POSTGRES_PORT=$((5432 + DEV_ID))
REDIS_PORT=$((6379 + DEV_ID))
FRONTEND_PORT=$((5173 + DEV_ID))
MAILHOG_SMTP_PORT=$((1025 + DEV_ID))
MAILHOG_UI_PORT=$((8025 + DEV_ID))
PGADMIN_PORT=$((5050 + DEV_ID))
REDIS_CMD_PORT=$((8081 + DEV_ID))
MCP_PORT=$((3000 + DEV_ID))

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
  
  queue:
    container_name: reporting-queue-dev${DEV_ID}
  
  scheduler:
    container_name: reporting-scheduler-dev${DEV_ID}
  
  mcp:
    container_name: reporting-mcp-dev${DEV_ID}
    ports:
      - "${MCP_PORT}:3000"
  
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

# Create .env.local with developer-specific settings
cat > .env.local <<EOF
# Developer ${DEV_ID} Environment
DEVELOPER_ID=${DEV_ID}

# Update these in your .env file if needed
APP_URL=http://localhost:${NGINX_PORT}
VITE_API_URL=http://localhost:${NGINX_PORT}/api
EOF

echo ""
echo "✅ Environment configured for Developer ${DEV_ID}"
echo ""
echo "Your service URLs:"
echo "  Frontend:        http://localhost:${FRONTEND_PORT}"
echo "  API:             http://localhost:${NGINX_PORT}/api"
echo "  MailHog:         http://localhost:${MAILHOG_UI_PORT}"
echo "  pgAdmin:         http://localhost:${PGADMIN_PORT}"
echo "  Redis Commander: http://localhost:${REDIS_CMD_PORT}"
echo "  MCP Server:      localhost:${MCP_PORT}"
echo ""
echo "PostgreSQL connection:"
echo "  Host: localhost"
echo "  Port: ${POSTGRES_PORT}"
echo "  Database: reporting"
echo "  User: reporting_user"
echo ""
echo "Next steps:"
echo "  1. Copy .env.example to .env"
echo "  2. Run: make install"
echo "  3. Access your services at the URLs above"
echo ""
```

**3. Start Your Environment**

```bash
make install
```

---

## How It Works

### Port Calculation

Each developer gets a unique port offset based on their ID:

```
Developer 1: Base port + 1
Developer 2: Base port + 2
Developer 3: Base port + 3
...
```

### Container Naming

Containers are named with developer ID to avoid conflicts:

```
reporting-nginx-dev1
reporting-app-dev1
reporting-timescaledb-dev1
...
```

### Volume Naming

Each developer gets separate Docker volumes:

```
reporting-timescaledb-data-dev1
reporting-redis-data-dev1
...
```

This ensures:
- ✅ No data conflicts between developers
- ✅ Each developer has their own database
- ✅ Complete isolation between environments

### Network Naming

Each developer gets their own Docker network:

```
reporting-network-dev1
reporting-network-dev2
...
```

---

## Managing Your Environment

### View Your Configuration

```bash
cat docker-compose.override.yml
```

### Change Your Developer ID

```bash
# Stop current environment
make down

# Run setup script again with new ID
./setup-dev-environment.sh

# Start with new configuration
make up
```

### Clean Up Your Environment

```bash
# Stop and remove containers
make down

# Remove volumes (deletes database data)
docker volume rm reporting-timescaledb-data-dev1
docker volume rm reporting-redis-data-dev1
docker volume rm reporting-pgadmin-data-dev1
docker volume rm reporting-frontend-node-modules-dev1
```

---

## Team Coordination

### Developer ID Assignment

Maintain a team roster to avoid ID conflicts:

| Developer | ID | Status |
|-----------|----|---------| 
| Josh | 1 | Active |
| Sarah | 2 | Active |
| Mike | 3 | Active |
| ... | ... | ... |

### Communication

When a developer leaves or joins:
1. Update the roster
2. Notify team of available IDs
3. New developers claim next available ID

---

## Troubleshooting

### Port Still Conflicts

**Check if port is in use:**
```bash
# Mac/Linux
lsof -i :8001

# Windows
netstat -ano | findstr :8001
```

**Solutions:**
1. Choose a different developer ID
2. Stop the conflicting service
3. Use a higher ID number (10-20)

### Can't Remember My Ports

**Check your override file:**
```bash
grep "ports:" docker-compose.override.yml
```

**Or check running containers:**
```bash
docker ps --format "table {{.Names}}\t{{.Ports}}"
```

### Containers from Different Developers

**List all reporting containers:**
```bash
docker ps -a | grep reporting
```

**Stop specific developer's containers:**
```bash
docker stop $(docker ps -a | grep "dev2" | awk '{print $1}')
```

### Accidentally Committed override File

**Remove from git:**
```bash
git rm --cached docker-compose.override.yml
git commit -m "Remove developer-specific override file"
git push
```

The file is already in `.gitignore`, so it won't be committed again.

---

## Alternative: Docker Compose Profiles (Advanced)

For teams that prefer a different approach, you can use Docker Compose profiles:

**docker-compose.profiles.yml:**
```yaml
version: '3.8'

services:
  nginx:
    profiles: ["dev1", "dev2", "dev3"]
    ports:
      - "${NGINX_PORT:-80}:80"
```

**Usage:**
```bash
# Developer 1
COMPOSE_PROFILES=dev1 NGINX_PORT=8001 docker-compose up

# Developer 2
COMPOSE_PROFILES=dev2 NGINX_PORT=8002 docker-compose up
```

However, the automated script approach is simpler and less error-prone.

---

## Production Deployment

**Important:** The multi-developer setup is for **development only**.

For production:
- Use standard ports (80, 443, 5432, etc.)
- Deploy to dedicated infrastructure
- Use managed services (RDS, ElastiCache, etc.)
- No port conflicts (single deployment per server)

---

## Summary

✅ **Automated Setup**: Run `./setup-dev-environment.sh` once
✅ **No Conflicts**: Each developer gets unique ports
✅ **Isolated Data**: Separate databases and volumes
✅ **Easy Cleanup**: Standard Docker commands work
✅ **Team Friendly**: Simple ID-based coordination

**Questions?** See `docs/DOCKER_SETUP.md` or contact josh@athenanetworks.com.au

