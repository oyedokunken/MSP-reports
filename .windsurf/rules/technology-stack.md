---
trigger: always_on
---

## Technology Stack

### Frontend

- **Framework:** React 18+
- **State Management:** React Context or Zustand (lightweight)
- **Styling:** Tailwind CSS (utility-first, white-label friendly)
- **Charts/Graphs:** Apache eCharts
- **HTTP Client:** Axios
- **Build Tool:** Vite

### Backend

- **Framework:** Laravel 11
- **Language:** PHP 8.2+
- **API Pattern:** RESTful with consistent response structure
- **Authentication:** Laravel Sanctum (sessions + API tokens)
- **Validation:** Laravel Form Requests
- **Queue System:** Laravel Queue with Redis driver

### Database

- **Primary:** TimescaleDB (PostgreSQL 16 with time-series extension)
- **Caching:** Redis 7+
- **Why TimescaleDB:** 
  - Native time-series optimization for historical data
  - Automatic partitioning by time
  - Continuous aggregates (auto-updating materialized views)
  - Built-in compression (90%+ reduction for old data)
  - Native retention policies
  - Still PostgreSQL underneath (encryption at rest, Laravel compatible)

### Infrastructure

- **Containerization:** Docker + Docker Compose
- **Web Server:** NGINX or Caddy (TLS termination)
- **Process Management:** Supervisor (queue workers, scheduler)
- **Storage:** Local filesystem initially, S3-compatible for SaaS
- **Backups:** Automated PostgreSQL dumps with encryption

### Security

- **Encryption at Rest:** PostgreSQL/TimescaleDB volume encryption
- **Encryption in Transit:** TLS 1.3 for all API calls
- **Credential Storage:** Encrypted in database using Laravel encryption
- **Password Hashing:** Bcrypt (Laravel default)
- **Session Management:** Redis-backed sessions with configurable timeout

### External Services

- **Email Delivery:** SMTP (configurable per company or system default)
- **PDF Generation:** Gutenberg PDF API
- **File Storage:** Local initially, migrate to object storage for SaaS
- **Monitoring:** Application health checks, integration status tracking