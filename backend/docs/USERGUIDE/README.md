# User Guide Documentation

## Overview
This directory contains comprehensive user guides for every major page in the reporting platform. Each guide provides detailed instructions, best practices, troubleshooting tips, and FAQ sections.

---

## Available User Guides

### 1. [Integrations Page](./IntegrationsPage.md)
**Purpose:** Connect and manage external systems (PSA, RMM, security tools)

**Key Topics:**
- Adding new integrations (Datto, Autotask, ITGlue, etc.)
- Syncing integration data
- Setting Master Customer List
- Historical data import
- Viewing sync logs and health checks
- Rate limit monitoring
- Troubleshooting sync failures

**Target Users:** Administrators, Integration Managers

---

### 2. [Customers Page](./CustomersPage.md)
**Purpose:** Unified customer management and mapping across integrations

**Key Topics:**
- Customer mapping (auto and manual)
- Viewing customer details
- Editing customer information
- Setting up report schedules
- Viewing report history
- Merging duplicate customers
- Splitting incorrectly merged customers
- Viewing integration data

**Target Users:** All users with customer access

---

### 3. [Reports Page](./ReportsPage.md)
**Purpose:** View, download, and manage generated reports

**Key Topics:**
- Viewing all generated reports
- Filtering by customer, status, type, date
- Downloading reports (PDF/Excel)
- Resending reports via email
- Deleting old reports
- Understanding report status indicators
- Troubleshooting failed reports
- Report branding

**Target Users:** All users with report access

---

### 4. [Schedules Page](./SchedulesPage.md)
**Purpose:** Configure when and how reports are automatically generated

**Key Topics:**
- Creating new schedules
- Configuring frequencies (Daily, Weekly, Monthly, Yearly, On-Demand)
- Editing schedules
- Pausing and resuming schedules
- Generating reports manually
- Configuring email delivery
- Timezone handling
- Preview next generations
- Troubleshooting schedule issues

**Target Users:** Administrators, Report Managers

---

### 5. [Settings Page](./SettingsPage.md)
**Purpose:** Company-wide configuration and white-label branding

**Key Topics:**
- Company information
- White-label branding (logo, colors, email templates)
- Email delivery (SMTP configuration)
- Data retention policies
- Authentication methods (Local, SSO, SAML, LDAP)
- Integration defaults
- Report defaults
- Security settings

**Target Users:** Administrators only

---

### 6. [Security Page](./SecurityPage.md)
**Purpose:** Personal account security management

**Key Topics:**
- Changing password
- Enabling/disabling 2FA (Two-Factor Authentication)
- Managing API keys
- Viewing active sessions
- Revoking sessions
- Security log (activity history)
- Suspicious activity alerts
- Security best practices
- Incident response

**Target Users:** All users (personal security)

---

## Quick Reference

### Common Tasks by Role

#### Administrator
1. **Initial Setup**:
   - Settings → Configure company information and branding
   - Integrations → Add first integration (Datto/Autotask)
   - Integrations → Set Master Customer List
   - Integrations → Import historical data

2. **Daily Operations**:
   - Customers → Review and map unmapped customers
   - Reports → Monitor failed reports
   - Integrations → Check sync status

3. **Maintenance**:
   - Settings → Review data retention
   - Security → Monitor security logs
   - Schedules → Verify schedules are running

---

#### Report Manager
1. **Setup Reports**:
   - Customers → Find customer
   - Customers → Setup Reports
   - Schedules → Create schedule

2. **Monitor Reports**:
   - Reports → Filter by status
   - Reports → Download and verify
   - Schedules → Check success rates

3. **Troubleshooting**:
   - Reports → Investigate failed reports
   - Schedules → Generate Now to test
   - Integrations → Verify data is syncing

---

#### Standard User
1. **View Reports**:
   - Reports → Filter by customer
   - Reports → Download reports
   - Reports → Resend if needed

2. **Customer Information**:
   - Customers → View customer details
   - Customers → View integration data
   - Customers → View report history

3. **Personal Security**:
   - Security → Enable 2FA
   - Security → Change password regularly
   - Security → Review active sessions

---

## Getting Started Guide

### For New Users

1. **First Login**:
   - Login with credentials provided by administrator
   - Security → Change password immediately
   - Security → Enable 2FA (highly recommended)

2. **Familiarize Yourself**:
   - Read user guide for your role
   - Explore the interface
   - Ask questions if unclear

3. **Start Working**:
   - Follow common tasks for your role
   - Refer to user guides as needed
   - Contact administrator for help

---

### For New Administrators

