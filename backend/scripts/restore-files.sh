#!/bin/bash

################################################################################
# File Restore Script
# 
# Purpose: Restore application files from backup
# Requirements: tar, GPG for decryption
# Usage: ./restore-files.sh <backup_file>
################################################################################

set -e  # Exit on error
set -u  # Exit on undefined variable

# Configuration
APP_DIR="${APP_DIR:-/home/kristian/reporting/backend}"
ENCRYPTION_KEY="${ENCRYPTION_KEY:-}"

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

# Usage information
usage() {
    cat << EOF
Usage: $0 <backup_file>

Restore files from backup.

Arguments:
    backup_file    Path to backup file (.tar.gz or .tar.gz.gpg)

Environment Variables:
    APP_DIR           Application directory (default: /home/kristian/reporting/backend)
    ENCRYPTION_KEY    GPG encryption key (if backup is encrypted)

Examples:
    # Restore from unencrypted backup
    $0 /var/backups/reporting/files/reporting_files_20250124_020000.tar.gz

    # Restore from encrypted backup
    ENCRYPTION_KEY="your-key" $0 /var/backups/reporting/files/reporting_files_20250124_020000.tar.gz.gpg

EOF
    exit 1
}

# Check arguments
if [ $# -eq 0 ]; then
    error "No backup file specified"
    usage
fi

BACKUP_FILE="$1"

# Check prerequisites
check_prerequisites() {
    log "Checking prerequisites..."
    
    # Check if backup file exists
    if [ ! -f "${BACKUP_FILE}" ]; then
        error "Backup file not found: ${BACKUP_FILE}"
        exit 1
    fi
    
    # Check if application directory exists
    if [ ! -d "${APP_DIR}" ]; then
        error "Application directory not found: ${APP_DIR}"
        exit 1
    fi
    
    # Check if tar is available
    if ! command -v tar >/dev/null 2>&1; then
        error "tar command not found"
        exit 1
    fi
    
    # Check if GPG is available if file is encrypted
    if [[ "${BACKUP_FILE}" == *.gpg ]]; then
        if ! command -v gpg >/dev/null 2>&1; then
            error "GPG not found but backup is encrypted"
            exit 1
        fi
        if [ -z "${ENCRYPTION_KEY}" ]; then
            error "ENCRYPTION_KEY not set for encrypted backup"
            exit 1
        fi
    fi
    
    log "Prerequisites check passed"
}

# Confirm restore
confirm_restore() {
    warning "========================================="
    warning "WARNING: FILE RESTORE"
    warning "========================================="
    warning "This will REPLACE existing files!"
    warning "Application: ${APP_DIR}"
    warning "Backup: $(basename "${BACKUP_FILE}")"
    warning "========================================="
    
    read -p "Are you sure you want to continue? (yes/no): " -r
    echo
    if [[ ! $REPLY =~ ^[Yy][Ee][Ss]$ ]]; then
        log "Restore cancelled by user"
        exit 0
    fi
}

# Prepare backup file
prepare_backup() {
    log "Preparing backup file..."
    
    local temp_file=$(mktemp)
    
    # Decrypt if needed
    if [[ "${BACKUP_FILE}" == *.gpg ]]; then
        log "Decrypting backup..."
        if echo "${ENCRYPTION_KEY}" | gpg --batch --yes --passphrase-fd 0 --decrypt "${BACKUP_FILE}" > "${temp_file}"; then
            log "Decryption completed"
            BACKUP_FILE="${temp_file}"
        else
            error "Decryption failed"
            rm "${temp_file}"
            exit 1
        fi
    else
        cp "${BACKUP_FILE}" "${temp_file}"
        BACKUP_FILE="${temp_file}"
    fi
    
    log "Backup file prepared: ${BACKUP_FILE}"
}

# Backup current files
backup_current_files() {
    log "Creating backup of current files..."
    
    local backup_timestamp=$(date +"%Y%m%d_%H%M%S")
    local current_backup="${APP_DIR}/../backup_before_restore_${backup_timestamp}.tar.gz"
    
    if tar -czf "${current_backup}" -C "${APP_DIR}" storage .env 2>/dev/null; then
        log "Current files backed up to: ${current_backup}"
    else
        warning "Failed to backup current files (continuing anyway)"
    fi
}

# Restore files
restore_files() {
    log "Restoring files..."
    
    local temp_dir=$(mktemp -d)
    
    # Extract backup
    log "Extracting backup archive..."
    if tar -xzf "${BACKUP_FILE}" -C "${temp_dir}"; then
        log "Archive extracted"
    else
        error "Failed to extract archive"
        rm -rf "${temp_dir}"
        exit 1
    fi
    
    # Restore storage directory
    if [ -d "${temp_dir}/reporting/storage" ]; then
        log "Restoring storage directory..."
        rm -rf "${APP_DIR}/storage"
        cp -r "${temp_dir}/reporting/storage" "${APP_DIR}/"
        log "Storage directory restored"
    else
        warning "Storage directory not found in backup"
    fi
    
    # Restore .env file
    if [ -f "${temp_dir}/reporting/.env" ]; then
        log "Restoring .env file..."
        cp "${temp_dir}/reporting/.env" "${APP_DIR}/"
        log ".env file restored"
    else
        warning ".env file not found in backup"
    fi
    
    # Restore docker-compose.yml
    if [ -f "${temp_dir}/reporting/docker-compose.yml" ]; then
        log "Restoring docker-compose.yml..."
        cp "${temp_dir}/reporting/docker-compose.yml" "${APP_DIR}/../"
        log "docker-compose.yml restored"
    else
        warning "docker-compose.yml not found in backup"
    fi
    
    # Cleanup temp directory
    rm -rf "${temp_dir}"
}

# Fix permissions
fix_permissions() {
    log "Fixing file permissions..."
    
    # Fix storage permissions
    if [ -d "${APP_DIR}/storage" ]; then
        chmod -R 775 "${APP_DIR}/storage"
        log "Storage permissions fixed"
    fi
    
    # Fix .env permissions
    if [ -f "${APP_DIR}/.env" ]; then
        chmod 600 "${APP_DIR}/.env"
        log ".env permissions fixed"
    fi
}

# Verify restore
verify_restore() {
    log "Verifying restore..."
    
    # Check if storage directory exists
    if [ ! -d "${APP_DIR}/storage" ]; then
        error "Storage directory not found after restore"
        exit 1
    fi
    
    # Check if .env file exists
    if [ ! -f "${APP_DIR}/.env" ]; then
        warning ".env file not found after restore"
    fi
    
    log "Restore verification passed"
}

# Cleanup
cleanup() {
    if [ -n "${BACKUP_FILE}" ] && [ -f "${BACKUP_FILE}" ]; then
        rm -f "${BACKUP_FILE}"
    fi
}

# Generate restore report
generate_report() {
    log "Generating restore report..."
    
    local storage_size=$(du -sh "${APP_DIR}/storage" 2>/dev/null | cut -f1 || echo "N/A")
    local file_count=$(find "${APP_DIR}/storage" -type f 2>/dev/null | wc -l || echo "N/A")
    
    cat << EOF

================================================================================
                          FILE RESTORE REPORT
================================================================================
Timestamp:        $(date +'%Y-%m-%d %H:%M:%S')
Source Backup:    $(basename "$1")
Storage Size:     ${storage_size}
Files Restored:   ${file_count}
Status:           SUCCESS
================================================================================

EOF
}

# Main execution
main() {
    log "========================================="
    log "Starting File Restore Process"
    log "========================================="
    
    check_prerequisites
    confirm_restore
    prepare_backup
    backup_current_files
    restore_files
    fix_permissions
    verify_restore
    cleanup
    generate_report "$1"
    
    log "========================================="
    log "Restore completed successfully!"
    log "========================================="
}

# Trap cleanup on exit
trap cleanup EXIT

# Run main function
main "$@"
