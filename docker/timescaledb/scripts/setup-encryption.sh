#!/bin/bash
set -e

echo "=== PostgreSQL Encryption Setup ==="

# Enable SSL in PostgreSQL configuration
cat >> "${PGDATA}/postgresql.conf" <<EOF

# SSL Configuration (Encryption in Transit)
ssl = on
ssl_cert_file = '/var/lib/postgresql/ssl/server.crt'
ssl_key_file = '/var/lib/postgresql/ssl/server.key'
ssl_ciphers = 'HIGH:MEDIUM:+3DES:!aNULL'
ssl_prefer_server_ciphers = on
ssl_min_protocol_version = 'TLSv1.2'

# Connection Security
password_encryption = scram-sha-256

# Logging for security auditing
log_connections = on
log_disconnections = on
log_duration = on
log_statement = 'mod'
log_line_prefix = '%t [%p]: [%l-1] user=%u,db=%d,app=%a,client=%h '

EOF

echo "✅ SSL/TLS encryption enabled for connections"
echo "✅ Password encryption set to scram-sha-256"
echo "✅ Security logging enabled"

# Note: For encryption at rest in development, Docker volumes are used.
# For production, use:
# - Encrypted EBS volumes (AWS)
# - Encrypted persistent disks (GCP)
# - Azure Disk Encryption (Azure)
# - LUKS encryption for on-premise deployments
