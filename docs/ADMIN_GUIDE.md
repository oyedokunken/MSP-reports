# MSP Reports - Administrator Guide

## Table of Contents

1. [Deployment](#deployment)
2. [Configuration](#configuration)
3. [Authentication Setup](#authentication-setup)
4. [Integration Configuration](#integration-configuration)
5. [Agent Deployment](#agent-deployment)
6. [Monitoring & Maintenance](#monitoring--maintenance)
7. [Backup & Recovery](#backup--recovery)
8. [Security](#security)

---

## Deployment

### Requirements

- Docker & Docker Compose
- PostgreSQL 16+ with TimescaleDB extension
- Redis 8.2+
- PHP 8.2+
- Node.js 20+
- SMTP server for email delivery

### Docker Deployment

```bash
# Clone repository
git clone https://gitlab.athenanetworks.com.au/athenanetworks/reporting.git
cd reporting

# Copy environment files
cp backend/.env.example backend/.env
cp frontend/.env.example frontend/.env

# Configure environment variables (see Configuration section)
nano backend/.env

# Start services
docker-compose up -d

# Run migrations
docker-compose exec backend php artisan migrate

# Create first admin user
docker-compose exec backend php artisan user:create-admin
```

### Manual Deployment

```bash
# Backend
cd backend
composer install --no-dev
php artisan migrate
php artisan config:cache
php artisan route:cache

# Frontend
cd frontend
npm install
npm run build
```

---

## Configuration

### Backend Environment Variables

```env
# Application
APP_NAME="MSP Reports"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://reports.yourmsp.com

# Database
DB_CONNECTION=pgsql
DB_HOST=localhost
DB_PORT=5432
DB_DATABASE=msp_reports
DB_USERNAME=msp_reports
DB_PASSWORD=secure_password

# Redis
REDIS_HOST=localhost
REDIS_PORT=6379

# Mail
MAIL_MAILER=smtp
MAIL_HOST=smtp.yourmsp.com
MAIL_PORT=587
MAIL_USERNAME=reports@yourmsp.com
MAIL_PASSWORD=mail_password
MAIL_FROM_ADDRESS=reports@yourmsp.com
MAIL_FROM_NAME="MSP Reports"

# Queue
QUEUE_CONNECTION=redis

# Session
SESSION_DRIVER=redis
SESSION_LIFETIME=120
```

### Frontend Environment Variables

```env
VITE_API_BASE_URL=https://reports.yourmsp.com/api
VITE_APP_NAME="MSP Reports"
```

---

## Authentication Setup

### Local Authentication

Enabled by default. Configure password policy in company settings.

### SAML SSO (Keycloak/Azure AD)

1. **IdP Configuration**:
   - Entity ID: `mspreports`
   - ACS URL: `https://reports.yourmsp.com/api/auth/saml/acs`
   - SLS URL: `https://reports.yourmsp.com/api/auth/saml/sls`
   - Metadata URL: `https://reports.yourmsp.com/api/saml/metadata`

2. **Backend Configuration** (`backend/.env`):
```env
SAML_ENABLED=true
SAML_IDP_ENTITY_ID=https://auth.yourmsp.com/realms/mspreports
SAML_IDP_SSO_URL=https://auth.yourmsp.com/realms/mspreports/protocol/saml
SAML_IDP_SLS_URL=https://auth.yourmsp.com/realms/mspreports/protocol/saml
SAML_IDP_CERT_PATH=storage/saml/idp-cert.pem
SAML_SP_ENTITY_ID=mspreports
SAML_SP_ACS_URL=https://reports.yourmsp.com/api/auth/saml/acs
SAML_SP_SLS_URL=https://reports.yourmsp.com/api/auth/saml/sls
```

3. **Certificate Files** (place in `backend/storage/saml/`):
   - `idp-cert.pem` - IdP signing certificate
   - `sp-cert.pem` - SP certificate (optional)
   - `sp-private-key.pem` - SP private key (optional)

### OAuth2/OIDC (Keycloak)

1. **Keycloak Configuration**:
   - Create OIDC client with PKCE enabled
   - Redirect URI: `https://reports.yourmsp.com/api/auth/keycloak/callback`

2. **Backend Configuration** (`backend/.env`):
```env
KEYCLOAK_CLIENT_ID=mspreports-oidc
KEYCLOAK_CLIENT_SECRET=your_client_secret
KEYCLOAK_BASE_URL=https://auth.yourmsp.com
KEYCLOAK_REALM=mspreports
```

---

## Integration Configuration

### Autotask PSA

```env
AUTOTASK_API_URL=https://webservices.autotask.net/ATServicesRest
AUTOTASK_USERNAME=api_user
AUTOTASK_SECRET=api_secret
AUTOTASK_INTEGRATION_CODE=your_integration_code
```

### SharePoint (Report Delivery)

```env
MICROSOFT_TENANT_ID=your_tenant_id
MICROSOFT_CLIENT_ID=your_client_id
MICROSOFT_CLIENT_SECRET=your_client_secret
MICROSOFT_SHAREPOINT_SITE_ID=your_site_id
MICROSOFT_SHAREPOINT_DRIVE_ID=your_drive_id
```

Required Graph API permissions:
- `Sites.ReadWrite.All`
- `Files.ReadWrite.All`

---

## Agent Deployment

### Active Directory Agent

Deploy to a Windows Server with AD role:

1. Copy `agents/windows_server/active-directory/` to server
2. Create `config.json` from `config.example.json`:

```json
{
  "platform": {
    "apiUrl": "https://reports.yourmsp.com/api",
    "apiKey": "msp_your_api_key_here"
  },
  "activeDirectory": {
    "searchBase": "DC=yourdomain,DC=local",
    "userFilter": "(&(objectClass=user)(objectCategory=person))",
    "groupFilter": "(objectClass=group)"
  },
  "licensing": {
    "enabled": true,
    "licenseGroups": [
      {
        "groupPattern": "*Office365*",
        "licenseName": "Microsoft 365",
        "licenseType": "SPLA",
        "costPerUser": 12.50
      }
    ]
  },
  "sync": {
    "deltaSync": true,
    "fullSyncInterval": 24
  }
}
```

3. Test: `.\ad-sync-agent.ps1 -DryRun`
4. Run: `.\ad-sync-agent.ps1`
5. Schedule via Task Scheduler (every 4 hours recommended)

### Exchange Agent

Deploy to Exchange Server:

1. Copy `agents/windows_server/exchange/` to server
2. Create `config.json` with same platform settings
3. Run from **Exchange Management Shell**
4. Test: `.\exchange-sync-agent.ps1 -DryRun`
5. Run: `.\exchange-sync-agent.ps1`

---

## Monitoring & Maintenance

### Health Checks

```bash
# API health
curl https://reports.yourmsp.com/api/health

# Queue status
docker-compose exec backend php artisan queue:monitor
```

### Log Locations

- Application logs: `backend/storage/logs/laravel.log`
- Queue worker logs: `backend/storage/logs/worker.log`
- Nginx access logs: `/var/log/nginx/access.log`

### Common Maintenance Tasks

```bash
# Clear caches
php artisan cache:clear
php artisan config:clear
php artisan route:clear

# Run migrations
php artisan migrate

# Process queue
php artisan queue:work

# Retry failed jobs
php artisan queue:retry all
```

### Database Maintenance

```sql
-- Check table sizes
SELECT relname, pg_size_pretty(pg_total_relation_size(relid))
FROM pg_catalog.pg_statio_user_tables
ORDER BY pg_total_relation_size(relid) DESC;

-- TimescaleDB compression status
SELECT * FROM timescaledb_information.compressed_chunk_stats;

-- Vacuum and analyze
VACUUM ANALYZE;
```

---

## Backup & Recovery

### Database Backup

```bash
# Full backup
pg_dump -h localhost -U msp_reports -d msp_reports -F c -f backup.dump

# Restore
pg_restore -h localhost -U msp_reports -d msp_reports -c backup.dump
```

### File Backup

Backup these directories:
- `backend/storage/app/` - Generated reports, uploads
- `backend/storage/saml/` - SAML certificates
- `backend/.env` - Configuration

### Automated Backups

Add to crontab:
```cron
0 2 * * * /usr/local/bin/backup-msp-reports.sh
```

---

## Security

### Firewall Rules

```bash
# Allow HTTPS
ufw allow 443/tcp

# Allow SSH (restrict to admin IPs)
ufw allow from 10.0.0.0/8 to any port 22

# Block all other inbound
ufw default deny incoming
```

### SSL/TLS

Use Let's Encrypt or your own certificates:

```bash
certbot --nginx -d reports.yourmsp.com
```

### API Key Security

- Use customer-scoped keys for agents
- Enable IP whitelisting for production keys
- Rotate keys every 6-12 months
- Revoke unused keys immediately

### Audit Logs

Audit logs are retained for 7 years (compliance requirement).

View in UI: **Settings > Security > Audit Logs**

Or query directly:
```sql
SELECT * FROM audit_logs
WHERE event_type = 'api_key_created'
ORDER BY created_at DESC
LIMIT 100;
```

### Security Checklist

- [ ] HTTPS enabled with valid certificate
- [ ] Database encrypted at rest
- [ ] Strong passwords for all accounts
- [ ] SSO enabled for enterprise users
- [ ] API keys have minimal permissions
- [ ] IP whitelisting for sensitive keys
- [ ] Regular backup testing
- [ ] Audit log monitoring
- [ ] Firewall configured
- [ ] Updates applied regularly
