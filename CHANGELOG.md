# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.0.1] - 2026-08-31

### Security

- Removed `.env.local`, `backend/.env.docker`, and `frontend/.env.docker` from git tracking
- Rewrote git history to purge committed `.env` files containing real credentials
- Hardened `.gitignore` to exclude all real `.env` files; only `.env.example` and `.env.docker.example` are permitted

## [1.0.0] - 2026-02-13

### First Production Release for Territory Technology Solutions (TTS)

This is the first production-ready release of the Multi-Tenant Reporting Platform, deployed on-premise for TTS.

### Integrations (22 Total)

- **Autotask PSA** - Full sync with customer mapping, tickets, and service data
- **Datto RMM & Backup** - Device monitoring, backup status, alerts, and historical import
- **CIPP (Microsoft 365)** - Tenant management with parallel sync optimization for 138+ tenants
- **IT Glue** - Documentation and configuration sync with license utilization
- **ThreatLocker** - Endpoint security with application control and audit data
- **Vonahi vPenTest** - Penetration testing results with v3 API integration
- **Barracuda** - Email security and backup integration
- **BitDefender** - Endpoint protection with SSL support
- **RocketCyber** - SOC/MDR integration
- **Sendmarc** - DMARC email authentication monitoring
- **LibreNMS** - Network monitoring integration
- **Veeam / Veeam ONE** - Backup and monitoring integration
- **Kaseya** - RMM integration
- **Kemp** - Load balancer monitoring
- **SharePoint** - Report delivery to customer SharePoint sites
- **Active Directory / LDAP** - Authentication integration
- **AdminDroid** - M365 analytics
- **M365** - Direct Microsoft 365 integration
- **SAT** - Security awareness training
- **SaaS** - Generic SaaS integration framework
- **WatchGuard AuthPoint** - MFA integration

### Report Generation

- **Gotenberg PDF Generation** - Professional PDF reports via Gotenberg API
- **ThreatLocker Summary Report** - Customizable templates with Blade rendering
- **Vonahi vPenTest Summary Report** - Penetration test results with findings
- **Report Templates** - Per-customer customizable report templates with bulk apply
- **Report Scheduling** - Flexible frequencies (daily/weekly/monthly/yearly/on-demand)
- **Report History** - Track all generated reports with filtering and download
- **Email Delivery** - Send reports via SMTP with branded templates
- **SharePoint Delivery** - Upload reports directly to customer SharePoint sites
- **Resend Report** - Re-deliver previously generated reports
- **Manual Generation** - CLI command for on-demand report generation

### Customer Mapping

- **Auto-Match Algorithm** - Fuzzy matching with configurable confidence threshold
- **Embedding-Based Matching** - AI-powered semantic similarity for customer name matching
- **Manual Mapping UI** - Drag-and-drop interface for unmapped customers
- **Source of Truth** - Designate primary integration for canonical customer names
- **Customer Merge/Split** - Tools for combining or separating customer records
- **Bulk Operations** - Map multiple customers at once

### Authentication & Security

- **Local Authentication** - Email/password with bcrypt hashing
- **LDAP/Active Directory** - Enterprise directory integration
- **SAML 2.0** - Single sign-on with identity providers
- **OAuth 2.0 (Microsoft 365)** - Azure AD authentication
- **Granular Permissions** - Per-module read/write/manage permissions
- **API Key System** - Programmatic access with scoped permissions and key preview
- **Session Timeout** - Configurable inactivity timeout
- **Audit Logging** - Comprehensive ISO 27001 compliant audit trail
- **Rate Limiting** - Per-integration API rate limiting with Redis tracking

### Dashboard & Monitoring

- **Unified Dashboard** - Single optimized API endpoint with 30s caching
- **Stats Grid** - Imported customers, mapped customers, unmapped customers, system users
- **Integration Summary** - Real-time status of all connected integrations
- **Report Charts** - Monthly report generation trends (6 months)
- **Activity Trends** - 7-day sync, report, and login activity charts
- **Health Monitoring** - System status, queue metrics, integration health checks
- **Schedule Summary** - Active and total report schedule counts

### Real-Time Features

- **WebSocket Notifications** - Laravel Reverb for real-time push notifications
- **AI Watcher System** - Chatbot monitors audit events and provides contextual tips with doc links
- **In-App Notifications** - Notification center with read/unread tracking
- **Toast Notifications** - Instant feedback for all user actions

### UI/UX

- **Modern React Interface** - React 19 with TypeScript, Tailwind CSS, Radix UI
- **Dark Mode** - Full dark mode support with theme persistence
- **Responsive Design** - Mobile, tablet, and desktop layouts with collapsible sidebar
- **In-App Documentation** - Comprehensive help guides with search and categories
- **Guided Onboarding** - Welcome modal and step-by-step tour for new users
- **Integration Data Preview** - View live data from any integration before reporting
- **Custom 404 Page** - Branded error page with navigation options
- **Logo Upload** - Company branding with logo upload and preview

### Infrastructure

- **Native Linux Deployment** - Systemd services for workers, scheduler, embedding
- **Nginx Configuration** - Production-ready with security headers, gzip, caching
- **Deploy Script** - One-command deployment with migration and frontend build
- **TimescaleDB** - Time-series data with automatic partitioning and compression
- **Redis** - Caching, queues, rate limiting, session storage
- **Gotenberg** - PDF generation service
- **Embedding Service** - Python-based semantic similarity for customer matching

### Developer Experience

- **CI/CD Pipeline** - GitLab CI with automated testing and deployment
- **PHPStan** - Static analysis for backend code quality
- **TypeScript** - Full type safety across frontend
- **Action Pattern** - Laravel actions for business logic encapsulation
- **Modular Integrations** - Namespace-based integration framework
- **Comprehensive .env.example** - Production-ready configuration template

### Fixed (Since 0.1.0)

- Audit log timestamps now output UTC for consistent relative time display
- Clipped integration names use Radix Tooltip instead of HTML title
- Dashboard shows imported customer count from integration data
- Stuck historical imports can be reset via UI and auto-cleanup
- CIPP parallel sync with atomic upserts prevents duplicate constraint violations
- Datto historical import progress reporting accuracy
- Map Customers page performance with optimized queries
- Notification system conditional broadcast to prevent empty notifications
- Proxy trust configuration for correct IP addresses in audit logs
- ThreatLocker data column mapping corrected for IntegrationData inserts
- IT Glue license utilization calculation
- Various PHPStan errors resolved across backend

## [0.1.0] - 2025-11-26

### Added
- Initial project setup
- Backend Laravel 12 API with TimescaleDB
- Frontend React 19 with TypeScript and Tailwind CSS
- Docker Compose configuration for development
- Authentication system with Sanctum
- Database migrations and seeders
- Basic CRUD operations for all main entities
