#!/bin/bash

################################################################################
# Backup Verification Script
# 
# Purpose: Verify backup integrity and test restore capability
# Requirements: All backup/restore scripts
# Schedule: Run monthly for compliance
################################################################################

set -e  # Exit on error
set -u  # Exit on undefined variable

# Configuration
DB_BACKUP_DIR="${DB_BACKUP_DIR:-/var/backups/reporting/database}"
FILES_BACKUP_DIR="${FILES_BACKUP_DIR:-/var/backups/reporting/files}"
TEST_CONTAINER="${TEST_CONTAINER:-reporting-test-db}"
ENCRYPTION_KEY="${ENCRYPTION_KEY:-}"

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
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

info() {
    echo -e "${BLUE}[$(date +'%Y-%m-%d %H:%M:%S')] INFO:${NC} $1"
}

# Check if backups exist
check_backups_exist() {
    log "Checking for backup files..."
    
    # Check database backups
    local db_backup_count=$(find "${DB_BACKUP_DIR}" -name "reporting_db_*.sql.gz*" -type f 2>/dev/null | wc -l)
    if [ ${db_backup_count} -eq 0 ]; then
        error "No database backups found in ${DB_BACKUP_DIR}"
        return 1
    fi
    log "Found ${db_backup_count} database backup(s)"
    
    # Check file backups
    local files_backup_count=$(find "${FILES_BACKUP_DIR}" -name "reporting_files_*.tar.gz*" -type f 2>/dev/null | wc -l)
    if [ ${files_backup_count} -eq 0 ]; then
        error "No file backups found in ${FILES_BACKUP_DIR}"
        return 1
    fi
    log "Found ${files_backup_count} file backup(s)"
    
    return 0
}

# Get latest backups
get_latest_backups() {
    log "Finding latest backups..."
    
    # Get latest database backup
    LATEST_DB_BACKUP=$(find "${DB_BACKUP_DIR}" -name "reporting_db_*.sql.gz*" -type f -printf '%T@ %p\n' 2>/dev/null | sort -rn | head -1 | cut -d' ' -f2-)
    if [ -z "${LATEST_DB_BACKUP}" ]; then
        error "No database backup found"
        return 1
    fi
    log "Latest database backup: $(basename "${LATEST_DB_BACKUP}")"
    
    # Get latest file backup
    LATEST_FILES_BACKUP=$(find "${FILES_BACKUP_DIR}" -name "reporting_files_*.tar.gz*" -type f -printf '%T@ %p\n' 2>/dev/null | sort -rn | head -1 | cut -d' ' -f2-)
    if [ -z "${LATEST_FILES_BACKUP}" ]; then
        error "No file backup found"
        return 1
    fi
    log "Latest file backup: $(basename "${LATEST_FILES_BACKUP}")"
    
    return 0
}

# Verify backup file integrity
verify_file_integrity() {
    log "Verifying backup file integrity..."
    
    local passed=0
    local failed=0
    
    # Check database backup
    info "Checking database backup..."
    if [ -f "${LATEST_DB_BACKUP}" ] && [ -s "${LATEST_DB_BACKUP}" ]; then
        log "✓ Database backup file exists and is not empty"
        ((passed++))
    else
        error "✗ Database backup file is missing or empty"
        ((failed++))
    fi
    
    # Check file backup
    info "Checking file backup..."
    if [ -f "${LATEST_FILES_BACKUP}" ] && [ -s "${LATEST_FILES_BACKUP}" ]; then
        log "✓ File backup exists and is not empty"
        ((passed++))
    else
        error "✗ File backup is missing or empty"
        ((failed++))
    fi
    
    # Check if encrypted backups can be decrypted
    if [[ "${LATEST_DB_BACKUP}" == *.gpg ]]; then
        info "Testing database backup decryption..."
        if [ -z "${ENCRYPTION_KEY}" ]; then
            error "✗ Backup is encrypted but no ENCRYPTION_KEY provided"
            ((failed++))
        else
            if echo "${ENCRYPTION_KEY}" | gpg --batch --yes --passphrase-fd 0 --decrypt "${LATEST_DB_BACKUP}" > /dev/null 2>&1; then
                log "✓ Database backup decryption successful"
                ((passed++))
            else
                error "✗ Database backup decryption failed"
                ((failed++))
            fi
        fi
    fi
    
    if [[ "${LATEST_FILES_BACKUP}" == *.gpg ]]; then
        info "Testing file backup decryption..."
        if [ -z "${ENCRYPTION_KEY}" ]; then
            error "✗ Backup is encrypted but no ENCRYPTION_KEY provided"
            ((failed++))
        else
            if echo "${ENCRYPTION_KEY}" | gpg --batch --yes --passphrase-fd 0 --decrypt "${LATEST_FILES_BACKUP}" > /dev/null 2>&1; then
                log "✓ File backup decryption successful"
                ((passed++))
            else
                error "✗ File backup decryption failed"
                ((failed++))
            fi
        fi
    fi
    
    log "Integrity check: ${passed} passed, ${failed} failed"
    return ${failed}
}

