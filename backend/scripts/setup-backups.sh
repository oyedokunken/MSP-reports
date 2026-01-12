#!/bin/bash

################################################################################
# Backup Setup Script
# 
# Purpose: Interactive setup for automated backups
# Usage: sudo ./setup-backups.sh
################################################################################

set -e

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

echo -e "${BLUE}"
cat << "EOF"
╔═══════════════════════════════════════════════════════════════╗
║                                                               ║
║          MSP Reporting Platform - Backup Setup               ║
║                                                               ║
╚═══════════════════════════════════════════════════════════════╝
EOF
echo -e "${NC}"

# Check if running as root
if [ "$EUID" -ne 0 ]; then
    echo -e "${RED}Please run as root (sudo)${NC}"
    exit 1
fi

# Get current user (who ran sudo)
ACTUAL_USER="${SUDO_USER:-$USER}"
ACTUAL_HOME=$(eval echo ~$ACTUAL_USER)

echo -e "${GREEN}Setting up automated backups...${NC}\n"

# Step 1: Configuration
echo -e "${YELLOW}Step 1: Configuration${NC}"
echo "-----------------------------------"

read -p "Backup directory [/var/backups/reporting]: " BACKUP_BASE
BACKUP_BASE="${BACKUP_BASE:-/var/backups/reporting}"

read -p "Retention days [30]: " RETENTION
RETENTION="${RETENTION:-30}"

read -p "Application directory [${ACTUAL_HOME}/reporting/backend]: " APP_DIR
APP_DIR="${APP_DIR:-${ACTUAL_HOME}/reporting/backend}"

read -p "Enable encryption? (yes/no) [yes]: " ENABLE_ENCRYPTION
ENABLE_ENCRYPTION="${ENABLE_ENCRYPTION:-yes}"

if [[ "$ENABLE_ENCRYPTION" =~ ^[Yy] ]]; then
    read -sp "Enter encryption key (min 16 characters): " ENCRYPTION_KEY
    echo
    if [ ${#ENCRYPTION_KEY} -lt 16 ]; then
        echo -e "${RED}Encryption key too short!${NC}"
        exit 1
    fi
else
    ENCRYPTION_KEY=""
fi

# Step 2: Create directories
echo -e "\n${YELLOW}Step 2: Creating directories${NC}"
echo "-----------------------------------"

mkdir -p "${BACKUP_BASE}/database"
mkdir -p "${BACKUP_BASE}/files"
mkdir -p /var/log

echo -e "${GREEN}✓ Directories created${NC}"

# Step 3: Create configuration file
echo -e "\n${YELLOW}Step 3: Creating configuration${NC}"
echo "-----------------------------------"

cat > /etc/reporting-backup.conf << EOF
# Reporting Platform Backup Configuration
# Generated: $(date)

export BACKUP_DIR="${BACKUP_BASE}/database"
export FILES_BACKUP_DIR="${BACKUP_BASE}/files"
export RETENTION_DAYS="${RETENTION}"
export CONTAINER_NAME="reporting-timescaledb"
export DB_NAME="reporting"
export DB_USER="reporting_user"
export APP_DIR="${APP_DIR}"
EOF

if [ -n "$ENCRYPTION_KEY" ]; then
    echo "export ENCRYPTION_KEY=\"${ENCRYPTION_KEY}\"" >> /etc/reporting-backup.conf
fi

chmod 600 /etc/reporting-backup.conf
echo -e "${GREEN}✓ Configuration saved to /etc/reporting-backup.conf${NC}"

# Step 4: Set up cron jobs
echo -e "\n${YELLOW}Step 4: Setting up cron jobs${NC}"
echo "-----------------------------------"

# Create temporary cron file
CRON_FILE=$(mktemp)

# Add existing crontab (if any)
crontab -l > "$CRON_FILE" 2>/dev/null || true

# Remove old reporting backup entries
sed -i '/reporting.*backup/d' "$CRON_FILE"

# Add new entries
cat >> "$CRON_FILE" << EOF

# MSP Reporting Platform - Automated Backups
SHELL=/bin/bash
BASH_ENV=/etc/reporting-backup.conf

# Database backup - Daily at 2:00 AM
0 2 * * * ${APP_DIR}/scripts/backup-database.sh >> /var/log/reporting-backup-db.log 2>&1

# File backup - Daily at 2:15 AM
15 2 * * * ${APP_DIR}/scripts/backup-files.sh >> /var/log/reporting-backup-files.log 2>&1

# Verify backups - Monthly on 1st at 3:00 AM
0 3 1 * * ${APP_DIR}/scripts/verify-backup.sh >> /var/log/reporting-backup-verify.log 2>&1

EOF

# Install new crontab
crontab "$CRON_FILE"
rm "$CRON_FILE"

echo -e "${GREEN}✓ Cron jobs installed${NC}"

# Step 5: Create log files
echo -e "\n${YELLOW}Step 5: Creating log files${NC}"
echo "-----------------------------------"

touch /var/log/reporting-backup-db.log
touch /var/log/reporting-backup-files.log
touch /var/log/reporting-backup-verify.log
chmod 644 /var/log/reporting-backup-*.log

echo -e "${GREEN}✓ Log files created${NC}"

# Step 6: Test backup
echo -e "\n${YELLOW}Step 6: Testing backup${NC}"
echo "-----------------------------------"

read -p "Run test backup now? (yes/no) [yes]: " RUN_TEST
RUN_TEST="${RUN_TEST:-yes}"

if [[ "$RUN_TEST" =~ ^[Yy] ]]; then
    echo "Running database backup..."
    source /etc/reporting-backup.conf
    "${APP_DIR}/scripts/backup-database.sh"
    
    echo -e "\nRunning file backup..."
    "${APP_DIR}/scripts/backup-files.sh"
    
    echo -e "\n${GREEN}✓ Test backups completed${NC}"
else
    echo -e "${YELLOW}Skipping test backup${NC}"
fi

# Summary
echo -e "\n${GREEN}"
cat << "EOF"
╔═══════════════════════════════════════════════════════════════╗
║                                                               ║
║                    Setup Complete! ✓                          ║
║                                                               ║
╚═══════════════════════════════════════════════════════════════╝
EOF
echo -e "${NC}"

cat << EOF

${BLUE}Configuration Summary:${NC}
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
  Backup Location:    ${BACKUP_BASE}
  Retention:          ${RETENTION} days
  Encryption:         $([ -n "$ENCRYPTION_KEY" ] && echo "Enabled ✓" || echo "Disabled")
  Schedule:           Daily at 2:00 AM
  Verification:       Monthly on 1st at 3:00 AM

${BLUE}Next Steps:${NC}
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
  1. Review configuration: /etc/reporting-backup.conf
  2. Check cron jobs: crontab -l
  3. Monitor logs: tail -f /var/log/reporting-backup-*.log
  4. Read full guide: ${APP_DIR}/BACKUP_GUIDE.md

${BLUE}Important:${NC}
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
  $([ -n "$ENCRYPTION_KEY" ] && echo "⚠️  Save your encryption key securely!" || echo "⚠️  Consider enabling encryption for security!")
  ⚠️  Set up off-site backups for disaster recovery
  ⚠️  Test restore procedures monthly

EOF

echo -e "${GREEN}Backup system is now active!${NC}\n"
