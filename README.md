# Multi-Tenant Reporting Platform

[![Laravel](https://img.shields.io/badge/Laravel-12-FF2D20?style=flat&logo=laravel)](https://laravel.com)
[![React](https://img.shields.io/badge/React-19-61DAFB?style=flat&logo=react)](https://react.dev)
[![TypeScript](https://img.shields.io/badge/TypeScript-5.9-3178C6?style=flat&logo=typescript)](https://www.typescriptlang.org)
[![PostgreSQL](https://img.shields.io/badge/PostgreSQL-16-336791?style=flat&logo=postgresql)](https://www.postgresql.org)
[![License](https://img.shields.io/badge/License-Proprietary-red)](LICENSE)

## Overview

A world-class, enterprise-grade reporting platform designed for MSPs and telcos to consolidate customer data across multiple business systems, intelligently map customers, and automatically generate professional, white-labeled reports.

### Key Features

- 🏢 **Multi-Tenant Architecture** - Complete tenant isolation at every layer
- 🔐 **Enterprise Security** - ISO 27001 compliant with comprehensive audit logging
- 🔌 **Modular Integrations** - Extensible framework for rapid integration additions
- 📊 **Smart Customer Mapping** - AI-powered fuzzy matching with manual override
- 📈 **Time-Series Analytics** - TimescaleDB-powered historical data analysis
- 🎨 **White-Label Ready** - Per-tenant branding and customization
- 🚀 **Production Ready** - Battle-tested with enterprise-grade security
- 📱 **Modern UI** - Beautiful, responsive React interface with dark mode

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
- **Containerization**: Docker + Docker Compose
- **Web Server**: NGINX
- **Process Management**: Supervisor (queue workers)
- **Monitoring**: Laravel Horizon (optional)

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

## Development Guidelines

### AI Usage - Keep It Focused
**Important**: Don't let AI run away on you. Use it for targeted, specific tasks. If you ask it to "build the integration system," you'll get a mess. Instead, ask it to help with specific components like "create the integration interface contract" or "write the PostgreSQL query for customer matching logic." Keep AI focused and on-track.

### Version Control Practices
- **Commit regularly** - small, logical commits with clear messages
- **Use git as your safety net** - if AI generates something that breaks things, you can easily roll back
- Commit before letting AI make major changes
- Branch strategy: feature branches off main, merge via PR

---

## Key API Endpoints

### Authentication & User Management
- `POST /api/auth/login` - Multi-mechanism authentication (Local, SAML/M365/LDAP)
- `GET /api/users` - List users in company
- `POST /api/users` - Create user with permissions
- `PATCH /api/users/{id}/permissions` - Update user permissions

### Integration Management
- `GET /api/integrations` - List available integration types
- `POST /api/integrations` - Add integration to company
- `GET /api/integrations/{id}/sync` - Trigger manual sync
- `GET /api/integrations/{id}/customers` - List customers from specific integration
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

### Reporting & Analytics
- `GET /api/reports/scheduled` - View scheduled report queue
- `GET /api/reports/history` - Report delivery history
- `GET /api/reports/{id}/download` - Download specific report

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

Each integration must implement: `sync()`, `getCustomers()`, `authenticate()`, `disconnect()`

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

### Core Integrations
- ✅ **Autotask PSA** - Tickets, time entries, projects
- ✅ **DattoRMM** - Device monitoring, backup status
- ✅ **Active Directory** - Users, groups, memberships (push-based)
- ✅ **Exchange** - Mailboxes, permissions, statistics (push-based)

### Additional Integrations
- ✅ **ITGlue** - Documentation platform integration
- ✅ **Kaseya VSA** - RMM platform
- ✅ **BitDefender GravityZone** - Endpoint security
- ✅ **Veeam Backup** - Backup monitoring
- ✅ **RocketCyber** - Security operations platform
- ✅ **AdminDroid** - Microsoft 365 management
- ✅ **Barracuda** - Email security
- ⚡ More integrations added regularly

Each integration follows a modular design pattern making it easy to add new ones.

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

- [ ] Set `APP_ENV=production` and `APP_DEBUG=false`
- [ ] Generate new `APP_KEY`
- [ ] Configure PostgreSQL with TimescaleDB extension
- [ ] Set up Redis for caching and queues
- [ ] Configure SMTP for email delivery
- [ ] Set up SSL/TLS certificates
- [ ] Configure firewall rules
- [ ] Set up automated backups
- [ ] Configure queue workers via Supervisor
- [ ] Set up monitoring and alerting
- [ ] Review security headers in `SecureHeaders` middleware
- [ ] Configure rate limiting per integration
- [ ] Test white-label branding configuration

See [CONFIGURATION.md](CONFIGURATION.md) for detailed deployment guide.

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

### 🚧 Phase 4: Report Framework (In Progress)
- Report generation engine
- Multiple report types
- Scheduled generation
- White-label PDF output

### 📋 Phase 5: Advanced Features (Planned)
- Advanced analytics dashboard
- Custom report builder
- API webhook integrations
- Mobile app companion

---

## Contributing

This is a proprietary project. For internal contributors:

1. Create feature branch from `main`
2. Follow Laravel and React best practices
3. Write tests for new features
4. Update documentation
5. Submit pull request for review

### Code Standards

- **Backend**: Follow PSR-12 coding standards
- **Frontend**: Follow Airbnb React/TypeScript style guide
- **Commits**: Use conventional commits format
- **Documentation**: Update README and relevant docs

---

## Security

### Reporting Security Issues

**DO NOT** open public issues for security vulnerabilities.

Email security concerns to: security@athenanetworks.com.au

### Security Features

- Multi-layer authentication with MFA support
- Comprehensive audit logging
- Rate limiting and brute force protection
- SQL injection prevention (Eloquent ORM)
- XSS prevention (React auto-escaping + CSP headers)
- CSRF protection (Laravel Sanctum)
- Encrypted credentials storage
- Secure session management

---

## License

Proprietary and confidential. Unauthorized copying, modification, distribution, or use of this software is strictly prohibited.

Copyright (c) 2024-2025 Athena Networks. All rights reserved.

---

## Support

For support, configuration help, or deployment assistance:
- **Email**: support@athenanetworks.com.au
- **Documentation**: Check `/docs` directory
- **Issues**: Internal issue tracker only

---

## Acknowledgments

Built with ❤️ by the Athena Networks team.

Special thanks to the open-source community for the amazing tools that power this platform.