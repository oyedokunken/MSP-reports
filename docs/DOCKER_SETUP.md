# Docker Development Environment Setup

## Overview

This Docker Compose configuration provides a complete development environment for the Multi-Tenant Reporting Platform with all required services.

## Services Included

### Core Application Services

1. **NGINX** (Port 80, 443)
   - Web server and reverse proxy
   - Handles static assets and routes to PHP-FPM

2. **Laravel App** (PHP 8.3-FPM)
   - Main application container
   - Runs Laravel backend

3. **TimescaleDB** (Port 5432)
   - PostgreSQL 16 with TimescaleDB extension
   - Time-series data storage for historical integration data
   - **SSL/TLS encryption enabled** for all connections
   - **SCRAM-SHA-256** password encryption
   - Configured for optimal performance and security

4. **Redis** (Port 6379)
   - Cache storage
   - Session management
   - Queue backend

5. **Queue Worker**
   - Processes background jobs
   - Handles async tasks (report generation, historical imports, etc.)

6. **Scheduler**
   - Runs Laravel's task scheduler
   - Executes cron jobs (sync schedules, report schedules, cleanup)

7. **MCP Server** (Port 3000)
   - Laravel MCP for AI-assisted development
   - Connects to Claude Desktop/IDE

8. **React Frontend** (Port 5173)
   - Vite development server
   - Hot module replacement for fast development

### Development Tools

9. **MailHog** (Port 8025)
   - Email testing interface
   - Catches all outbound emails
   - Web UI: http://localhost:8025

10. **pgAdmin** (Port 5050)
    - Database management interface
    - Web UI: http://localhost:5050
    - Login: admin@athenanetworks.com.au / admin

11. **Redis Commander** (Port 8081)
    - Redis management interface
    - Web UI: http://localhost:8081

---

## Prerequisites

- **Docker Desktop** (Mac/Windows) or **Docker Engine** (Linux)
- **Docker Compose** v2.0+
- **Git**
- Minimum 8GB RAM allocated to Docker
- Minimum 20GB free disk space

---

## Quick Start

### 1. Clone Repository
```bash
git clone https://gitlab.athenanetworks.com.au/athenanetworks/reporting.git
cd reporting
```

### 2. Generate SSL Certificates
```bash
./docker/timescaledb/scripts/generate-ssl-certs.sh
```

### 3. Copy Environment File
```bash
cp .env.example .env
```

### 4. Start Docker Services
```bash
docker-compose up -d
```

### 5. Install Laravel Dependencies
```bash
docker-compose exec app composer install
```

### 6. Generate Application Key
```bash
docker-compose exec app php artisan key:generate
```

### 7. Run Migrations
```bash
docker-compose exec app php artisan migrate
```

### 8. Verify Encryption (Optional)
```bash
make verify-encryption
```

### 9. Install Frontend Dependencies
```bash
cd frontend
npm install
```

### 10. Access Application
- **Frontend**: http://localhost:5173
- **API**: http://localhost/api
- **MailHog**: http://localhost:8025
- **pgAdmin**: http://localhost:5050
- **Redis Commander**: http://localhost:8081

---

## Common Commands

### Docker Management

**Start all services:**
```bash
docker-compose up -d
```

**Stop all services:**
```bash
docker-compose down
```

**View logs:**
```bash
docker-compose logs -f [service-name]
```

**Restart a service:**
```bash
docker-compose restart [service-name]
```

**Rebuild containers:**
```bash
docker-compose up -d --build
```

### Laravel Commands

**Run Artisan commands:**
```bash
docker-compose exec app php artisan [command]
```

**Access Laravel shell:**
```bash
docker-compose exec app bash
```

**Run migrations:**
```bash
docker-compose exec app php artisan migrate
```

**Seed database:**
```bash
docker-compose exec app php artisan db:seed
```

**Clear cache:**
```bash
docker-compose exec app php artisan cache:clear
docker-compose exec app php artisan config:clear
docker-compose exec app php artisan route:clear
docker-compose exec app php artisan view:clear
```

### Database Commands

**Access PostgreSQL CLI:**
```bash
docker-compose exec timescaledb psql -U reporting_user -d reporting
```

**Backup database:**
```bash
docker-compose exec timescaledb pg_dump -U reporting_user reporting > backup.sql
```

**Restore database:**
```bash
docker-compose exec -T timescaledb psql -U reporting_user reporting < backup.sql
```

### Queue & Scheduler

**Monitor queue:**
```bash
docker-compose logs -f queue
```

**Monitor scheduler:**
```bash
docker-compose logs -f scheduler
```

**Restart queue worker:**
```bash
docker-compose restart queue
```

### Frontend Development

**Install dependencies:**
```bash
cd frontend && npm install
```

