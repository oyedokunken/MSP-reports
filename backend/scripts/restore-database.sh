#!/bin/bash

################################################################################
# Database Restore Script
# 
# Purpose: Restore PostgreSQL/TimescaleDB database from backup
# Requirements: GPG for decryption, Docker for database access
# Usage: ./restore-database.sh <backup_file>
################################################################################

set -e  # Exit on error
set -u  # Exit on undefined variable

# Configuration
CONTAINER_NAME="${CONTAINER_NAME:-reporting-timescaledb}"
DB_NAME="${DB_NAME:-reporting}"
DB_USER="${DB_USER:-reporting_user}"
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

Restore database from backup file.

Arguments:
    backup_file    Path to backup file (.sql, .sql.gz, or .sql.gz.gpg)

Environment Variables:
    CONTAINER_NAME    Docker container name (default: reporting-timescaledb)
    DB_NAME           Database name (default: reporting)
    DB_USER           Database user (default: reporting_user)
    ENCRYPTION_KEY    GPG encryption key (if backup is encrypted)

Examples:
    # Restore from unencrypted backup
    $0 /var/backups/reporting/database/reporting_db_20250124_020000.sql.gz

    # Restore from encrypted backup
    ENCRYPTION_KEY="your-key" $0 /var/backups/reporting/database/reporting_db_20250124_020000.sql.gz.gpg

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
    warning "WARNING: DATABASE RESTORE"
    warning "========================================="
    warning "This will REPLACE the current database!"
    warning "Database: ${DB_NAME}"
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
    
    # Decompress if needed
    if [[ "${BACKUP_FILE}" == *.gz ]]; then
        log "Decompressing backup..."
        local decompressed=$(mktemp)
        if gunzip -c "${BACKUP_FILE}" > "${decompressed}"; then
            log "Decompression completed"
            rm "${BACKUP_FILE}"
            BACKUP_FILE="${decompressed}"
        else
            error "Decompression failed"
            rm "${BACKUP_FILE}" "${decompressed}"
            exit 1
        fi
    fi
    
    log "Backup file prepared: ${BACKUP_FILE}"
}

# Drop existing database connections
drop_connections() {
    log "Dropping existing database connections..."
    
    docker exec "${CONTAINER_NAME}" psql -U "${DB_USER}" -d postgres -c "
        SELECT pg_terminate_backend(pg_stat_activity.pid)
        FROM pg_stat_activity
        WHERE pg_stat_activity.datname = '${DB_NAME}'
        AND pid <> pg_backend_pid();
    " >/dev/null 2>&1 || true
    
    log "Connections dropped"
}

# Restore database
restore_database() {
    log "Restoring database..."
    
    # Drop and recreate database
    log "Dropping existing database..."
    docker exec "${CONTAINER_NAME}" psql -U "${DB_USER}" -d postgres -c "DROP DATABASE IF EXISTS ${DB_NAME};" || {
        error "Failed to drop database"
        exit 1
    }
    
    log "Creating new database..."
    docker exec "${CONTAINER_NAME}" psql -U "${DB_USER}" -d postgres -c "CREATE DATABASE ${DB_NAME};" || {
        error "Failed to create database"
        exit 1
    }
    
    # Enable TimescaleDB extension
    log "Enabling TimescaleDB extension..."
    docker exec "${CONTAINER_NAME}" psql -U "${DB_USER}" -d "${DB_NAME}" -c "CREATE EXTENSION IF NOT EXISTS timescaledb;" || {
        warning "Failed to enable TimescaleDB extension (may not be needed)"
    }
    
    # Restore from backup
    log "Restoring from backup file..."
    if docker exec -i "${CONTAINER_NAME}" psql -U "${DB_USER}" -d "${DB_NAME}" < "${BACKUP_FILE}"; then
        log "Database restore completed successfully"
    else
        error "Database restore failed"
        exit 1
    fi
}

# Verify restore
verify_restore() {
    log "Verifying restore..."
    
    # Check if database exists
    local db_exists=$(docker exec "${CONTAINER_NAME}" psql -U "${DB_USER}" -d postgres -tAc "SELECT 1 FROM pg_database WHERE datname='${DB_NAME}';")
    
    if [ "${db_exists}" != "1" ]; then
        error "Database verification failed: database does not exist"
        exit 1
    fi
    
    # Check table count
    local table_count=$(docker exec "${CONTAINER_NAME}" psql -U "${DB_USER}" -d "${DB_NAME}" -tAc "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='public';")
    
    log "Database tables found: ${table_count}"
    
    if [ "${table_count}" -eq 0 ]; then
        warning "No tables found in restored database"
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
    
    local table_count=$(docker exec "${CONTAINER_NAME}" psql -U "${DB_USER}" -d "${DB_NAME}" -tAc "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='public';")
    local db_size=$(docker exec "${CONTAINER_NAME}" psql -U "${DB_USER}" -d "${DB_NAME}" -tAc "SELECT pg_size_pretty(pg_database_size('${DB_NAME}'));")
    
    cat << EOF

================================================================================
                          RESTORE REPORT
================================================================================
Timestamp:        $(date +'%Y-%m-%d %H:%M:%S')
Database:         ${DB_NAME}
Source Backup:    $(basename "$1")
Tables Restored:  ${table_count}
Database Size:    ${db_size}
Status:           SUCCESS
================================================================================

EOF
}

# Main execution
main() {
    log "========================================="
    log "Starting Database Restore Process"
    log "========================================="
    
    check_prerequisites
    confirm_restore
    prepare_backup
    drop_connections
    restore_database
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
