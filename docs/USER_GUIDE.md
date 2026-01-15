# MSP Reports - User Guide

## Table of Contents

1. [Getting Started](#getting-started)
2. [Dashboard](#dashboard)
3. [Customers](#customers)
4. [Integrations](#integrations)
5. [Reports & Schedules](#reports--schedules)
6. [Templates](#templates)
7. [API Keys](#api-keys)
8. [User Management](#user-management)
9. [Settings](#settings)
10. [Troubleshooting](#troubleshooting)

---

## Getting Started

### Logging In

MSP Reports supports multiple authentication methods:

- **Local Authentication**: Email and password
- **SSO (SAML)**: Enterprise single sign-on via Keycloak/Azure AD
- **OAuth2 (OIDC)**: Microsoft/Keycloak authentication

Contact your administrator if you don't have login credentials.

### First-Time Setup

1. Log in with your admin credentials
2. Navigate to **Settings > Company** to configure branding
3. Add your first integration under **Integrations**
4. Map customers from your integration to the platform
5. Create report schedules for your customers

---

## Dashboard

The dashboard provides an overview of:

- **Recent Reports**: Latest generated reports
- **Integration Status**: Health of connected integrations
- **Upcoming Schedules**: Reports scheduled to generate soon
- **Quick Actions**: Common tasks like generating reports

---

## Customers

### Viewing Customers

Navigate to **Customers** to see all mapped customers. Each customer card shows:

- Customer name
- Mapped integrations
- Active report schedules
- Last report generated

### Customer Mapping

When integrations sync, they bring in customer records. These need to be mapped to unified customer records:

1. Go to **Customers > Unmapped**
2. Review suggested matches (auto-matching uses fuzzy name matching)
3. Click **Approve** for correct matches
4. Click **Create New** for genuinely new customers
5. Click **Ignore** for non-customers (vendors, etc.)

### Source of Truth

Designate one integration as the "source of truth" for customer names:

1. Go to **Integrations**
2. Click on the integration
3. Enable **Source of Truth**

This integration's customer names become canonical.

---

## Integrations

### Supported Integrations

- **Autotask PSA**: Tickets, time entries, contracts
- **Active Directory**: Users, groups, SPLA licensing
- **Exchange**: Mailboxes, permissions, storage
- **ITGlue**: Documentation, configurations

### Adding an Integration

1. Go to **Integrations > Add Integration**
2. Select the integration type
3. Enter credentials (API key, URL, etc.)
4. Click **Test Connection**
5. If successful, click **Save**

### Syncing Data

Integrations sync automatically on a schedule. To manually sync:

1. Go to **Integrations**
2. Click the **Sync** button on the integration card
3. View sync progress in the status indicator

### Historical Import

For new integrations, you can import historical data:

1. After adding integration, click **Import Historical Data**
2. Select date range (up to your retention limit)
3. Click **Start Import**
4. Monitor progress in the integration details

---

## Reports & Schedules

### Report Types

- **Monthly Summary**: Overview of all services
- **SPLA Licensing**: Microsoft license usage
- **Backup Status**: Backup success/failure rates
- **Ticket Summary**: Support ticket metrics

### Creating a Schedule

1. Go to **Schedules > Create Schedule**
2. Select **Customer**
3. Select **Report Type**
4. Configure **Frequency**:
   - Daily (select days of week)
   - Weekly (select day)
   - Monthly (select day of month)
   - Yearly (select month and day)
5. Set **Time of Day**
6. Configure **Delivery** (email recipients)
7. Click **Save**

### Manual Report Generation

To generate a report immediately:

1. Go to **Reports > Generate**
2. Select customer and report type
3. Select date range
4. Click **Generate**
5. Download or email the report

### Viewing Generated Reports

1. Go to **Reports > History**
2. Filter by customer, date, or type
3. Click on a report to view/download

---

## Templates

Templates allow you to configure report settings once and apply to multiple customers.

### Creating a Template

1. Go to **Templates > Create Template**
2. Enter template name
3. Select report type
4. Configure schedule settings
5. Configure report options
6. Click **Save**

### Applying Templates

1. Go to **Templates**
2. Click on a template
3. Click **Apply to Customers**
4. Select customers
5. Click **Apply**

### Template vs Custom Schedules

- **Template-linked schedules**: Update automatically when template changes
- **Customized schedules**: Marked as customized, won't update with template

To customize a schedule:
1. Edit the schedule directly
2. It will be marked as "customized"
3. Template updates will skip this schedule

To reset to template:
1. Edit the schedule
2. Click **Reset to Template**

---

## API Keys

API keys allow programmatic access for agents and integrations.

### Creating an API Key

1. Go to **Settings > API Keys**
2. Click **Create API Key**
3. Enter a **Display Name** (e.g., "TTS AD Agent")
4. Optionally select a **Customer Scope** (for agents)
5. Configure **Permissions**
6. Optionally add **IP Whitelist**
7. Click **Create**
8. **IMPORTANT**: Copy the key immediately - it's shown only once!

### Customer-Scoped Keys

For AD/Exchange agents, create customer-scoped keys:

- Select the customer the agent will sync data for
- The key can only access that customer's data
- Required for the PowerShell sync agents

### Revoking Keys

1. Go to **Settings > API Keys**
2. Find the key
3. Click **Revoke**
4. Confirm revocation

Revoked keys stop working immediately.

---

## User Management

### User Roles

| Role | Description |
|------|-------------|
| **Admin** | Full access to everything |
| **Manager** | Report configuration, customer management |
| **Technician** | Integration management, syncing |
| **Viewer** | Read-only report access |
| **Customer** | Access to own reports only |

### Adding Users

1. Go to **Settings > Users**
2. Click **Add User**
3. Enter name and email
4. Select role
5. Configure custom permissions (optional)
6. Click **Create**

### Editing Permissions

1. Go to **Settings > Users**
2. Click on a user
3. Click **Edit Permissions**
4. Toggle individual permissions
5. Click **Save**

---

## Settings

### Company Settings

- **Company Name**: Your organization name
- **Logo**: Upload your logo for branding
- **Primary Color**: Brand color for reports
- **Contact Info**: Support email and phone

### White-Label Branding

Reports and emails use your company branding:

1. Go to **Settings > Branding**
2. Upload logo
3. Set colors
4. Configure email sender name/address
5. Preview and save

### Data Retention

Configure how long data is kept:

1. Go to **Settings > Data Retention**
2. Set retention periods for:
   - Raw integration data
   - Generated reports
   - Audit logs (minimum 7 years)
3. Click **Save**

---

## Troubleshooting

### Integration Not Syncing

1. Check integration status in **Integrations**
2. View sync logs for errors
3. Verify credentials are still valid
4. Check rate limiting status
5. Try manual sync

### Reports Not Generating

1. Check schedule is active
2. Verify customer has required data
3. Check audit logs for errors
4. Ensure email delivery is configured

### API Key Not Working

1. Verify key was copied correctly
2. Check if key is revoked
3. Verify IP whitelist (if configured)
4. Check permissions include required actions

### SSO Login Issues

1. Verify SSO is configured in company settings
2. Check IdP metadata is current
3. Verify user exists in both systems
4. Check SAML response for errors

### Contact Support

If issues persist, contact your administrator or Athena Networks support.

---

## Keyboard Shortcuts

| Shortcut | Action |
|----------|--------|
| `Ctrl+K` | Quick search |
| `Ctrl+N` | New report |
| `Escape` | Close modal |

---

## Glossary

- **Integration**: Connection to external system (Autotask, AD, etc.)
- **Customer Mapping**: Linking integration customers to unified records
- **Schedule**: Automated report generation configuration
- **Template**: Reusable schedule configuration
- **SPLA**: Service Provider License Agreement (Microsoft licensing)
- **SSO**: Single Sign-On
- **SAML**: Security Assertion Markup Language (SSO protocol)
