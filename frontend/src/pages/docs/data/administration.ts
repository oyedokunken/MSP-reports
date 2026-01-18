import type { DocCategory } from '../types'

export const administrationCategory: DocCategory = {
  id: 'administration',
  title: 'Administration',
  description: 'System administration and settings',
  icon: 'Settings',
  articles: [
    {
      id: 'user-management',
      title: 'User Management',
      description: 'Managing users and their access',
      icon: 'UserPlus',
      content: `
# User Management

Manage who can access the platform and what they can do.

## Accessing User Management

Navigate to **Admin > Users** (requires user management permission).

## User List

Shows:
- **Name** - User's full name
- **Email** - Login email address
- **Permissions** - Access level summary
- **Status** - Active, inactive, or locked
- **Last Login** - Most recent access

## Creating Users

### Step 1: Start Creation
Click **Add User** or **Create User**.

### Step 2: Enter Details
- **Name** - User's full name
- **Email** - Must be unique, used for login
- **Password** - Set initial password or send invite

### Step 3: Set Permissions
Configure access:
- **Integrations** - View, manage, sync
- **Customers** - View, edit, map
- **Reports** - View, configure, generate, download
- **Users** - View, manage
- **Company Settings** - View, edit

### Step 4: Save
Click **Create User** to save.

## Editing Users

1. Find the user in the list
2. Click **Edit**
3. Update information or permissions
4. Save changes

## User Status

- **Active** - Can log in and use the platform
- **Inactive** - Cannot log in, account preserved
- **Locked** - Temporarily blocked

## Password Management

### Reset Password
1. Find the user
2. Click **Reset Password**
3. Set new password or send reset email

## Deactivating Users

1. Find the user
2. Click **Deactivate**
3. Confirm deactivation
4. User immediately loses access

Historical actions are preserved in audit logs.

## Best Practices

- Give users only permissions they need
- Review permissions regularly
- Deactivate unused accounts
      `,
      relatedArticles: ['user-roles', 'security-settings', 'api-keys']
    },
    {
      id: 'api-keys',
      title: 'API Keys',
      description: 'Managing API keys for programmatic access',
      icon: 'Key',
      content: `
# API Keys

API keys allow programmatic access to the platform.

## What Are API Keys?

API keys are credentials for:
- Automated systems
- Custom integrations
- External applications
- Scripted operations

## Accessing API Key Management

Navigate to **Admin > API Keys**.

## Creating API Keys

### Step 1: Start Creation
Click **Create API Key**.

### Step 2: Set Details
- **Name** - Descriptive name
- **Description** - What it's used for

### Step 3: Configure Permissions
Set what the key can access (same categories as users).

### Step 4: Optional Restrictions
- **IP Whitelist** - Only allow from specific IPs
- **Endpoint Restrictions** - Limit to specific API endpoints

### Step 5: Generate
Click **Generate Key**.

### Step 6: Copy Key
**IMPORTANT:** The key is shown only once!
- Copy it immediately
- Store securely
- Cannot be retrieved later

## Using API Keys

Include in API requests:
\`\`\`
Authorization: Bearer {api_key}
\`\`\`

## Managing API Keys

### Viewing Details
Click on a key to see permissions and usage statistics.

### Revoking Keys
1. Find the key
2. Click **Revoke**
3. Confirm - key immediately stops working

## Security Best Practices

- Never share keys in code repositories
- Use environment variables
- Rotate keys periodically (every 6-12 months)
- Use separate keys for different systems
- Only grant needed permissions
      `,
      relatedArticles: ['user-management', 'security-settings', 'monitoring']
    },
    {
      id: 'company-settings',
      title: 'Company Settings',
      description: 'Configuring company-wide settings',
      icon: 'Building',
      content: `
# Company Settings

Configure platform-wide settings for your organisation.

## Accessing Settings

Navigate to **Admin > Settings**.

## General Settings

### Company Information
- **Company Name** - Your organisation name
- **Contact Email** - Primary contact
- **Support Email** - For customer support queries
- **Phone Number** - Contact phone

### Timezone
Set your company's timezone for schedules and timestamps.

## Branding Settings

### Logo
Upload your company logo for platform header and reports.

### Colours
- **Primary Colour** - Main brand colour
- **Secondary Colour** - Accent colour

### Favicon
Small icon for browser tabs.

## Email Settings

### From Address
- **From Name** - Sender name on emails
- **From Email** - Sender email address

## Integration Settings

### Source of Truth
Designate primary integration for customer creation.

### Sync Settings
- Default sync frequency
- Failure notification emails

## Customer Mapping Settings

### Auto-Match
- Enable/disable auto-matching
- Set confidence threshold

## Report Settings

### Default Settings
- Default report timezone
- Default retention period

### Format Options
- PDF page size
- Compression settings

## Data Retention

Configure how long data is kept:
- Raw integration data
- Generated reports
- Sync logs
      `,
      relatedArticles: ['user-management', 'branding-settings', 'security-settings']
    },
    {
      id: 'security-settings',
      title: 'Security Settings',
      description: 'Configuring security options and policies',
      icon: 'Shield',
      content: `
# Security Settings

Configure security policies to protect your platform and data.

## Authentication Settings

### Password Policy
- **Minimum Length** - Recommended: 8+ characters
- **Require Uppercase** - At least one capital letter
- **Require Numbers** - At least one digit
- **Require Special Characters** - At least one symbol

### Single Sign-On (SSO)
The platform supports multiple SSO providers:
- **Microsoft 365** - Azure AD integration
- **Keycloak** - OpenID Connect with PKCE
- **SAML 2.0** - Enterprise identity providers
- **LDAP** - Active Directory authentication

Contact your administrator to configure SSO for your organisation.

### Two-Factor Authentication (2FA)
- **Available** - Users can enable 2FA
- **Required** - All users must use 2FA

### Session Management
- **Session Timeout** - Auto-logout after inactivity
- **Concurrent Sessions** - Allow multiple logins

## Access Controls

### IP Whitelisting
Restrict access to specific IP addresses.

### Failed Login Protection
- **Lockout Threshold** - Lock after X failed attempts
- **Lockout Duration** - How long account is locked

## Audit Logging

### What's Logged
- User logins and logouts
- Permission changes
- Data access
- Configuration changes
- Report generation

### Viewing Audit Logs
1. Go to **Admin > Audit Logs**
2. Filter by date range, user, or event type
3. Search for specific actions
4. Click on entries for full details

### Log Retention
Audit logs kept for 7 years (compliance requirement).

## Data Security

### Encryption
- **In Transit** - All connections use TLS
- **At Rest** - Database encryption enabled
- **Credentials** - Integration credentials encrypted

## Best Practices

### For Administrators
1. Enable 2FA for all admin accounts
2. Use strong, unique passwords
3. Review user access regularly
4. Monitor audit logs

### For Users
1. Enable 2FA on your account
2. Don't share credentials
3. Log out when finished
4. Report suspicious activity
      `,
      relatedArticles: ['user-management', 'company-settings', 'api-keys']
    },
    {
      id: 'monitoring',
      title: 'System Monitoring',
      description: 'Monitoring system health and activity',
      icon: 'Activity',
      content: `
# System Monitoring

Monitor the health and activity of your platform.

## Accessing Monitoring

Navigate to **Admin > Monitoring**.

## Dashboard Overview

### System Health
- **Overall Status** - Green/Yellow/Red
- **Integration Health** - All integrations status
- **Queue Status** - Background job processing

### Key Metrics
- Active integrations
- Recent sync success rate
- Reports generated today
- Active users

## Integration Monitoring

### Integration Status
For each integration:
- **Status** - Connected, error, syncing
- **Last Sync** - When it last synced
- **Success Rate** - Recent sync success percentage

### Sync History
View detailed sync logs with timestamps, duration, and errors.

## Report Monitoring

### Generation Stats
- Reports generated (today/week/month)
- Success rate
- Average generation time
- Failed reports

### Delivery Stats
- Delivery success rate
- Bounce rate
- Failed deliveries

## Audit Logs

### Viewing Logs
1. Select **Audit Logs** tab
2. Filter by date range
3. Filter by user or action type
4. Search for specific events

### Log Details
Each log entry shows:
- Timestamp
- User/actor
- Action performed
- Target resource
- IP address

### Exporting Logs
Export logs for compliance or analysis.

## Alerts

### Alert Types
- **Critical** - Immediate attention required
- **Warning** - Review within 4 hours
- **Info** - Review daily

### Common Alerts
- Integration sync failures
- Report generation failures
- Security events
- System health issues

## Best Practices

- Review monitoring dashboard daily
- Address alerts promptly
- Export audit logs periodically
- Track trends over time
      `,
      relatedArticles: ['security-settings', 'integration-troubleshooting', 'company-settings']
    }
  ]
}