# Check backup age
check_backup_age() {
    log "Checking backup age..."
    
    local max_age_hours=26  # 24 hours + 2 hour grace period
    local current_time=$(date +%s)
    
    # Check database backup age
    local db_backup_time=$(stat -c %Y "${LATEST_DB_BACKUP}")
    local db_age_hours=$(( (current_time - db_backup_time) / 3600 ))
    
    info "Database backup age: ${db_age_hours} hours"
    if [ ${db_age_hours} -gt ${max_age_hours} ]; then
        warning "Database backup is older than ${max_age_hours} hours!"
    else
        log "✓ Database backup is recent"
    fi
    
    # Check file backup age
    local files_backup_time=$(stat -c %Y "${LATEST_FILES_BACKUP}")
    local files_age_hours=$(( (current_time - files_backup_time) / 3600 ))
    
    info "File backup age: ${files_age_hours} hours"
    if [ ${files_age_hours} -gt ${max_age_hours} ]; then
        warning "File backup is older than ${max_age_hours} hours!"
    else
        log "✓ File backup is recent"
    fi
}

# Check backup sizes
check_backup_sizes() {
    log "Checking backup sizes..."
    
    local db_size=$(du -h "${LATEST_DB_BACKUP}" | cut -f1)
    local files_size=$(du -h "${LATEST_FILES_BACKUP}" | cut -f1)
    
    log "Database backup size: ${db_size}"
    log "File backup size: ${files_size}"
    
    # Check if sizes are reasonable (not too small)
    local db_bytes=$(stat -c %s "${LATEST_DB_BACKUP}")
    local files_bytes=$(stat -c %s "${LATEST_FILES_BACKUP}")
    
    if [ ${db_bytes} -lt 1024 ]; then
        error "Database backup suspiciously small (< 1KB)"
        return 1
    fi
    
    if [ ${files_bytes} -lt 1024 ]; then
        error "File backup suspiciously small (< 1KB)"
        return 1
    fi
    
    log "✓ Backup sizes look reasonable"
    return 0
}

# Generate verification report
generate_report() {
    local status=$1
    
    log "Generating verification report..."
    
    local db_size=$(du -h "${LATEST_DB_BACKUP}" 2>/dev/null | cut -f1 || echo "N/A")
    local files_size=$(du -h "${LATEST_FILES_BACKUP}" 2>/dev/null | cut -f1 || echo "N/A")
    local db_age=$(stat -c %Y "${LATEST_DB_BACKUP}" 2>/dev/null || echo "0")
    local files_age=$(stat -c %Y "${LATEST_FILES_BACKUP}" 2>/dev/null || echo "0")
    local current_time=$(date +%s)
    local db_age_hours=$(( (current_time - db_age) / 3600 ))
    local files_age_hours=$(( (current_time - files_age) / 3600 ))
    
    cat << EOF

================================================================================
                     BACKUP VERIFICATION REPORT
================================================================================
Verification Date:    $(date +'%Y-%m-%d %H:%M:%S')
Status:               $([ ${status} -eq 0 ] && echo "PASSED ✓" || echo "FAILED ✗")

DATABASE BACKUP:
  File:               $(basename "${LATEST_DB_BACKUP}")
  Size:               ${db_size}
  Age:                ${db_age_hours} hours
  Encrypted:          $([ "${LATEST_DB_BACKUP}" == *.gpg ] && echo "Yes" || echo "No")

FILE BACKUP:
  File:               $(basename "${LATEST_FILES_BACKUP}")
  Size:               ${files_size}
  Age:                ${files_age_hours} hours
  Encrypted:          $([ "${LATEST_FILES_BACKUP}" == *.gpg ] && echo "Yes" || echo "No")

RECOMMENDATIONS:
  - Backups should be tested monthly
  - Verify restore procedures work
  - Monitor backup age (should be < 26 hours)
  - Keep backups off-site for disaster recovery
================================================================================

EOF
}

# Main execution
main() {
    log "========================================="
    log "Starting Backup Verification"
    log "========================================="
    
    local exit_code=0
    
    if ! check_backups_exist; then
        exit_code=1
    fi
    
    if ! get_latest_backups; then
        exit_code=1
    fi
    
    if ! verify_file_integrity; then
        exit_code=1
    fi
    
    check_backup_age
    
    if ! check_backup_sizes; then
        exit_code=1
    fi
    
    generate_report ${exit_code}
    
    if [ ${exit_code} -eq 0 ]; then
        log "========================================="
        log "Verification PASSED ✓"
        log "========================================="
    else
        error "========================================="
        error "Verification FAILED ✗"
        error "========================================="
    fi
    
    exit ${exit_code}
}

# Run main function
main "$@"
