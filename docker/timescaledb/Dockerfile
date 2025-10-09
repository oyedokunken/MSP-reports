FROM timescale/timescaledb:latest-pg16

# Copy encryption setup scripts
COPY ./docker/timescaledb/scripts/setup-encryption.sh /docker-entrypoint-initdb.d/00-setup-encryption.sh
RUN chmod +x /docker-entrypoint-initdb.d/00-setup-encryption.sh

# Enable SSL/TLS for connections
RUN mkdir -p /var/lib/postgresql/ssl
COPY ./docker/timescaledb/ssl/server.crt /var/lib/postgresql/ssl/server.crt
COPY ./docker/timescaledb/ssl/server.key /var/lib/postgresql/ssl/server.key
RUN chown -R postgres:postgres /var/lib/postgresql/ssl && \
    chmod 600 /var/lib/postgresql/ssl/server.key && \
    chmod 644 /var/lib/postgresql/ssl/server.crt
