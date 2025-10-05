#!/bin/bash
# Generate self-signed SSL certificates for development
# For production, use proper CA-signed certificates

set -e

CERT_DIR="./docker/timescaledb/ssl"
mkdir -p "$CERT_DIR"

echo "=== Generating SSL Certificates for PostgreSQL ==="

# Generate private key
openssl genrsa -out "$CERT_DIR/server.key" 2048

# Generate certificate signing request
openssl req -new -key "$CERT_DIR/server.key" -out "$CERT_DIR/server.csr" \
    -subj "/C=AU/ST=Queensland/L=Brisbane/O=Athena Networks/OU=Development/CN=timescaledb"

# Generate self-signed certificate (valid for 365 days)
openssl x509 -req -days 3650 -in "$CERT_DIR/server.csr" \
    -signkey "$CERT_DIR/server.key" -out "$CERT_DIR/server.crt"

# Set proper permissions
chmod 600 "$CERT_DIR/server.key"
chmod 644 "$CERT_DIR/server.crt"

# Clean up CSR
rm "$CERT_DIR/server.csr"

echo "✅ SSL certificates generated successfully"
echo "   Certificate: $CERT_DIR/server.crt"
echo "   Private Key: $CERT_DIR/server.key"
echo ""
echo "⚠️  These are self-signed certificates for DEVELOPMENT only"
echo "   For PRODUCTION, use CA-signed certificates from Let's Encrypt, AWS ACM, etc."
