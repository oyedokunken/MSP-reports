#!/bin/bash

################################################################################
# File Backup Script
# 
# Purpose: Backup application files (reports, uploads, configs)
# Requirements: tar, GPG for encryption
# Schedule: Run daily at 2am via cron (after database backup)
################################################################################

set -e  # Exit on error
set -u  # Exit on undefined variable

# Configuration
BACKUP_DIR="${BACKUP_DIR:-/var/backups/reporting/files}"
RETENTION_DAYS="${RETENTION_DAYS:-30}"
APP_DIR="${APP_DIR:-/home/kristian/reporting/backend}"
STORAGE_DIR="${APP_DIR}/storage"
ENV_FILE="${APP_DIR}/.env"
DOCKER_COMPOSE_FILE="${APP_DIR}/../docker-compose.yml"
ENCRYPTION_KEY="${ENCRYPTION_KEY:-}"
TIMESTAMP=$(date +"%Y%m%d_%H%M%S")
BACKUP_FILE="reporting_files_${TIMESTAMP}.tar.gz"
ENCRYPTED_FILE="${BACKUP_FILE}.gpg"

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
    
    # Check if storage directory exists
    if [ ! -d "${STORAGE_DIR}" ]; then
        error "Storage directory not found: ${STORAGE_DIR}"
        exit 1
    fi
    
    # Check if tar is available
    if ! command -v tar >/dev/null 2>&1; then
        error "tar command not found"
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

# Backup files
backup_files() {
    log "Starting file backup..."
    
    local backup_path="${BACKUP_DIR}/${BACKUP_FILE}"
    local temp_dir=$(mktemp -d)
    
    # Create temporary structure
    mkdir -p "${temp_dir}/reporting"
    
    # Copy storage directory
    log "Backing up storage directory..."
    if [ -d "${STORAGE_DIR}" ]; then
        cp -r "${STORAGE_DIR}" "${temp_dir}/reporting/"
        log "Storage directory backed up"
    else
        warning "Storage directory not found, skipping"
    fi
    
    # Copy .env file
    log "Backing up configuration files..."
    if [ -f "${ENV_FILE}" ]; then
        cp "${ENV_FILE}" "${temp_dir}/reporting/"
        log ".env file backed up"
    else
        warning ".env file not found, skipping"
    fi
    
    # Copy docker-compose.yml
    if [ -f "${DOCKER_COMPOSE_FILE}" ]; then
        cp "${DOCKER_COMPOSE_FILE}" "${temp_dir}/reporting/"
        log "docker-compose.yml backed up"
    else
        warning "docker-compose.yml not found, skipping"
    fi
    
    # Create tarball
    log "Creating compressed archive..."
    if tar -czf "${backup_path}" -C "${temp_dir}" reporting; then
        log "Archive created: ${BACKUP_FILE}"
        
        # Get backup size
        local size=$(du -h "${backup_path}" | cut -f1)
        log "Backup size: ${size}"
    else
        error "Failed to create archive"
        rm -rf "${temp_dir}"
        exit 1
    fi
    
    # Cleanup temp directory
    rm -rf "${temp_dir}"
}

# Encrypt backup
encrypt_backup() {
    if [ -z "${ENCRYPTION_KEY}" ]; then
        log "Encryption disabled (no key provided)"
        return 0
    fi
    
    log "Encrypting backup..."
    
    local backup_path="${BACKUP_DIR}/${BACKUP_FILE}"
    local encrypted_path="${BACKUP_DIR}/${ENCRYPTED_FILE}"
    
    # Encrypt using GPG with passphrase
    if echo "${ENCRYPTION_KEY}" | gpg --batch --yes --passphrase-fd 0 --symmetric --cipher-algo AES256 -o "${encrypted_path}" "${backup_path}"; then
        log "Encryption completed: ${ENCRYPTED_FILE}"
        rm "${backup_path}"
        
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
        final_file="${BACKUP_DIR}/${BACKUP_FILE}"
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
    done < <(find "${BACKUP_DIR}" -name "reporting_files_*.tar.gz*" -type f -mtime +${RETENTION_DAYS})
    
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
        final_file="${BACKUP_DIR}/${BACKUP_FILE}"
    fi
    
    local size=$(du -h "${final_file}" | cut -f1)
    local backup_count=$(find "${BACKUP_DIR}" -name "reporting_files_*.tar.gz*" -type f | wc -l)
    local total_size=$(du -sh "${BACKUP_DIR}" | cut -f1)
    
    cat << EOF

================================================================================
                          FILE BACKUP REPORT
================================================================================
Timestamp:        $(date +'%Y-%m-%d %H:%M:%S')
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
    log "Starting File Backup Process"
    log "========================================="
    
    check_prerequisites
    create_backup_dir
    backup_files
    encrypt_backup
    verify_backup
    cleanup_old_backups
    generate_report
    
    log "========================================="
    log "File backup completed successfully!"
    log "========================================="
}

# Run main function
main "$@"