1. **Week 1 - Initial Setup**:
   - Settings → Configure company information
   - Settings → Upload logo and set colors
   - Settings → Configure SMTP for email delivery
   - Integrations → Add first integration
   - Integrations → Test sync

2. **Week 2 - Customer Mapping**:
   - Customers → Review unmapped customers
   - Customers → Map customers (auto and manual)
   - Integrations → Set Master Customer List
   - Integrations → Import historical data (90 days)

3. **Week 3 - Reports Setup**:
   - Customers → Setup reports for first customer
   - Schedules → Create test schedule
   - Schedules → Generate Now to test
   - Reports → Verify report quality

4. **Week 4 - Rollout**:
   - Schedules → Create schedules for all customers
   - Reports → Monitor first scheduled generations
   - Troubleshoot any issues
   - Document your configuration

5. **Ongoing**:
   - Weekly: Review unmapped customers, failed reports
   - Monthly: Check integration health, review security logs
   - Quarterly: Review data retention, update branding

---

## Permissions Overview

### Permission Levels

| Permission | View | Edit | Manage | Sync | Configure | Generate | Download | Delete |
|------------|------|------|--------|------|-----------|----------|----------|--------|
| **Integrations** | ✓ | - | ✓ | ✓ | - | - | - | - |
| **Customers** | ✓ | ✓ | - | - | - | - | - | - |
| **Reports** | ✓ | - | - | - | ✓ | ✓ | ✓ | ✓ |
| **Users** | ✓ | - | ✓ | - | - | - | - | - |
| **Company Settings** | ✓ | ✓ | - | - | - | - | - | - |

### Role Templates

**Administrator:**
- All permissions enabled
- Can manage users and settings
- Full access to all features

**Report Manager:**
- View/configure/generate/download reports
- View/edit customers
- View integrations
- Cannot manage users or settings

**Standard User:**
- View reports and download
- View customers
- View integrations
- Cannot edit or configure

**Read-Only User:**
- View-only access to all features
- Cannot edit, configure, or manage anything
- Useful for executives or auditors

---

## Support & Resources

### Getting Help

1. **User Guides**: Read relevant guide for your task
2. **FAQ Sections**: Check FAQ in each guide
3. **Troubleshooting**: Follow troubleshooting steps
4. **Administrator**: Contact your company administrator
5. **Support**: Email support@yourcompany.com (if available)

### Additional Documentation

- **API Documentation**: For developers integrating with platform
- **Integration Endpoints**: Technical details for each integration type
- **Artisan Commands**: CLI commands for administrators
- **Backup Guide**: Backup and disaster recovery procedures

### Training Resources

- **Video Tutorials**: (If available) Step-by-step video guides
- **Webinars**: Live training sessions for new features
- **Knowledge Base**: Searchable articles and guides
- **Release Notes**: What's new in each version

---

## Document Maintenance

### Version History

| Version | Date | Changes | Author |
|---------|------|---------|--------|
| 1.0 | 2025-12-10 | Initial user guide documentation | System |

### Contributing

If you find errors or have suggestions for improvements:
1. Document the issue or suggestion
2. Contact your administrator
3. Administrator can submit feedback to support

### Updates

These guides are updated with each major platform release. Check the version history to see what's new.

---

## Quick Links

- [Integrations Page Guide](./IntegrationsPage.md)
- [Customers Page Guide](./CustomersPage.md)
- [Reports Page Guide](./ReportsPage.md)
- [Schedules Page Guide](./SchedulesPage.md)
- [Settings Page Guide](./SettingsPage.md)
- [Security Page Guide](./SecurityPage.md)

---

## Glossary

**Integration**: External system connected to platform (Datto, Autotask, etc.)

**Customer Mapping**: Linking customers from different integrations to unified record

**Master Customer List**: Integration designated as authoritative source for customer names

**Report Schedule**: Configuration defining when reports generate automatically

**Historical Import**: Importing past data from integrations for trend analysis

**2FA**: Two-Factor Authentication - extra security layer requiring code from app

**API Key**: Token for programmatic access to platform

**White-Label**: Customizing platform appearance with your company branding

**Sync**: Pulling latest data from integration

**Rate Limit**: Maximum number of API requests allowed per time period

**Retention Policy**: How long data is stored before automatic deletion

**Audit Log**: Record of all actions for compliance and security

---

## Contact Information

**Platform Support**: support@yourcompany.com
**Administrator**: [Your Company Admin Email]
**Emergency**: [Emergency Contact if applicable]

---

*Last Updated: December 10, 2025*
