#!/bin/bash

################################################################################
# Database Backup Script
# 
# Purpose: Create encrypted daily backups of PostgreSQL/TimescaleDB database
# Requirements: GPG for encryption, Docker for database access
# Schedule: Run daily at 2am via cron
################################################################################

set -e  # Exit on error
set -u  # Exit on undefined variable

# Configuration
BACKUP_DIR="${BACKUP_DIR:-/var/backups/reporting/database}"
RETENTION_DAYS="${RETENTION_DAYS:-30}"
CONTAINER_NAME="${CONTAINER_NAME:-reporting-timescaledb}"
DB_NAME="${DB_NAME:-reporting}"
DB_USER="${DB_USER:-reporting_user}"
ENCRYPTION_KEY="${ENCRYPTION_KEY:-}"
TIMESTAMP=$(date +"%Y%m%d_%H%M%S")
BACKUP_FILE="reporting_db_${TIMESTAMP}.sql"
COMPRESSED_FILE="${BACKUP_FILE}.gz"
ENCRYPTED_FILE="${COMPRESSED_FILE}.gpg"

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Logging
log() {
    echo -e "${GREEN}[$(date +'%Y-%m-%d %H:%M:%S')]${NC} $1"
}

error() {
    echo -e "${RED}[$(date +'%Y-%m-%d %H:%M:%S')] ERROR:${NC} $1" >&2
}

warning() {
    echo -e "${YELLOW}[$(date +'%Y-%m-%d %H:%M:%S')] WARNING:${NC} $1"
}

# Check prerequisites
check_prerequisites() {
    log "Checking prerequisites..."
    
    # Check if Docker is running
    if ! docker ps >/dev/null 2>&1; then
        error "Docker is not running or not accessible"
        exit 1
    fi
    
    # Check if container exists
    if ! docker ps -a --format '{{.Names}}' | grep -q "^${CONTAINER_NAME}$"; then
        error "Container ${CONTAINER_NAME} not found"
        exit 1
    fi
    
    # Check if container is running
    if ! docker ps --format '{{.Names}}' | grep -q "^${CONTAINER_NAME}$"; then
        error "Container ${CONTAINER_NAME} is not running"
        exit 1
    fi
    
    # Check if GPG is available for encryption
    if [ -n "${ENCRYPTION_KEY}" ] && ! command -v gpg >/dev/null 2>&1; then
        error "GPG not found but encryption is enabled"
        exit 1
    fi
    
    log "Prerequisites check passed"
}

# Create backup directory
create_backup_dir() {
    if [ ! -d "${BACKUP_DIR}" ]; then
        log "Creating backup directory: ${BACKUP_DIR}"
        mkdir -p "${BACKUP_DIR}"
    fi
}

# Perform database backup
backup_database() {
    log "Starting database backup..."
    
    local backup_path="${BACKUP_DIR}/${BACKUP_FILE}"
    
    # Dump database
    log "Dumping database ${DB_NAME}..."
    if docker exec "${CONTAINER_NAME}" pg_dump -U "${DB_USER}" "${DB_NAME}" > "${backup_path}"; then
        log "Database dump completed: ${backup_path}"
    else
        error "Database dump failed"
        exit 1
    fi
    
    # Get backup size
    local size=$(du -h "${backup_path}" | cut -f1)
    log "Backup size: ${size}"
}

# Compress backup
compress_backup() {
    log "Compressing backup..."
    
    local backup_path="${BACKUP_DIR}/${BACKUP_FILE}"
    local compressed_path="${BACKUP_DIR}/${COMPRESSED_FILE}"
    
    if gzip -c "${backup_path}" > "${compressed_path}"; then
        log "Compression completed: ${COMPRESSED_FILE}"
        rm "${backup_path}"
        
        # Get compressed size
        local size=$(du -h "${compressed_path}" | cut -f1)
        local original_size=$(du -h "${backup_path}" 2>/dev/null | cut -f1 || echo "N/A")
        log "Compressed size: ${size}"
    else
        error "Compression failed"
        exit 1
    fi
}

