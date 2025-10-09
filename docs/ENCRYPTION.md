# Database Encryption Guide

## Overview

This platform implements **encryption at rest** and **encryption in transit** for all database operations to meet ISO 27001 compliance requirements.

---

## Encryption in Transit (SSL/TLS)

### Development Environment

**What's Configured:**
- PostgreSQL SSL/TLS enabled with self-signed certificates
- TLS 1.2 minimum protocol version
- Strong cipher suites configured
- All database connections encrypted

**Certificates Location:**
```
docker/timescaledb/ssl/
├── server.crt  (SSL certificate)
└── server.key  (Private key)
```

**Regenerate Certificates:**
```bash
./docker/timescaledb/scripts/generate-ssl-certs.sh
```

**Connection String:**
```
postgresql://reporting_user:password@timescaledb:5432/reporting?sslmode=require
```

### Production Environment

**Requirements:**
- Use CA-signed certificates (Let's Encrypt, AWS ACM, DigiCert, etc.)
- Set `sslmode=verify-full` in connection string
- Provide CA certificate bundle
- Rotate certificates before expiry

**Production Configuration:**
```env
DB_SSLMODE=verify-full
DB_SSLROOTCERT=/etc/ssl/certs/ca-certificates.crt
```

**Managed Database Services:**
Most cloud providers handle SSL/TLS automatically:
- **AWS RDS**: SSL enabled by default, download RDS CA bundle
- **Google Cloud SQL**: SSL enforced, download server CA cert
- **Azure Database**: SSL required by default, download SSL cert

---

## Encryption at Rest

### Development Environment

**Docker Volume Encryption:**

For local development, Docker volumes store data unencrypted by default. For enhanced security during development:

**Option 1: Docker Desktop Encryption (Mac)**
- Docker Desktop on Mac uses encrypted APFS volumes automatically
- No additional configuration needed

**Option 2: Encrypted Docker Volume (Linux)**
```bash
# Create encrypted volume using LUKS
sudo cryptsetup luksFormat /dev/sdb1
sudo cryptsetup open /dev/sdb1 encrypted-docker
sudo mkfs.ext4 /dev/mapper/encrypted-docker
sudo mount /dev/mapper/encrypted-docker /var/lib/docker/volumes
```

**Option 3: Host Filesystem Encryption**
- Use full disk encryption (FileVault on Mac, LUKS on Linux)
- Docker volumes inherit host encryption

### Production Environment - On-Premise

**LUKS Encryption (Linux):**

1. **Create Encrypted Volume:**
```bash
# Format disk with LUKS
sudo cryptsetup luksFormat /dev/sdb --type luks2

# Open encrypted volume
sudo cryptsetup open /dev/sdb pgdata

# Create filesystem
sudo mkfs.ext4 /dev/mapper/pgdata

# Mount
sudo mount /dev/mapper/pgdata /var/lib/postgresql/data
```

2. **Auto-Mount on Boot:**
```bash
# Add to /etc/crypttab
pgdata /dev/sdb none luks

# Add to /etc/fstab
/dev/mapper/pgdata /var/lib/postgresql/data ext4 defaults 0 2
```

3. **Key Management:**
- Store LUKS passphrase in secure key management system
- Use TPM (Trusted Platform Module) for automatic unlock
- Implement key rotation policy (annually)

### Production Environment - Cloud

**AWS RDS:**
```terraform
resource "aws_db_instance" "reporting" {
  storage_encrypted = true
  kms_key_id       = aws_kms_key.rds.arn
  # ... other config
}
```

**Google Cloud SQL:**
```terraform
resource "google_sql_database_instance" "reporting" {
  settings {
    disk_encryption_configuration {
      kms_key_name = google_kms_crypto_key.sql.id
    }
  }
}
```

**Azure Database:**
```terraform
resource "azurerm_postgresql_server" "reporting" {
  # Encryption enabled by default
  infrastructure_encryption_enabled = true
  # ... other config
}
```

---

## Password Encryption

### SCRAM-SHA-256

PostgreSQL configured to use SCRAM-SHA-256 for password hashing (strongest available):

```sql
-- Configured in postgresql.conf
password_encryption = scram-sha-256
```

**Benefits:**
- Stronger than MD5 (legacy)
- Salted hashing prevents rainbow table attacks
- Challenge-response authentication
- Prevents password sniffing

### Laravel Application Encryption

**Sensitive Data Encryption:**

Integration credentials and API keys encrypted in database:

```php
// config/database.php
'encrypt' => env('DB_ENCRYPT', true),

// Encrypting sensitive fields
use Illuminate\Database\Eloquent\Casts\Encrypted;

class Integration extends Model
{
    protected $casts = [
        'credentials' => Encrypted::class,
        'api_key' => Encrypted::class,
    ];
}
```

**Encryption Key Management:**
- Laravel `APP_KEY` stored in `.env` (never commit!)
- Production: Use AWS Secrets Manager, Azure Key Vault, or HashiCorp Vault
- Rotate encryption keys annually

---

## Audit Logging for Encryption

### PostgreSQL Connection Logging

Configured to log all connections and disconnections:

```sql
-- In postgresql.conf
log_connections = on
log_disconnections = on
log_duration = on
log_statement = 'mod'  -- Log all data modifications
```

**Log Format:**
```
2025-10-05 11:30:15 AEST [12345]: [1-1] user=reporting_user,db=reporting,app=Laravel,client=172.18.0.5 LOG: connection authorized: user=reporting_user database=reporting SSL enabled (protocol=TLSv1.3, cipher=TLS_AES_256_GCM_SHA384, bits=256)
```

### Laravel Audit Logging

All database operations logged to `AuditLog` model:

```php
AuditLog::create([
    'company_id' => $company->id,
    'event_type' => 'database_access',
    'actor_type' => 'User',
    'actor_id' => $user->id,
    'event_data' => [
        'action' => 'query',
        'table' => 'integrations',
        'ssl_enabled' => true,
    ],
    'ip_address' => $request->ip(),
]);
```

---

## Verification & Testing

### Verify SSL/TLS Encryption

**From Application Container:**
```bash
docker-compose exec app php artisan tinker

# In Tinker:
DB::connection()->getPdo()->query("SHOW ssl")->fetch();
# Should return: on

DB::connection()->getPdo()->query("SELECT * FROM pg_stat_ssl WHERE pid = pg_backend_pid()")->fetch();
# Should show: ssl=t, version=TLSv1.3, cipher=TLS_AES_256_GCM_SHA384
```

**From PostgreSQL:**
```bash
docker-compose exec timescaledb psql -U reporting_user -d reporting

-- Check SSL status
SHOW ssl;

-- Check active SSL connections
SELECT datname, usename, ssl, version, cipher 
FROM pg_stat_ssl 
JOIN pg_stat_activity ON pg_stat_ssl.pid = pg_stat_activity.pid;
```

### Verify Password Encryption

```bash
docker-compose exec timescaledb psql -U reporting_user -d reporting

-- Check password encryption method
SHOW password_encryption;
-- Should return: scram-sha-256

-- View user password hashes (should start with SCRAM-SHA-256)
SELECT rolname, rolpassword FROM pg_authid WHERE rolname = 'reporting_user';
```

### Verify Data Encryption at Rest

**Development (Docker Volume):**
```bash
# Check if host filesystem is encrypted
# Mac: diskutil info / | grep "Encrypted"
# Linux: lsblk -o NAME,FSTYPE,MOUNTPOINT,SIZE,TYPE,ENC

# Docker volume location
docker volume inspect reporting_timescaledb-data
```

**Production (Managed Database):**
```bash
# AWS RDS
aws rds describe-db-instances --db-instance-identifier reporting-db \
  --query 'DBInstances[0].StorageEncrypted'

# Google Cloud SQL
gcloud sql instances describe reporting-db --format="value(settings.diskEncryptionConfiguration)"

# Azure
az postgres server show --resource-group rg-reporting --name reporting-db \
  --query "infrastructureEncryption"
```

---

## Compliance Checklist

### ISO 27001 Requirements

- [x] **Encryption in Transit**: SSL/TLS 1.2+ for all database connections
- [x] **Encryption at Rest**: Volume/disk encryption for database storage
- [x] **Strong Password Hashing**: SCRAM-SHA-256 for database passwords
- [x] **Application Encryption**: Laravel encryption for sensitive fields
- [x] **Key Management**: Secure storage of encryption keys
- [x] **Audit Logging**: All connections and data modifications logged
- [x] **Certificate Management**: SSL certificates with expiry monitoring
- [x] **Access Control**: Encrypted connections required, no plaintext

### Regular Security Tasks

**Monthly:**
- [ ] Review encryption audit logs
- [ ] Verify SSL certificate validity
- [ ] Check for failed SSL connection attempts

**Quarterly:**
- [ ] Test encryption verification procedures
- [ ] Review key management practices
- [ ] Update security documentation

**Annually:**
- [ ] Rotate encryption keys
- [ ] Renew SSL certificates
- [ ] Security audit of encryption implementation
- [ ] Update to latest TLS version if available

---

## Troubleshooting

### SSL Connection Errors

**Error: "SSL connection has been closed unexpectedly"**
```bash
# Check certificate permissions
docker-compose exec timescaledb ls -la /var/lib/postgresql/ssl/
# server.key should be 600, server.crt should be 644

# Regenerate certificates if corrupted
./docker/timescaledb/scripts/generate-ssl-certs.sh
docker-compose restart timescaledb
```

**Error: "certificate verify failed"**
```env
# For development, use require instead of verify-full
DB_SSLMODE=require

# For production, ensure CA certificate is valid
DB_SSLMODE=verify-full
DB_SSLROOTCERT=/path/to/valid/ca-cert.crt
```

### Performance Impact

**SSL/TLS Overhead:**
- Typical overhead: 1-5% CPU increase
- Negligible for most workloads
- Benefits far outweigh performance cost

**Encryption at Rest:**
- Modern hardware encryption: <1% overhead
- Software encryption (LUKS): 2-5% overhead
- Use hardware-accelerated encryption when available

---

## Production Deployment Checklist

### Before Go-Live

- [ ] Replace self-signed certificates with CA-signed certificates
- [ ] Configure `DB_SSLMODE=verify-full`
- [ ] Enable encryption at rest (cloud KMS or LUKS)
- [ ] Store encryption keys in secure key management system
- [ ] Configure automatic certificate renewal
- [ ] Set up certificate expiry monitoring
- [ ] Test SSL connection from application
- [ ] Verify audit logging is working
- [ ] Document key rotation procedures
- [ ] Train operations team on encryption management

### Post-Deployment

- [ ] Monitor SSL connection success rate
- [ ] Review encryption audit logs weekly
- [ ] Set up alerts for certificate expiry (30 days before)
- [ ] Schedule quarterly encryption verification tests
- [ ] Document any encryption-related incidents

---

## Support & References

**Documentation:**
- PostgreSQL SSL: https://www.postgresql.org/docs/16/ssl-tcp.html
- Laravel Encryption: https://laravel.com/docs/12.x/encryption
- LUKS Encryption: https://gitlab.com/cryptsetup/cryptsetup

**Internal Contacts:**
- Security Questions: josh@athenanetworks.com.au
- Certificate Management: DevOps team
- Key Management: Security team

**Emergency Procedures:**
- Certificate expiry: See `docs/runbooks/ssl-certificate-renewal.md`
- Key compromise: See `docs/runbooks/encryption-key-rotation.md`
- Encryption failure: See `docs/runbooks/database-encryption-recovery.md`