**Build for production:**
```bash
cd frontend && npm run build
```

---

## Service URLs

| Service | URL | Credentials |
|---------|-----|-------------|
| Frontend | http://localhost:5173 | - |
| API | http://localhost/api | - |
| MailHog | http://localhost:8025 | - |
| pgAdmin | http://localhost:5050 | admin@athenanetworks.com.au / admin |
| Redis Commander | http://localhost:8081 | - |
| MCP Server | localhost:3000 | - |

---

## Troubleshooting

### Port Conflicts

If you get port binding errors, check if ports are already in use:

```bash
# Mac/Linux
lsof -i :80
lsof -i :5432
lsof -i :6379

# Windows
netstat -ano | findstr :80
```

Change ports in `docker-compose.yml` if needed.

### Permission Issues

If you encounter permission errors:

```bash
# Fix storage permissions
docker-compose exec app chmod -R 775 storage bootstrap/cache
docker-compose exec app chown -R www:www storage bootstrap/cache
```

### Database Connection Issues

Check TimescaleDB is running:
```bash
docker-compose ps timescaledb
docker-compose logs timescaledb
```

Test connection:
```bash
docker-compose exec app php artisan db:show
```

### Queue Not Processing

Restart queue worker:
```bash
docker-compose restart queue
docker-compose logs -f queue
```

### Frontend Not Hot Reloading

Ensure Vite is configured to listen on all interfaces:
```bash
# In frontend/vite.config.js
server: {
  host: '0.0.0.0',
  port: 5173
}
```

---

## Production Differences

### Key Changes for Production

1. **Remove Development Tools**
   - Remove MailHog, pgAdmin, Redis Commander
   - These are development-only services

2. **Security Hardening**
   - Use environment-specific `.env` file
   - Set `APP_DEBUG=false`
   - Set `MCP_READ_ONLY=true` or disable MCP entirely
   - Use strong database passwords
   - Enable TLS/SSL certificates

3. **Performance Optimization**
   - Increase worker processes
   - Configure OPcache for production
   - Use separate queue workers for different priorities
   - Enable Redis persistence

4. **Scaling**
   - Use managed database (RDS, Cloud SQL)
   - Use managed Redis (ElastiCache, Cloud Memorystore)
   - Deploy multiple app containers behind load balancer
   - Use object storage (S3) for file storage

5. **Monitoring**
   - Add application monitoring (New Relic, Datadog)
   - Add log aggregation (ELK, CloudWatch)
   - Add uptime monitoring
   - Add performance monitoring

### Production Docker Compose Example

```yaml
# docker-compose.prod.yml
version: '3.8'

services:
  nginx:
    # ... same config
    volumes:
      - ./docker/nginx/ssl:/etc/nginx/ssl  # Real SSL certs
  
  app:
    environment:
      - APP_ENV=production
      - APP_DEBUG=false
      - MCP_ENABLED=false  # Disable MCP in production
    # ... rest of config
  
  # Remove: mailhog, pgadmin, redis-commander, mcp, frontend
```

---

## Volume Management

### Persistent Data Volumes

- `timescaledb-data` - Database files
- `redis-data` - Redis persistence
- `pgadmin-data` - pgAdmin settings
- `frontend-node-modules` - Node modules cache

### Backup Volumes

```bash
# Backup TimescaleDB volume
docker run --rm -v reporting_timescaledb-data:/data -v $(pwd):/backup alpine tar czf /backup/timescaledb-backup.tar.gz /data

# Restore TimescaleDB volume
docker run --rm -v reporting_timescaledb-data:/data -v $(pwd):/backup alpine tar xzf /backup/timescaledb-backup.tar.gz -C /
```

### Clean Up Volumes

**Warning: This will delete all data!**

```bash
docker-compose down -v
```

---

## Performance Tuning

### Allocate More Resources

In Docker Desktop:
- Settings → Resources
- Increase CPUs to 4+
- Increase Memory to 8GB+
- Increase Swap to 2GB+

### TimescaleDB Optimization

Already configured in `docker-compose.yml`:
- `shared_buffers=512MB`
- `effective_cache_size=2GB`
- `work_mem=4MB`
- `max_connections=200`

Adjust based on your machine's resources.

### Redis Optimization

Already configured:
- `maxmemory=512mb`
- `maxmemory-policy=allkeys-lru`
- Append-only file persistence

---

## Next Steps

1. **Set up Laravel application** - See main README.md
2. **Configure integrations** - Add Autotask/Datto credentials
3. **Set up MCP** - Connect Claude Desktop (see LARAVEL_MCP.md)
4. **Start developing** - Build features per development phases

---

## Support

For issues or questions:
- Check logs: `docker-compose logs -f`
- Review this documentation
- Contact: josh@athenanetworks.com.au
