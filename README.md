# Multi-Tenant Reporting Platform

[![Laravel](https://img.shields.io/badge/Laravel-12-FF2D20?style=flat&logo=laravel)](https://laravel.com)
[![React](https://img.shields.io/badge/React-19-61DAFB?style=flat&logo=react)](https://react.dev)
[![TypeScript](https://img.shields.io/badge/TypeScript-5.9-3178C6?style=flat&logo=typescript)](https://www.typescriptlang.org)
[![PostgreSQL](https://img.shields.io/badge/PostgreSQL-16-336791?style=flat&logo=postgresql)](https://www.postgresql.org)
[![License](https://img.shields.io/badge/License-MIT-green)](LICENSE)
[![Pipeline](https://img.shields.io/badge/Pipeline-Passing-success)](https://gitlab.athenanetworks.com.au/athenanetworks/reporting/pipelines)
[![Version](https://img.shields.io/badge/Version-1.0.0-blue)](CHANGELOG.md)

## Overview

A world-class, enterprise-grade reporting platform designed for MSPs and telcos to consolidate customer data across multiple business systems, intelligently map customers, and automatically generate professional, white-labeled reports.

### Key Features

- 🏢 **Multi-Tenant Architecture** - Complete tenant isolation at every layer
- 🔐 **Enterprise Security** - ISO 27001 compliant with comprehensive audit logging
- 🔑 **Flexible Authentication** - Local, LDAP/AD, SAML, and OAuth support
- 🔌 **Modular Integrations** - Extensible framework for rapid integration additions
- 📊 **Smart Customer Mapping** - AI-powered fuzzy matching with manual override
- 📈 **Time-Series Analytics** - TimescaleDB-powered historical data analysis
- 🎨 **White-Label Ready** - Per-tenant branding and customization
- 🚀 **Production Ready** - Battle-tested with enterprise-grade security
- 📱 **Modern UI** - Beautiful, responsive React interface with dark mode
- 🔔 **Real-time Notifications** - In-app notification system with detailed views
- 📲 **Responsive Design** - Collapsible sidebar with persistent state across navigation
- 🎯 **Interactive UI** - Clickable notifications with full detail modals
- 💾 **State Persistence** - UI preferences saved across sessions
- 🔍 **Integration Data Preview** - View live data from any integration before generating reports
- 📋 **Enhanced Company Settings** - Email, phone, and address included in report generation
- 📄 **Professional PDF Export** - Enhanced integration preview exports with company branding and styling
- 📊 **Report History** - Track all generated reports with filtering and download capabilities
- 📧 **Email Reports** - Send reports directly via email with success confirmation
- 📤 **SharePoint Delivery** - Upload reports directly to customer SharePoint sites
- 🔄 **Extensible Delivery System** - Easy to add new delivery methods (Email, SharePoint, more)
- ✅ **Form Validation** - Smart validation modals for required fields
- 🔍 **Search & Filter** - Search functionality across integrations, reports, and schedules
- 📄 **Smart Pagination** - 9 items per page with dynamic labels
- 📚 **In-App Documentation** - Comprehensive help guides with search, categories, and sharable links
- 🎓 **Guided Onboarding** - Welcome modal and step-by-step tour for first-time users
- 🚫 **Custom 404 Page** - Branded error page for unknown routes with navigation options

---

## Technical Stack

### Backend
- **Framework**: Laravel 12 (PHP 8.2+)
- **Database**: PostgreSQL 16 + TimescaleDB (time-series extension)
- **Authentication**: Laravel Sanctum (Local, SAML, M365, LDAP)
- **Cache**: Redis (recommended) / Database fallback
- **Queue**: Laravel Queue with Redis/Database drivers
- **PDF Generation**: Gotenberg service

### Frontend
- **Framework**: React 19 with TypeScript 5.9
- **Build Tool**: Vite 7
- **UI Library**: Radix UI + Tailwind CSS 3.4
- **State Management**: Zustand + React Context
- **Icons**: Lucide React
- **Routing**: React Router v7

### Infrastructure
- **Web Server**: NGINX (production config in `nginx/`)
- **Process Management**: Systemd services (see `systemd/`)
- **Deployment**: Native Linux server (see `docs/DEPLOYMENT_NATIVE.md`)
- **Monitoring**: Built-in health checks + custom alerting system

---

## Core Features

### Multi-Tenancy Architecture
- Tenant isolation at company level
- User management with granular read/write permissions per company
- Data segregation ensuring no cross-tenant data leakage

### Integration Framework
- Modular, namespace-based integration system
- Each integration lives in its own namespace (e.g., `App\Integrations\Autotask`, `App\Integrations\DattoRMM`)
- Integration interface/contract that all integrations must implement
- Integrations pull and store data on scheduled basis
- Company can designate one integration as "source of truth" for customer list

### Customer Mapping System
- Auto-match customers with identical names across integrations
- Manual mapping interface for mismatched customer names
- Maintains relationship mapping table to ensure reports pull correct data across all integrated systems

### Report Generation & Distribution
- Per-customer report selection (which reports to generate monthly)
- Per-customer delivery destination configuration (defaults to company admin)
- Scheduled report generation engine
- Modular report framework - each report type in own namespace (e.g., `App\Reports\UptimeReport`, `App\Reports\SecuritySummary`)
- Report interface/contract that all reports must implement

### UI/UX Requirements
- Clean, modern interface
- Intuitive navigation for non-technical users
- Dashboard showing integration status, scheduled reports, recent deliveries

---

## 🚀 Quick Start

### Prerequisites

| Tool | Version | Download |
|------|---------|----------|
| PHP | 8.3+ | [windows.php.net](https://windows.php.net/download/) (Thread Safe) |
| Composer | 2.x | [getcomposer.org](https://getcomposer.org/download/) |
| Node.js | 20+ LTS | [nodejs.org](https://nodejs.org/) |
| PostgreSQL | 16+ | [postgresql.org](https://www.postgresql.org/download/) |
| TimescaleDB | 2.x | [timescale.com](https://docs.timescale.com/self-hosted/latest/install/) (optional for dev) |
| Redis | 7+ | [Memurai](https://www.memurai.com/) (Windows) or Docker |
| Docker | Latest | [docker.com](https://www.docker.com/products/docker-desktop) (for Gotenberg PDF) |

### Installation

#### Windows (PowerShell)

1. **Clone the Repository**
   ```powershell
   git clone https://gitlab.athenanetworks.com.au/athenanetworks/reporting.git
   cd reporting
   ```

2. **Quick Setup (Automated)**
   ```powershell
   cd scripts
   .\setup-local.ps1
   ```

3. **Manual Installation**
   ```powershell
   # Create PostgreSQL database
   $env:PGPASSWORD = "postgres"
   createdb -U postgres -h 127.0.0.1 reporting
   
   # Backend setup
   cd backend
   Copy-Item .env.example .env
   # Edit .env: Set DB_CONNECTION=pgsql, DB_HOST=127.0.0.1, DB_DATABASE=reporting
   composer install
   php artisan key:generate
   php artisan migrate
   php artisan db:seed
   php artisan serve --host=0.0.0.0 --port=8000
   
   # Frontend setup (new terminal)
   cd frontend
   Copy-Item .env.example .env
   npm install
   npm run dev
   ```

#### Linux/macOS (Bash)

1. **Clone and Setup**
   ```bash
   git clone https://gitlab.athenanetworks.com.au/athenanetworks/reporting.git
   cd reporting
   ./scripts/setup-local.sh
   ./scripts/start-dev.sh
   ```

4. **Production Deployment**
   
   See `docs/DEPLOYMENT_NATIVE.md` for complete production deployment guide.

5. **Access the Application**

   | Service | URL |
   |---------|-----|
   | Frontend | http://localhost:5173 |
   | Backend API | http://localhost:8000 |
   | Login Page | http://localhost:5173/login |

### Default Login Credentials

After running `php artisan db:seed`, use these credentials:

| User | Email | Password | Role |
|------|-------|----------|------|
| Admin | `admin@tts.com.au` | `ChangeMe123!` | Full Administrator |
| Test User | `kristianbas14@gmail.com` | `password123` | Administrator |
| Viewer | `viewer@tts.com.au` | `password123` | Scoped Viewer |

> ⚠️ **Important**: Change default passwords after first login!

### Optional Services

```powershell
# Start Queue Worker (for background jobs)
cd backend
php artisan queue:work

# Start Gotenberg (for PDF generation) - requires Docker
docker run -d -p 3000:3000 gotenberg/gotenberg:8

# Enable TimescaleDB (after installation)
psql -U postgres -d reporting -c "CREATE EXTENSION IF NOT EXISTS timescaledb CASCADE;"
```

---

## 📚 Documentation

- [Architecture Overview](docs/ARCHITECTURE.md) - System design and multi-tenancy
- [Integration Framework](docs/INTEGRATIONS.md) - How to add new integrations
- [Report Framework](docs/REPORTS.md) - Creating custom report types
- [API Documentation](docs/API.md) - RESTful API endpoints
- [Deployment Guide](docs/DEPLOYMENT.md) - Production deployment
- [Contributing Guide](CONTRIBUTING.md) - How to contribute
- [Changelog](CHANGELOG.md) - Version history

---

## 🛠️ Development Guidelines

### AI Usage - Keep It Focused
**Important**: Don't let AI run away on you. Use it for targeted, specific tasks. If you ask it to "build the integration system," you'll get a mess. Instead, ask it to help with specific components like "create the integration interface contract" or "write the PostgreSQL query for customer matching logic." Keep AI focused and on-track.

### Version Control Practices
- **Commit regularly** - small, logical commits with clear messages
- **Use git as your safety net** - if AI generates something that breaks things, you can easily roll back
- Commit before letting AI make major changes
- Branch strategy: feature branches off main, merge via MR
- Follow [Conventional Commits](https://www.conventionalcommits.org/) for commit messages

---

## Key API Endpoints

### Authentication & User Management
- `POST /api/auth/login` - Multi-mechanism authentication (Local, SAML/M365/LDAP)
- `GET /api/users` - List users in company
- `POST /api/users` - Create user with permissions
- `PATCH /api/users/{id}/permissions` - Update user permissions

### Company Settings
- `GET /api/company/settings` - Get company settings and contact information
- `PATCH /api/company/settings` - Update company details (name, email, phone, address)
- `PATCH /api/company/branding` - Update white-label branding (colors, logos)

### Integration Management
- `GET /api/integrations` - List available integration types
- `POST /api/integrations` - Add integration to company
- `GET /api/integrations/{id}/sync` - Trigger manual sync
- `GET /api/integrations/{id}/customers` - List customers from specific integration
- `GET /api/integrations/{id}/preview/template` - Get display template for integration
- `GET /api/integrations/{id}/preview/data` - Get preview data from integration
- `GET /api/integrations/{id}/preview` - Get template and data in single request
- `PATCH /api/company/source-integration` - Set source of truth integration

### Customer Mapping
- `GET /api/customers` - List all customers (merged view across integrations)
- `GET /api/customers/unmapped` - List customers requiring manual mapping
- `POST /api/customers/map` - Create manual customer mapping
- `GET /api/customers/{id}/integrations` - Show which integrations have this customer

### Report Configuration
- `GET /api/reports` - List available report types
- `GET /api/customers/{id}/reports` - Get report config for customer
- `PATCH /api/customers/{id}/reports` - Update which reports to generate
- `PATCH /api/customers/{id}/report-destination` - Set delivery destination
- `POST /api/reports/generate/{customer_id}` - Manual report generation trigger
- `POST /api/reports/{id}/email` - Email report to specified recipients

### Reporting & Analytics
- `GET /api/reports/scheduled` - View scheduled report queue
- `GET /api/reports/history` - Report delivery history
- `GET /api/reports/{id}/download` - Download specific report
- `POST /api/reports/{id}/share` - Generate a 1-hour share link for a completed report
- `GET /api/reports/shared/{token}` - Download a shared report (public, rate-limited)

---

## Modular Framework Structure

### Integration Module Structure
```
app/Integrations/
├── Contracts/
│   └── IntegrationInterface.php
├── Autotask/
│   ├── AutotaskIntegration.php
│   ├── AutotaskClient.php
│   └── config.php
└── DattoRMM/
    ├── DattoRMMIntegration.php
    ├── DattoRMMClient.php
    └── config.php
```

Each integration must implement: `sync()`, `getCustomers()`, `authenticate()`, `disconnect()`, `getDisplayTemplate()`, `getPreviewData()`

### Report Module Structure
```
app/Reports/
├── Contracts/
│   └── ReportInterface.php
├── UptimeReport/
│   ├── UptimeReport.php
│   └── template.blade.php
└── SecuritySummary/
    ├── SecuritySummary.php
    └── template.blade.php
```

Each report must implement: `generate()`, `getRequiredData()`, `getTemplate()`, `deliver()`

---

## ISO 27001 SoC Compliance Requirements

- All data in PostgreSQL encrypted at rest
- Encryption in transit (HTTPS/TLS for all API calls)
- Audit logging for all user actions
- Role-based access control (RBAC) enforced
- Secure credential storage for integration API keys (encrypted in DB)
- Data retention policies configurable per tenant
- Secure session management
- Input validation and sanitisation on all endpoints
- Regular security scanning in CI/CD pipeline

---

## Notification System

The platform features a comprehensive in-app notification system that keeps users informed of all system activities:

### Features
- **Real-time Updates** - Notifications sync automatically with dashboard activities
- **Bell Icon Indicator** - Red dot appears when new notifications are available
- **Dropdown Preview** - Quick view of recent notifications from any page
- **Dedicated Page** - Full notification list at `/notifications` with search and filtering
- **Click to View Details** - Each notification opens a detailed modal with complete information
- **Status Tracking** - Visual indicators for read/unread status
- **Contextual Information** - Type-specific details (sync, report, alert, info)
- **Mark as Read** - Individual or bulk actions to manage notification status

### Notification Types
- **Sync Events** - Integration synchronization completions and failures
- **Report Events** - Report generation and delivery status
- **Alerts** - System alerts requiring attention
- **Info** - General system information and updates

### User Experience
- Notifications automatically created from Recent Activity on dashboard
- Persistent across page navigation
- Modal detail view shows event type, severity, timestamp, and contextual help
- "View All Notifications" button in dropdown for quick access
- Recent Activity section links directly to notifications page

---

## Integration Data Visualization

The platform features a comprehensive data preview system that allows users to view integration data before generating reports:

### Features
- **Live Data Preview** - View real-time data from any integration without generating a full report
- **Standardized Display** - Each integration defines its own template for presenting data
- **Flexible Rendering** - Supports tables, key-value pairs, and list formats
- **Customer-Specific Filtering** - Option to filter data by specific customers
- **Accessible from Customer View** - Easy access through the customer integrations modal

### Display Template Types
Each integration can define custom display templates with the following section types:

- **Table View** - Tabular data with customizable columns (e.g., Active Directory users)
- **Key-Value Pairs** - Configuration or metadata display (e.g., Exchange server settings)
- **List View** - Simple lists of items (e.g., security groups, mailboxes)

### How It Works
1. Navigate to a customer's integrations view
2. Click the "View Data" (eye icon) button next to any integration
3. See live data pulled directly from the integration
4. Review data accuracy before scheduling reports
5. Verify customer mappings are correct

This feature helps clients validate data quality and troubleshoot integration issues before committing to automated report generation.

---

## Company Settings & Branding

### Enhanced Settings
Company settings now include comprehensive contact information that appears on all generated reports:

- **Company Name** - Your organization's display name
- **Contact Email** - Primary contact email for reports
- **Contact Phone** - Support phone number
- **Physical Address** - Company address (multi-line support)
- **Primary Brand Color** - Hex color for report theming

### Report Integration
All contact details are automatically included in:
- PDF report headers and footers
- Email delivery notifications
- White-labeled report templates
- Customer-facing documentation

### Settings API
```
GET  /api/company/settings        # Retrieve company settings
PATCH /api/company/settings        # Update company details
PATCH /api/company/branding        # Update branding and colors
```

---

## UI/UX Features

### Sidebar Navigation
- **Persistent State** - Sidebar collapsed/expanded state saved in localStorage
- **Cross-page Memory** - State maintained when navigating between pages
- **Keyboard Shortcut** - `Ctrl+B` (or `Cmd+B` on Mac) to toggle sidebar
- **Responsive Icons** - Collapsed state shows icon-only view with logo icon
- **Smooth Animations** - Polished transitions for expand/collapse
- **Mobile Optimized** - Dedicated mobile menu with sheet overlay

### Logo & Branding
- **Custom Favicon** - Configurable site icon (msp-logo-icon.png)
- **Dynamic Logos** - Different logos for light/dark themes
- **Collapsed View** - Icon logo when sidebar is collapsed
- **Expanded View** - Full logo with company name when expanded

---

## Quick Start

### Prerequisites

- PHP 8.2+
- Composer
- Node.js 20+ & npm
- SQLite (dev) or PostgreSQL 16+ (production)
- Redis (optional but recommended)

### Installation

```bash
# Clone the repository
git clone <repository-url>
cd reporting

# Install backend dependencies
cd backend
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed

# Install frontend dependencies
cd ../frontend
npm install

# Start development servers
cd ../backend
composer run dev  # Starts Laravel, queue, and Vite concurrently
```

Visit `http://localhost:5173` for the frontend and `http://localhost/api` for the API.

### Default Credentials

After seeding, you can log in with:
- **Email**: admin@example.com
- **Password**: password

**⚠️ Change these immediately in production!**

---

## Available Integrations

The platform currently supports:

### PSA & Documentation
- ✅ **Autotask PSA** - Tickets, time entries, projects, contracts
- ✅ **ITGlue** - Documentation platform with license consumption tracking

### RMM & Monitoring
- ✅ **DattoRMM** - Device monitoring, backup status, alerts
- ✅ **Kaseya VSA** - RMM platform integration
- ✅ **LibreNMS** - Network monitoring and device reports

### Identity & Directory
- ✅ **Active Directory** - Users, groups, memberships (agent-based push)
- ✅ **Exchange** - Mailboxes, permissions, statistics (agent-based push)
- ✅ **Microsoft 365** - Cloud identity and services
- ✅ **AdminDroid** - Microsoft 365 management reporting

### Security
- ✅ **BitDefender GravityZone** - Endpoint security
- ✅ **RocketCyber** - Security operations platform
- ✅ **ThreatLocker** - Application whitelisting
- ✅ **WatchGuard AuthPoint** - MFA platform
- ✅ **Sendmarc** - DMARC monitoring and compliance
- ✅ **Vonahi** - Penetration testing platform

### Backup & Infrastructure
- ✅ **Veeam Backup** - Backup monitoring
- ✅ **Barracuda** - Email security and backup
- ✅ **Kemp** - Load balancer monitoring

### SaaS & Cloud
- ✅ **SaaS Alerts** - SaaS application monitoring
- ✅ **SAT (Security Awareness Training)** - Training platform

**24 integrations** implemented following a modular design pattern. Adding new integrations is straightforward - see [Integration Scaffold](backend/docs/INTEGRATION_SCAFFOLD.md).

---

## Architecture Highlights

### Security Features

- 🔒 **Encrypted at Rest**: All sensitive data encrypted in database
- 🔐 **HTTPS/TLS**: All API communications over TLS
- 🛡️ **CSP Headers**: Strict Content Security Policy
- 🚫 **Rate Limiting**: Per-integration and per-IP rate limiting
- 📝 **Audit Logging**: Comprehensive audit trail (7-year retention)
- 🔑 **API Keys**: Granular API access with permission scoping
- ⏱️ **Session Management**: Configurable timeouts and encryption
- 🚨 **Failed Login Detection**: Automatic lockout after failed attempts

### Performance Features

- ⚡ **TimescaleDB**: Automatic time-series data partitioning and compression
- 🗄️ **Continuous Aggregates**: Pre-computed rollups for fast queries
- 📦 **Redis Caching**: Optional Redis for 10-100x performance boost
- 🔄 **Queue System**: Asynchronous job processing
- 📊 **Database Indexes**: Optimized for common query patterns
- 🎯 **Lazy Loading**: Efficient data loading in frontend

### Reliability Features

- 🔄 **Automatic Retry**: Failed jobs retry with exponential backoff
- 📋 **Health Checks**: Built-in health monitoring endpoints
- 🔍 **Error Tracking**: Comprehensive error logging
- 💾 **Data Retention**: Configurable per-company retention policies
- 🔙 **Database Fallback**: Rate limiter works without Redis
- 🛡️ **Fail-Open Design**: Services degrade gracefully

---

## Documentation

- 📘 **[Configuration Guide](CONFIGURATION.md)** - Environment setup and production configuration
- 📗 **[Project Plan](PROJECT_PLAN.md)** - Detailed architecture and technical decisions
- 📙 **[Quick Start Guide](QUICK_START.md)** - Get up and running quickly
- 📕 **[Integration Scaffold](backend/docs/INTEGRATION_SCAFFOLD.md)** - How to build new integrations
- 📓 **[Customer Syncing](backend/docs/INTEGRATION_CUSTOMER_SYNCING.md)** - Customer mapping system

---

## Development

### Running Tests

```bash
cd backend
php artisan test
```

### Code Quality

```bash
# Run Laravel Pint (code formatter)
./vendor/bin/pint

# Run PHPStan (static analysis)
./vendor/bin/phpstan analyse
```

### Frontend Linting

```bash
cd frontend
npm run lint
npm run build  # Check for TypeScript errors
```

---

## Deployment

### Production Checklist

#### Environment & Security
- [ ] Set `APP_ENV=production` and `APP_DEBUG=false`
- [ ] Generate new `APP_KEY` (`php artisan key:generate`)
- [ ] Set `LOG_LEVEL=warning` or `LOG_LEVEL=error`
- [ ] Set `SESSION_SECURE_COOKIE=true` (requires HTTPS)
- [ ] Set `SESSION_DOMAIN` to your production domain
- [ ] Configure `CORS_ALLOWED_ORIGINS` to production frontend URL only
- [ ] Configure `SANCTUM_STATEFUL_DOMAINS` for production domain
- [ ] Change all default passwords immediately after first login

#### Database & Services
- [ ] Configure PostgreSQL with TimescaleDB extension
- [ ] Run all pending migrations (`php artisan migrate --force`)
- [ ] Set up Redis for caching, queues, and rate limiting
- [ ] Set `CACHE_STORE=redis` and `QUEUE_CONNECTION=redis`
- [ ] Configure Gotenberg for PDF generation (`GOTENBERG_URL`)
- [ ] Configure embedding service if using AI customer matching

#### Email & Delivery
- [ ] Configure SMTP (`MAIL_MAILER=smtp` with real credentials)
- [ ] Set `MAIL_FROM_ADDRESS` and `MAIL_FROM_NAME` to company values
- [ ] Test email delivery with a test report

#### Real-Time & WebSocket
- [ ] Configure Laravel Reverb (`BROADCAST_CONNECTION=reverb`)
- [ ] Set `REVERB_APP_KEY`, `REVERB_APP_SECRET` to unique values
- [ ] Configure `VITE_REVERB_*` variables for frontend WebSocket

#### Infrastructure
- [ ] Set up SSL/TLS certificates (Let's Encrypt or manual)
- [ ] Configure Nginx with `nginx/mspreports.conf`
- [ ] Install systemd services (`systemd/install-services.sh`)
- [ ] Configure firewall rules (allow 80, 443, block all else)
- [ ] Set up automated database backups
- [ ] Configure log rotation

#### Integrations & Data
- [ ] Add and test each integration (Datto, CIPP, Autotask, etc.)
- [ ] Verify integration syncs complete successfully
- [ ] Run customer auto-matching after initial sync
- [ ] Review and approve customer mappings
- [ ] Configure rate limits per integration

#### Branding & Reports
- [ ] Upload company logo and configure white-label branding
- [ ] Set company contact details (email, phone, address)
- [ ] Configure report templates per customer
- [ ] Set up report schedules
- [ ] Test PDF generation with Gotenberg
- [ ] Test report delivery (email and/or SharePoint)

#### Monitoring
- [ ] Verify health check endpoint (`/api/monitoring/status`)
- [ ] Set up monitoring for queue workers
- [ ] Configure alerting for sync failures
- [ ] Review audit logs for completeness

See [docs/DEPLOYMENT_NATIVE.md](docs/DEPLOYMENT_NATIVE.md) for the complete deployment guide.

---

## Development Roadmap

### ✅ Phase 1: Foundation (Completed)
- Multi-tenant architecture
- Authentication system
- Core database schema
- White-labeling system

### ✅ Phase 2: Integration Framework (Completed)
- Modular integration contracts
- 10+ integrations implemented
- Rate limiting framework
- Historical data import system

### ✅ Phase 3: Customer Mapping (Completed)
- Fuzzy matching algorithm
- Manual mapping interface
- Source of truth designation
- Merge/split capabilities

### ✅ Phase 4: Report Framework (Completed)
- Report generation engine with Gotenberg PDF service
- **14 report types** implemented:
  - Autotask Summary, Backup Status, BitDefender Summary
  - Datto RMM Summary, ITGlue Summary, LibreNMS Device Report
  - Monthly Comprehensive, SaaS Summary, Security Summary
  - Sendmarc Summary, Uptime Report, and more
- Scheduled generation with flexible frequencies (daily/weekly/monthly/yearly)
- White-label PDF output with company branding
- Global/template schedules for reusable configurations

### ✅ Phase 5: Report Delivery (Completed)
- Email delivery with SMTP configuration
- **SharePoint delivery** via Microsoft Graph API
- Extensible delivery driver system
- Per-customer delivery configuration
- Connection testing for delivery methods

### ✅ Phase 6: Authentication & RBAC (Completed)
- LDAP/Active Directory authentication
- SAML and OAuth configuration
- Role-based access control (RBAC)
- Customer-level scoping for users
- JIT user provisioning

### 📋 Phase 7: Advanced Features (Planned)
- Advanced analytics dashboard
- Custom report builder
- API webhook integrations
- Mobile app companion

---

## 🤝 Contributing

We welcome contributions! Please see our [Contributing Guide](CONTRIBUTING.md) for comprehensive details on:

- Code of Conduct
- Development setup and workflow
- Coding standards (PSR-12, TypeScript best practices)
- Commit message conventions (Conventional Commits)
- Pull request process and review guidelines
- Testing requirements

### Quick Contribution Guide

1. Fork the repository
2. Create feature branch from `main` (`git checkout -b feature/amazing-feature`)
3. Follow Laravel and React best practices
4. Write tests for new features
5. Update documentation and CHANGELOG.md
6. Commit using conventional commits (`git commit -m 'feat: add amazing feature'`)
7. Push to your branch (`git push origin feature/amazing-feature`)
8. Submit merge request for review

### Code Standards

- **Backend**: Follow PSR-12 coding standards, use type hints
- **Frontend**: TypeScript strict mode, React functional components with hooks
- **Commits**: Use [Conventional Commits](https://www.conventionalcommits.org/) format
- **Documentation**: Update README, API docs, and relevant documentation
- **Tests**: Maintain or improve code coverage

---

## Security

### Reporting Security Issues

**DO NOT** open public issues for security vulnerabilities.

Email security concerns to: security@athenanetworks.com.au

### Security Features

- Multi-layer authentication with MFA support
- Comprehensive audit logging
- Rate limiting and brute force protection
- SQL injection prevention (Eloquent ORM + parameterized queries)
- XSS prevention (React auto-escaping + CSP headers)
- CSRF protection (Laravel Sanctum)
- Encrypted credentials storage
- Secure session management

### Security Implementations

#### API Rate Limiting
- **Global rate limit**: 300 requests/minute for authenticated routes
- **Auth endpoints**: 10 requests/minute for login, password reset (brute force protection)
- **Public endpoints**: 30 requests/minute for share link downloads
- Rate limit headers included in responses (`X-RateLimit-Limit`, `X-RateLimit-Remaining`, `X-RateLimit-Reset`)

#### Password Policy Enforcement
Strong password requirements enforced on all password endpoints:
- Minimum 8 characters
- At least one uppercase letter
- At least one lowercase letter
- At least one number
- At least one special character
- Common password blocklist (password123, admin, etc.)

#### Session Timeout
Server-side session timeout enforcement:
- **Inactivity timeout**: Configurable per company (default 60 minutes)
- **Maximum session duration**: 8 hours absolute limit
- Sessions automatically invalidated on timeout
- Company-specific settings via `settings.authentication.session_timeout_minutes`

#### Share Link Security
Report share links include HMAC signatures for tamper detection:
- 64-character cryptographically random tokens
- HMAC-SHA256 signature using application key
- 1-hour expiration
- Signature verification on download
- Failed signature attempts logged for security monitoring

Share link endpoints:
- `POST /api/reports/{id}/share` (authenticated)
- `GET /api/reports/shared/{token}` (public, rate-limited)

#### Credential Protection
- Integration credentials never logged (only key names logged for debugging)
- Credentials encrypted at rest using Laravel encryption (AES-256)
- Per-company encryption keys for credential storage

#### SQL Injection Prevention
- All database queries use Eloquent ORM or parameterized queries
- JSONB queries use bound parameters for values
- Input validation on all API endpoints

---

## 📋 Version 1.0.0 Highlights

### Production-Ready for TTS
- **22 Integrations** - Datto, CIPP, Autotask, ITGlue, ThreatLocker, Vonahi, and 16 more
- **Native Linux Deployment** - Systemd services, Nginx config, deploy script
- **Gotenberg PDF Generation** - Professional branded reports
- **AI Watcher Chatbot** - Real-time contextual tips based on user activity
- **Unified Dashboard** - Single optimized API with 30s caching
- **Comprehensive Audit Logging** - ISO 27001 compliant with 7-year retention
- **Multi-Auth** - Local, LDAP/AD, SAML 2.0, OAuth 2.0 (Microsoft 365)
- **Real-Time Notifications** - WebSocket via Laravel Reverb
- **Embedding-Based Customer Matching** - AI-powered semantic similarity

---

## 📄 License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

Copyright © 2025 Athena Networks. All rights reserved.

The MIT License allows for free use, modification, and distribution with proper attribution.

---

## 📞 Support & Resources

### Documentation
- **[Quick Start Guide](README.md#-quick-start)** - Get started quickly
- **[Contributing Guide](CONTRIBUTING.md)** - How to contribute
- **[Changelog](CHANGELOG.md)** - Version history and release notes
- **[Project Wiki](https://gitlab.athenanetworks.com.au/athenanetworks/reporting/-/wikis/home)** - Comprehensive documentation

### Getting Help
- **Email**: support@athenanetworks.com.au
- **Issues**: [GitLab Issues](https://gitlab.athenanetworks.com.au/athenanetworks/reporting/issues)
- **Wiki**: Check project wiki for FAQs and guides

### CI/CD Pipeline
This project uses GitLab CI/CD for automated testing and deployment. See [.gitlab-ci.yml](.gitlab-ci.yml) for pipeline configuration.

---

## 🙏 Acknowledgments

- Built with ❤️ by the Athena Networks team
- Special thanks to all contributors
- Powered by Laravel, React, PostgreSQL, TimescaleDB, and other amazing open-source technologies
