# Multi-Tenant Reporting Platform - Project Plan

## Project Purpose & Expected Outcome

We're building a centralised reporting platform that solves a critical pain point for MSPs and telcos: fragmented customer data across multiple systems. This platform will allow our clients to integrate various tools (Autotask, DattoRMM, etc.), intelligently map customers across these systems despite naming mismatches, and automatically generate and distribute customised monthly reports to their customers. The end result is a white-label solution that saves our clients countless hours of manual report compilation while providing their customers with professional, consistent reporting. By making this multi-tenanted with flexible authentication and modular integration framework, we're building a scalable product that can grow with customer needs and rapidly add new integrations and report types.

---

## Technical Stack
- **Frontend**: React
- **Backend**: Laravel
- **Database**: PostgreSQL with encryption at rest
- **Authentication**: Local, SAML, M365, Active Directory/LDAP support
- **Compliance**: ISO 27001 SoC compliant

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

## Development Phases

1. **Foundation** - Auth system, multi-tenancy, database schema
2. **Integration Framework** - Build modular integration system, implement first integration (Autotask)
3. **Customer Mapping** - Auto-match logic, manual mapping UI
4. **Report Framework** - Build modular report system, implement first report type
5. **Scheduling & Delivery** - Report generation queue, email delivery
6. **Polish** - UI refinement, additional integrations/reports, testing

---

**Questions or need clarification on any component before starting?**