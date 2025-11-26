# Frequently Asked Questions (FAQ)

## General Questions

### What is the Multi-Tenant Reporting Platform?

A comprehensive enterprise platform that consolidates customer data from multiple business systems (PSA, RMM, security tools, etc.), intelligently maps customers across systems, and automatically generates professional, white-labeled reports on flexible schedules.

### Who is this platform for?

- **MSPs (Managed Service Providers)**: Generate monthly service reports for clients
- **Telcos**: Create customer usage and performance reports
- **IT Service Companies**: Automate customer reporting workflows

### What makes this platform "multi-tenant"?

Complete isolation between companies. Each company has:
- Separate data (no cross-tenant access)
- Own users and permissions
- Custom white-label branding
- Independent integrations and reports

## Technical Questions

### What technologies are used?

- **Backend**: Laravel 12 (PHP 8.2+), PostgreSQL 16, TimescaleDB
- **Frontend**: React 19, TypeScript 5.9, Tailwind CSS 3.4
- **Infrastructure**: Docker, Redis, NGINX

### What integrations are supported?

Currently supported:
- Autotask PSA
- Datto RMM, Backup, and SaaS Protection
- N-able N-sight and N-central
- CyberCNS
- Liongard
- Huntress
- RocketCyber
- AdminDroid
- Barracuda

More integrations added regularly.

### Can I add custom integrations?

Yes! The platform uses a modular integration framework. See [Creating Integrations](Creating-Integrations) for details.

### What report types are available?

Standard reports:
- Uptime Report
- Monthly Uptime Report
- Security Summary

Custom reports can be added following the report framework. See [Creating Reports](Creating-Reports).

### How does customer mapping work?

The system uses fuzzy matching algorithms to automatically match customers with similar names across different integrations (e.g., "ACME Corp" and "ACME Corporation"). Manual override is always available.

## Installation & Setup

### What are the system requirements?

**Minimum**:
- 2 CPU cores
- 4GB RAM
- 20GB storage
- Ubuntu 20.04+ or similar

**Recommended**:
- 4+ CPU cores
- 8GB+ RAM
- 50GB+ SSD storage
- PostgreSQL 16 with TimescaleDB extension

### Can I use Docker?

Yes! Docker is the recommended deployment method. See [Quick Start Guide](Quick-Start) for Docker setup.

### Do I need Redis?

Redis is optional but highly recommended for:
- Caching (10-100x performance boost)
- Queue management
- Session storage

The platform works without Redis but with reduced performance.

### How do I upgrade to a new version?

```bash
# Backup first!
git pull origin main
docker-compose down
docker-compose up -d
docker-compose exec backend php artisan migrate
docker-compose exec backend php artisan cache:clear
docker-compose exec frontend npm install
```

See [Deployment Guide](Deployment-Guide) for detailed upgrade procedures.

## Features & Usage

### How do I configure white-label branding?

1. Navigate to Settings
2. Update:
   - Company name
   - Primary color (hex code)
   - Logo (upload image)
   - Email settings

Reports will automatically use your branding.

### Can I customize report content?

Yes. Each report type can be customized:
- Which data to include
- Formatting and layout
- Customer-specific overrides

See [Report Configuration](Report-Configuration).

### How often can reports be generated?

Schedule options:
- **Daily**: Every day or specific days of week
- **Weekly**: Specific day of week
- **Monthly**: Specific day of month
- **Yearly**: Specific date
- **On-Demand**: Manual generation only

### Can I send reports to multiple recipients?

Yes! Configure primary destination and CC recipients for each customer.

### How do I export integration data?

Two options:
1. **Integration Preview**: Click "Export PDF" button
2. **API**: Use `/api/integrations/{id}/export` endpoint

## Security

### Is my data secure?

Yes. Security features include:
- Encryption at rest and in transit
- ISO 27001 compliant audit logging
- Role-based access control
- API key management with scoping
- Rate limiting and brute force protection
- Regular security audits

### How are credentials stored?

Integration credentials are encrypted using AES-256 encryption with per-company keys.

### What about data retention?

Configurable per company:
- Default: 90 days raw data, 365 days aggregated
- Audit logs: 7 years (compliance)
- Generated reports: Configurable

See [Data Retention](Data-Retention) for details.

### Can I restrict user access?

Yes. Granular permissions available:
- Integrations: view, manage, sync
- Customers: view, edit, map
- Reports: view, configure, generate, download
- Users: view, manage
- Company settings: view, edit

## Performance

### How much data can the platform handle?

Tested with:
- 1000+ customers per company
- 10+ integrations per company
- Millions of time-series data points
- 100+ concurrent users

TimescaleDB compression reduces storage by 90%+.

### Why is my first sync slow?

Historical data import takes time:
- Initial sync fetches all historical data
- Subsequent syncs are incremental
- Progress visible in UI

### How can I improve performance?

1. Enable Redis caching
2. Configure queue workers
3. Optimize PostgreSQL settings
4. Use SSD storage
5. Add database indexes for custom queries

See [Performance Tuning](Performance-Tuning).

## Troubleshooting

### Frontend shows blank page

Check browser console for errors:
1. Clear browser cache
2. Check API connectivity
3. Verify `.env` configuration
4. Rebuild frontend: `npm run build`

### Backend returns 500 errors

Check Laravel logs:
```bash
tail -f backend/storage/logs/laravel.log
```

Common causes:
- Database connection issues
- Missing environment variables
- Permission errors

### Integration sync fails

Check:
1. API credentials are correct
2. Integration service is accessible
3. Rate limits not exceeded
4. Error message in SyncLog

### Reports not generating

Verify:
1. Queue workers running
2. Sufficient data available
3. Schedule configuration correct
4. Check GeneratedReport status

## Development

### How do I contribute?

See [Contributing Guide](../CONTRIBUTING.md) for:
- Code of conduct
- Development workflow
- Coding standards
- Pull request process

### Where are the tests?

```bash
# Backend tests
cd backend
php artisan test

# Frontend tests
cd frontend
npm run test
```

### How do I add a new integration?

Follow the [Creating Integrations](Creating-Integrations) guide. Basic steps:
1. Create integration class implementing `IntegrationInterface`
2. Add registration in config
3. Implement required methods
4. Add tests
5. Update documentation

### Can I use this in production?

Yes! The platform is production-ready with:
- Comprehensive testing
- Security best practices
- Performance optimization
- Error handling
- Audit logging

## Licensing

### What license is this under?

MIT License - Free to use, modify, and distribute with attribution.

### Can I use this commercially?

Yes, the MIT License allows commercial use.

### Do I need to contribute changes back?

Not required, but appreciated! The MIT License permits proprietary modifications.

## Support

### Where can I get help?

- **Documentation**: This wiki and project docs
- **Issues**: [GitLab Issues](https://gitlab.athenanetworks.com.au/athenanetworks/reporting/issues)
- **Email**: support@athenanetworks.com.au

### How do I report bugs?

1. Check existing issues
2. Create new issue with:
   - Description of bug
   - Steps to reproduce
   - Expected vs actual behavior
   - Environment details
   - Error logs (if applicable)

### Is professional support available?

Yes. Contact support@athenanetworks.com.au for:
- Deployment assistance
- Custom integration development
- Training and onboarding
- Dedicated support contracts

---

**Don't see your question?** Create an issue or email support@athenanetworks.com.au
