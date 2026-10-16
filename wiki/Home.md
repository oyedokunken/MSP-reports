# Multi-Tenant Reporting Platform Wiki

Welcome to the comprehensive documentation for the Multi-Tenant Reporting Platform!

## Quick Links

### Getting Started
- [Installation Guide](Installation)
- [Quick Start Tutorial](Quick-Start)
- [Configuration Guide](Configuration)
- [First Deployment](First-Deployment)

### Core Concepts
- [Multi-Tenancy Architecture](Multi-Tenancy-Architecture)
- [Integration Framework](Integration-Framework)
- [Customer Mapping System](Customer-Mapping)
- [Report Generation](Report-Generation)
- [White-Label Branding](White-Label-Branding)

### Development
- [Development Setup](Development-Setup)
- [Creating Integrations](Creating-Integrations)
- [Creating Reports](Creating-Reports)
- [Testing Guide](Testing-Guide)
- [API Documentation](API-Documentation)

### Operations
- [Deployment Guide](Deployment-Guide)
- [Monitoring & Alerting](Monitoring)
- [Backup & Recovery](Backup-Recovery)
- [Performance Tuning](Performance-Tuning)
- [Troubleshooting](Troubleshooting)

### Security
- [Security Best Practices](Security-Best-Practices)
- [Authentication Methods](Authentication)
- [API Key Management](API-Keys)
- [Audit Logging](Audit-Logging)

## Overview

The Multi-Tenant Reporting Platform is an enterprise-grade solution designed for MSPs and telcos to:

- **Consolidate Data**: Pull customer data from multiple business systems (PSA, RMM, Security, etc.)
- **Map Customers**: Intelligently match customers across systems with fuzzy matching
- **Generate Reports**: Automatically create professional, white-labeled reports on flexible schedules
- **Deliver Results**: Email reports or make available via API

## Architecture

```
┌─────────────────────────────────────────────────┐
│                   Frontend                       │
│         React 19 + TypeScript + Tailwind        │
└─────────────────────────────────────────────────┘
                        │
                        ▼
┌─────────────────────────────────────────────────┐
│                Backend API                       │
│              Laravel 12 + PHP 8.2                │
└─────────────────────────────────────────────────┘
                        │
        ┌───────────────┼───────────────┐
        ▼               ▼               ▼
┌─────────────┐ ┌─────────────┐ ┌─────────────┐
│ PostgreSQL  │ │    Redis    │ │   Queue     │
│ TimescaleDB │ │   (Cache)   │ │   Workers   │
└─────────────┘ └─────────────┘ └─────────────┘
```

## Documentation Sections

### For Users
- **[User Guide](User-Guide)** - How to use the platform
- **[Report Configuration](Report-Configuration)** - Setting up reports
- **[Customer Management](Customer-Management)** - Managing customers

### For Administrators
- **[Admin Guide](Admin-Guide)** - Platform administration
- **[User Management](User-Management)** - Managing users and permissions
- **[Integration Setup](Integration-Setup)** - Configuring integrations

### For Developers
- **[Developer Guide](Developer-Guide)** - Development workflows
- **[API Reference](API-Reference)** - Complete API documentation
- **[Database Schema](Database-Schema)** - Database structure

### For DevOps
- **[Deployment](Deployment)** - Deployment procedures
- **[CI/CD Pipeline](CI-CD-Pipeline)** - GitLab CI/CD setup
- **[Maintenance](Maintenance)** - Routine maintenance tasks

## Quick Start

1. **Clone the repository**
   ```bash
   git clone https://gitlab.athenanetworks.com.au/athenanetworks/reporting.git
   cd reporting
   ```

2. **Start with Docker**
   ```bash
   docker-compose up -d
   ```

3. **Access the application**
   - Frontend: http://localhost:5173
   - API: http://localhost:8000

See the [Quick Start Guide](Quick-Start) for detailed instructions.

## Contributing

We welcome contributions! See our [Contributing Guide](../CONTRIBUTING.md) for details.

## Support

- **Issues**: [GitLab Issues](https://gitlab.athenanetworks.com.au/athenanetworks/reporting/issues)
- **Email**: support@athenanetworks.com.au

## License

MIT License - See [LICENSE](../LICENSE) for details.

---

**Last Updated**: November 26, 2025  
**Version**: 0.1.0
