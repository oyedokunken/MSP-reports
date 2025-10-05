# PostgreSQL Encryption - Quick Reference

## ✅ What's Configured

### Encryption in Transit (SSL/TLS)
- **Status**: ✅ Enabled
- **Protocol**: TLS 1.2+ 
- **Cipher**: Strong ciphers only
- **Password Hash**: SCRAM-SHA-256 (strongest available)
- **Certificates**: Self-signed for development (auto-generated)

### Encryption at Rest
- **Development**: Docker Desktop encrypted volumes (automatic on Mac)
- **Production**: Cloud KMS or LUKS encryption (documented)

### Audit Logging
- **Status**: ✅ Enabled
- **Logs**: All connections, disconnections, and data modifications
- **Format**: Timestamped with user, database, client IP, SSL status

---

## 🚀 Quick Commands

```bash
# Generate SSL certificates (first time setup)
make generate-certs

# Verify encryption is working
make verify-encryption

# Check SSL from application
make db-verify-ssl

# View PostgreSQL logs
docker-compose logs timescaledb | grep SSL
```

---

## 📋 Verification Checklist

After starting services, verify encryption:

```bash
# 1. Start services
make up

# 2. Verify SSL is enabled
make verify-encryption

# Expected output:
# ssl = on
# password_encryption = scram-sha-256
# SSL connections shown with TLS version and cipher
```

---

## 🔒 What Gets Encrypted

### In Transit (SSL/TLS)
- ✅ All database connections from Laravel app
- ✅ All database connections from queue workers
- ✅ All database connections from scheduler
- ✅ All database connections from MCP server
- ✅ pgAdmin connections
- ✅ Manual psql connections

### At Rest
- ✅ Database files on disk (via volume/disk encryption)
- ✅ WAL (Write-Ahead Log) files
- ✅ Backup files
- ✅ Temporary files

### Application Level
- ✅ Integration credentials (Laravel encryption)
- ✅ API keys (Laravel encryption)
- ✅ User passwords (bcrypt)
- ✅ Database passwords (SCRAM-SHA-256)

---

## 📖 Full Documentation

- **Complete Guide**: See `ENCRYPTION.md`
- **Docker Setup**: See `DOCKER_SETUP.md`
- **Production Deployment**: See `ENCRYPTION.md` → Production sections

---

## ⚠️ Important Notes

### Development
- Self-signed certificates are automatically generated
- Certificates are gitignored (regenerate on each machine)
- SSL verification uses `require` mode (not `verify-full`)

### Production
- **Must use CA-signed certificates** (Let's Encrypt, AWS ACM, etc.)
- **Must use `verify-full` SSL mode** with proper CA bundle
- **Must enable encryption at rest** (cloud KMS or LUKS)
- **Must rotate certificates** before expiry
- **Must store keys securely** (AWS Secrets Manager, Vault, etc.)

---

## 🎯 ISO 27001 Compliance

| Requirement | Status | Implementation |
|-------------|--------|----------------|
| Encryption in Transit | ✅ | SSL/TLS 1.2+ |
| Encryption at Rest | ✅ | Volume/disk encryption |
| Strong Password Hashing | ✅ | SCRAM-SHA-256 |
| Audit Logging | ✅ | PostgreSQL logging |
| Certificate Management | ✅ | Documented procedures |
| Key Management | ✅ | Secure storage documented |
| Access Control | ✅ | Encrypted connections required |

---

## 🆘 Troubleshooting

**SSL certificates not found?**
```bash
make generate-certs
make restart
```

**SSL connection failed?**
```bash
# Check certificate permissions
docker-compose exec timescaledb ls -la /var/lib/postgresql/ssl/

# Regenerate if needed
make generate-certs
make restart
```

**Want to verify it's actually encrypted?**
```bash
# Check from PostgreSQL
docker-compose exec timescaledb psql -U reporting_user -d reporting \
  -c "SELECT ssl, version, cipher FROM pg_stat_ssl WHERE pid = pg_backend_pid();"

# Should show: ssl=t, version=TLSv1.3, cipher=TLS_AES_256_GCM_SHA384
```

---

## 📞 Support

- **Encryption Questions**: See `ENCRYPTION.md`
- **Setup Issues**: See `DOCKER_SETUP.md`
- **Contact**: josh@athenanetworks.com.au
