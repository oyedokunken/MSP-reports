# Backup & Disaster Recovery Guide

## Overview

This guide covers the complete backup and disaster recovery procedures for the MSP Reporting Platform. The system implements automated daily backups with encryption, retention management, and tested restore procedures.

---

## 📋 Table of Contents

1. [Backup Strategy](#backup-strategy)
2. [Quick Start](#quick-start)
3. [Automated Backup Setup](#automated-backup-setup)
4. [Manual Backup](#manual-backup)
5. [Restore Procedures](#restore-procedures)
6. [Backup Verification](#backup-verification)
7. [Disaster Recovery](#disaster-recovery)
8. [Troubleshooting](#troubleshooting)

---

## Backup Strategy

### What Gets Backed Up

**1. Database (PostgreSQL/TimescaleDB)**
- All application data
- Time-series historical data
- User accounts and permissions
- Configuration settings

**2. Application Files**
- Generated reports (PDFs)
- Uploaded logos and assets
- Storage directory
- Configuration files (.env, docker-compose.yml)

### Backup Schedule

- **Frequency:** Daily at 2:00 AM
- **Retention:** 30 days (configurable)
- **Encryption:** AES-256 (optional but recommended)
- **Verification:** Monthly automated testing

### Recovery Objectives

- **RTO (Recovery Time Objective):** 4 hours
- **RPO (Recovery Point Objective):** 24 hours

---

## Quick Start

### Prerequisites

```bash
# Install required tools
sudo apt-get update
sudo apt-get install -y gpg cron

# Make scripts executable
chmod +x backend/scripts/*.sh
```

### Test Backup (Manual)

```bash
# Set encryption key (optional but recommended)
export ENCRYPTION_KEY="your-secure-encryption-key-here"

# Backup database
./backend/scripts/backup-database.sh

# Backup files
./backend/scripts/backup-files.sh

# Verify backups
./backend/scripts/verify-backup.sh
```

---

## Automated Backup Setup

### Step 1: Configure Environment

Create `/etc/reporting-backup.conf`:

```bash
# Backup Configuration
export BACKUP_DIR="/var/backups/reporting/database"
export FILES_BACKUP_DIR="/var/backups/reporting/files"
export RETENTION_DAYS="30"
export CONTAINER_NAME="reporting-timescaledb"
export DB_NAME="reporting"
export DB_USER="reporting_user"
export APP_DIR="/home/kristian/reporting/backend"

# Encryption (HIGHLY RECOMMENDED)
export ENCRYPTION_KEY="your-secure-encryption-key-here"
```

**⚠️ IMPORTANT:** Store encryption key securely! Without it, backups cannot be restored.

### Step 2: Set Up Cron Jobs

```bash
# Edit crontab
sudo crontab -e

# Add these lines:

# Source configuration
SHELL=/bin/bash
BASH_ENV=/etc/reporting-backup.conf

# Database backup - Daily at 2:00 AM
0 2 * * * /home/kristian/reporting/backend/scripts/backup-database.sh >> /var/log/reporting-backup-db.log 2>&1

# File backup - Daily at 2:15 AM
15 2 * * * /home/kristian/reporting/backend/scripts/backup-files.sh >> /var/log/reporting-backup-files.log 2>&1

# Verify backups - Monthly on 1st at 3:00 AM
0 3 1 * * /home/kristian/reporting/backend/scripts/verify-backup.sh >> /var/log/reporting-backup-verify.log 2>&1
```

### Step 3: Create Log Directory

```bash
sudo mkdir -p /var/log
sudo touch /var/log/reporting-backup-db.log
sudo touch /var/log/reporting-backup-files.log
sudo touch /var/log/reporting-backup-verify.log
sudo chmod 644 /var/log/reporting-backup-*.log
```

### Step 4: Test Automated Backup

```bash
# Run backup manually to test
sudo -E /home/kristian/reporting/backend/scripts/backup-database.sh
sudo -E /home/kristian/reporting/backend/scripts/backup-files.sh

# Check logs
tail -f /var/log/reporting-backup-db.log
```

---

## Manual Backup

### Database Backup

```bash
# With encryption (recommended)
export ENCRYPTION_KEY="your-key"
./backend/scripts/backup-database.sh

# Without encryption
./backend/scripts/backup-database.sh

# Custom backup location
export BACKUP_DIR="/custom/path"
./backend/scripts/backup-database.sh
```

### File Backup

```bash
# With encryption (recommended)
export ENCRYPTION_KEY="your-key"
./backend/scripts/backup-files.sh

# Without encryption
./backend/scripts/backup-files.sh

# Custom backup location
export BACKUP_DIR="/custom/path"
./backend/scripts/backup-files.sh
```

---

## Restore Procedures

### ⚠️ CRITICAL: Before Restoring

1. **Stop the application:**
   ```bash
   cd /home/kristian/reporting
   docker-compose down
   ```

2. **Backup current state** (just in case):
   ```bash
   ./backend/scripts/backup-database.sh
   ./backend/scripts/backup-files.sh
   ```

3. **Verify backup file exists and is recent**

### Database Restore

```bash
# Start database container only
docker-compose up -d reporting-timescaledb

# Restore from encrypted backup
export ENCRYPTION_KEY="your-key"
./backend/scripts/restore-database.sh /var/backups/reporting/database/reporting_db_20250124_020000.sql.gz.gpg

# Restore from unencrypted backup
./backend/scripts/restore-database.sh /var/backups/reporting/database/reporting_db_20250124_020000.sql.gz
```

### File Restore

```bash
# Restore from encrypted backup
export ENCRYPTION_KEY="your-key"
./backend/scripts/restore-files.sh /var/backups/reporting/files/reporting_files_20250124_020000.tar.gz.gpg

# Restore from unencrypted backup
./backend/scripts/restore-files.sh /var/backups/reporting/files/reporting_files_20250124_020000.tar.gz
```

### Complete Restore

```bash
# 1. Stop application
docker-compose down

# 2. Restore database
export ENCRYPTION_KEY="your-key"
./backend/scripts/restore-database.sh /path/to/database/backup.sql.gz.gpg

# 3. Restore files
./backend/scripts/restore-files.sh /path/to/files/backup.tar.gz.gpg

# 4. Start application
docker-compose up -d

# 5. Verify application is working
curl http://localhost:8080/api/health
```

---

## Backup Verification

### Monthly Verification (Automated)

The verification script runs monthly via cron to ensure backups are valid.

### Manual Verification

```bash
# Verify latest backups
export ENCRYPTION_KEY="your-key"
./backend/scripts/verify-backup.sh

# Check verification report
cat /var/log/reporting-backup-verify.log
```

### What Gets Verified

- ✅ Backup files exist
- ✅ Backup files are not empty
- ✅ Backup files are recent (< 26 hours)
- ✅ Encrypted backups can be decrypted
- ✅ Backup sizes are reasonable

---

## Disaster Recovery

### Scenario 1: Database Corruption

**Symptoms:** Application errors, data inconsistencies

**Recovery Steps:**
1. Stop application
2. Restore database from latest backup
3. Verify data integrity
4. Start application
5. Test functionality

**Time:** 30-60 minutes

### Scenario 2: Server Failure

**Symptoms:** Server unresponsive, hardware failure

**Recovery Steps:**
1. Provision new server
2. Install Docker and dependencies
3. Deploy application
4. Restore database from off-site backup
5. Restore files from off-site backup
6. Update DNS/IP configuration
7. Start application

**Time:** 2-4 hours

### Scenario 3: Ransomware/Security Breach

**Symptoms:** Files encrypted, unauthorized access

**Recovery Steps:**
1. Isolate affected systems
2. Assess breach scope
3. Provision clean server
4. Deploy application from clean source
5. Restore from backup BEFORE breach date
6. Change all passwords and keys
7. Implement additional security measures
8. Notify affected parties

**Time:** 4-8 hours

### Scenario 4: Accidental Data Deletion

**Symptoms:** Missing data, deleted records

**Recovery Steps:**
1. Identify what was deleted and when
2. Find backup from before deletion
3. Restore to test environment
4. Extract needed data
5. Import to production
6. Verify data integrity

**Time:** 1-2 hours

---

## Off-Site Backup (Recommended)

### Option 1: Rsync to Remote Server

```bash
# Add to cron after local backup
30 2 * * * rsync -avz --delete /var/backups/reporting/ backup-server:/backups/reporting/
```

### Option 2: Cloud Storage (S3, Azure, etc.)

```bash
# Install AWS CLI
sudo apt-get install awscli

# Configure credentials
aws configure

# Add to cron
45 2 * * * aws s3 sync /var/backups/reporting/ s3://your-bucket/reporting-backups/
```

### Option 3: Network Attached Storage (NAS)

```bash
# Mount NAS
sudo mount -t nfs nas-server:/backups /mnt/nas-backups

# Add to fstab for automatic mounting
echo "nas-server:/backups /mnt/nas-backups nfs defaults 0 0" | sudo tee -a /etc/fstab

# Update backup location
export BACKUP_DIR="/mnt/nas-backups/reporting/database"
```

---

## Troubleshooting

### Backup Fails with "Container not found"

**Solution:**
```bash
# Check container name
docker ps -a | grep timescaledb

# Update container name in config
export CONTAINER_NAME="actual-container-name"
```

### Backup Fails with "Permission denied"

**Solution:**
```bash
# Fix backup directory permissions
sudo mkdir -p /var/backups/reporting/{database,files}
sudo chown -R $USER:$USER /var/backups/reporting
sudo chmod -R 755 /var/backups/reporting
```

### Restore Fails with "Decryption failed"

**Solution:**
```bash
# Verify encryption key is correct
export ENCRYPTION_KEY="your-key"

# Test decryption manually
echo "$ENCRYPTION_KEY" | gpg --batch --passphrase-fd 0 --decrypt backup.sql.gz.gpg > test.sql.gz
```

### Backup Files Too Large

**Solution:**
```bash
# Reduce retention period
export RETENTION_DAYS="14"

# Clean old backups manually
find /var/backups/reporting -name "*.sql.gz*" -mtime +14 -delete
```

### Cron Job Not Running

**Solution:**
```bash
# Check cron service
sudo systemctl status cron

# Check cron logs
sudo tail -f /var/log/syslog | grep CRON

# Verify crontab
sudo crontab -l
```

---

## Best Practices

### ✅ DO

- ✅ Enable encryption for all backups
- ✅ Store encryption key securely (password manager, vault)
- ✅ Keep backups off-site
- ✅ Test restores monthly
- ✅ Monitor backup logs
- ✅ Document recovery procedures
- ✅ Keep multiple backup versions (30 days)

### ❌ DON'T

- ❌ Store encryption key in backup scripts
- ❌ Keep only one backup copy
- ❌ Skip backup verification
- ❌ Ignore backup failures
- ❌ Store backups on same server only
- ❌ Use weak encryption keys
- ❌ Delete backups without testing restore

---

## Monitoring & Alerts

### Check Backup Status

```bash
# View recent backup logs
tail -100 /var/log/reporting-backup-db.log

# Check backup file ages
find /var/backups/reporting -name "*.sql.gz*" -mtime -1 -ls

# Verify backup sizes
du -sh /var/backups/reporting/*
```

### Set Up Email Alerts

Add to backup scripts:

```bash
# At end of backup script
if [ $? -eq 0 ]; then
    echo "Backup completed successfully" | mail -s "Backup Success" admin@company.com
else
    echo "Backup failed! Check logs." | mail -s "Backup FAILED" admin@company.com
fi
```

---

## Compliance

### ISO 27001 Requirements

- ✅ Automated daily backups
- ✅ Encrypted at rest
- ✅ 30-day retention minimum
- ✅ Off-site storage
- ✅ Monthly restore testing
- ✅ Documented procedures
- ✅ Audit trail (logs)

### GDPR Requirements

- ✅ Data can be restored (availability)
- ✅ Backups encrypted (security)
- ✅ Retention policy enforced
- ✅ Audit logs maintained

---

## Support

For backup-related issues:
1. Check logs: `/var/log/reporting-backup-*.log`
2. Verify configuration: `/etc/reporting-backup.conf`
3. Test manually: Run scripts with `-x` flag for debug output
4. Contact support with log files

---

**Last Updated:** 2025-01-24
**Version:** 1.0