# Encrypt backup
encrypt_backup() {
    if [ -z "${ENCRYPTION_KEY}" ]; then
        log "Encryption disabled (no key provided)"
        return 0
    fi
    
    log "Encrypting backup..."
    
    local compressed_path="${BACKUP_DIR}/${COMPRESSED_FILE}"
    local encrypted_path="${BACKUP_DIR}/${ENCRYPTED_FILE}"
    
    # Encrypt using GPG with passphrase
    if echo "${ENCRYPTION_KEY}" | gpg --batch --yes --passphrase-fd 0 --symmetric --cipher-algo AES256 -o "${encrypted_path}" "${compressed_path}"; then
        log "Encryption completed: ${ENCRYPTED_FILE}"
        rm "${compressed_path}"
        
        # Get encrypted size
        local size=$(du -h "${encrypted_path}" | cut -f1)
        log "Encrypted size: ${size}"
    else
        error "Encryption failed"
        exit 1
    fi
}

# Verify backup
verify_backup() {
    log "Verifying backup integrity..."
    
    local final_file
    if [ -n "${ENCRYPTION_KEY}" ]; then
        final_file="${BACKUP_DIR}/${ENCRYPTED_FILE}"
    else
        final_file="${BACKUP_DIR}/${COMPRESSED_FILE}"
    fi
    
    if [ ! -f "${final_file}" ]; then
        error "Backup file not found: ${final_file}"
        exit 1
    fi
    
    if [ ! -s "${final_file}" ]; then
        error "Backup file is empty: ${final_file}"
        exit 1
    fi
    
    log "Backup verification passed"
}

# Clean old backups
cleanup_old_backups() {
    log "Cleaning up backups older than ${RETENTION_DAYS} days..."
    
    local deleted_count=0
    
    # Find and delete old backups
    while IFS= read -r file; do
        if [ -n "${file}" ]; then
            log "Deleting old backup: $(basename "${file}")"
            rm "${file}"
            ((deleted_count++))
        fi
    done < <(find "${BACKUP_DIR}" -name "reporting_db_*.sql.gz*" -type f -mtime +${RETENTION_DAYS})
    
    if [ ${deleted_count} -gt 0 ]; then
        log "Deleted ${deleted_count} old backup(s)"
    else
        log "No old backups to delete"
    fi
}

# Generate backup report
generate_report() {
    log "Generating backup report..."
    
    local final_file
    if [ -n "${ENCRYPTION_KEY}" ]; then
        final_file="${BACKUP_DIR}/${ENCRYPTED_FILE}"
    else
        final_file="${BACKUP_DIR}/${COMPRESSED_FILE}"
    fi
    
    local size=$(du -h "${final_file}" | cut -f1)
    local backup_count=$(find "${BACKUP_DIR}" -name "reporting_db_*.sql.gz*" -type f | wc -l)
    local total_size=$(du -sh "${BACKUP_DIR}" | cut -f1)
    
    cat << EOF

================================================================================
                          BACKUP REPORT
================================================================================
Timestamp:        $(date +'%Y-%m-%d %H:%M:%S')
Database:         ${DB_NAME}
Backup File:      $(basename "${final_file}")
Backup Size:      ${size}
Encrypted:        $([ -n "${ENCRYPTION_KEY}" ] && echo "Yes" || echo "No")
Retention Days:   ${RETENTION_DAYS}
Total Backups:    ${backup_count}
Total Size:       ${total_size}
Backup Location:  ${BACKUP_DIR}
================================================================================

EOF
}

# Main execution
main() {
    log "========================================="
    log "Starting Database Backup Process"
    log "========================================="
    
    check_prerequisites
    create_backup_dir
    backup_database
    compress_backup
    encrypt_backup
    verify_backup
    cleanup_old_backups
    generate_report
    
    log "========================================="
    log "Backup completed successfully!"
    log "========================================="
}

# Run main function
main "$@"
