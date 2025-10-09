-- Enable TimescaleDB extension
CREATE EXTENSION IF NOT EXISTS timescaledb;

-- Set timezone to Australia/Brisbane
SET timezone = 'Australia/Brisbane';

-- Create additional extensions for full-text search, UUID, etc.
CREATE EXTENSION IF NOT EXISTS "uuid-ossp";
CREATE EXTENSION IF NOT EXISTS "pg_trgm";  -- For fuzzy text matching (customer mapping)

-- Performance optimizations for TimescaleDB
ALTER DATABASE reporting SET timescaledb.max_background_workers = 8;

-- Grant privileges
GRANT ALL PRIVILEGES ON DATABASE reporting TO reporting_user;
