# Settings Page - User Guide

## Overview
The Settings page allows administrators to configure company-wide settings including white-label branding, email delivery, data retention, authentication methods, and integration defaults. Changes here affect all users and customers.

---

## Page Layout

### Header Section
- **Title**: "Company Settings" with gear icon
- **Save Button**: Apply all changes
- **Reset Button**: Discard unsaved changes

### Settings Sections
1. **Company Information**: Basic company details
2. **White-Label Branding**: Customize appearance
3. **Email Delivery**: Configure email settings
4. **Data Retention**: Manage data lifecycle
5. **Authentication**: Login methods and security
6. **Integration Defaults**: Default sync and mapping settings
7. **Report Defaults**: Default report configurations

---

## Features & How to Use

### 1. Company Information

**Purpose:** Basic company details used throughout the platform.

**Settings:**
- **Company Name**: Legal business name
  - Appears in UI header
  - Used in reports and emails
  - Example: "Territory Technology Solutions"
  
- **Contact Email**: Primary company contact
  - Used for system notifications
  - Support contact for customers
  - Example: "support@tts.com.au"
  
- **Contact Phone**: Primary phone number
  - Displayed in reports
  - Customer support line
  - Format: "+61 1300 XXX XXX"
  
- **Address**: Physical business address
  - Used in report footers
  - Legal correspondence address
  - Multi-line supported
  
- **Website URL**: Company website
  - Linked in reports and emails
  - Example: "https://www.tts.com.au"

**Steps to Update:**
1. Scroll to "Company Information" section
2. Edit desired fields
3. Click **"Save Changes"** at bottom
4. Confirmation message appears

**Permissions Required:** `company_settings.edit`

---

### 2. White-Label Branding

**Purpose:** Customize the platform's appearance to match your brand.

#### Logo Settings

**Company Logo:**
- **Format**: PNG, JPG, SVG
- **Recommended Size**: 200x60 pixels (width x height)
- **Max File Size**: 2MB
- **Usage**: 
  - Platform header
  - Report headers
  - Email templates
  - Login page

**Upload Steps:**
1. Click **"Upload Logo"** button
2. Select image file from computer
3. Preview appears
4. Click **"Save"** to apply
5. Logo updates across platform

**Favicon:**
- **Format**: ICO, PNG
- **Recommended Size**: 32x32 pixels
- **Max File Size**: 100KB
- **Usage**: Browser tab icon

---

#### Color Scheme

**Primary Color:**
- **Purpose**: Main brand color
- **Usage**:
  - Buttons and links
  - Headers and titles
  - Chart primary colors
  - Active states
- **Format**: Hex code (e.g., #1a56db)
- **Picker**: Click color box to open picker

**Secondary Color:**
- **Purpose**: Accent color
- **Usage**:
  - Secondary buttons
  - Highlights
  - Chart secondary colors
  - Hover states
- **Format**: Hex code (e.g., #7c3aed)

**Color Selection Tips:**
- Use your brand colors
- Ensure sufficient contrast for readability
- Test in both light and dark modes
- Preview before saving

---

#### Email Branding

**Email From Name:**
- **Purpose**: Sender name in customer emails
- **Example**: "Territory Technology Solutions"
- **Best Practice**: Use company name, not personal name

**Email From Address:**
- **Purpose**: Sender email address
- **Example**: "reports@tts.com.au"
- **Requirements**: 
  - Must be valid email
  - Must be configured in SMTP settings
  - Should be monitored for bounces

**Support Email:**
- **Purpose**: Customer support contact
- **Example**: "support@tts.com.au"
- **Usage**: Report footers, error messages

**Support Phone:**
- **Purpose**: Customer support phone
- **Example**: "1300 XXX XXX"
- **Usage**: Report footers, contact pages

---

#### Report Branding

**Footer Text:**
- **Purpose**: Custom footer message in reports
- **Example**: "© 2025 Territory Technology Solutions. All rights reserved."
- **Max Length**: 200 characters
- **Supports**: Plain text only

**Report Header Text:**
- **Purpose**: Custom header text in reports
- **Example**: "Monthly Service Report"
- **Max Length**: 100 characters
- **Usage**: Report title page

---

### 3. Email Delivery Settings

**Purpose:** Configure how the platform sends emails.

#### SMTP Configuration

**SMTP Host:**
- **Purpose**: Mail server address
- **Example**: "smtp.gmail.com" or "smtp.office365.com"
- **Required**: Yes

**SMTP Port:**
- **Purpose**: Mail server port
- **Common Ports**:
  - 587: TLS (recommended)
  - 465: SSL
  - 25: Unencrypted (not recommended)
- **Default**: 587

**SMTP Username:**
- **Purpose**: Email account username
- **Example**: "reports@tts.com.au"
- **Note**: Often same as email address

**SMTP Password:**
- **Purpose**: Email account password or app password
- **Security**: Encrypted in database
- **Best Practice**: Use app-specific password, not main password

**Encryption:**
- **Options**: TLS, SSL, None
- **Recommended**: TLS
- **Purpose**: Secure email transmission

**Test Email Button:**
- Click to send test email
- Verifies SMTP configuration
- Sends to your user email
- Shows success/error message

---

#### Email Defaults

**Default Reply-To:**
- **Purpose**: Where replies go
- **Example**: "support@tts.com.au"
- **Best Practice**: Use monitored inbox

**Email Signature:**
- **Purpose**: Signature appended to all emails
- **Supports**: HTML formatting
- **Example**:
  ```
  Best regards,
  Territory Technology Solutions
  Phone: 1300 XXX XXX
  Email: support@tts.com.au
  ```

---

### 4. Data Retention Policies

**Purpose:** Control how long data is stored before automatic deletion.

#### Retention Periods

**Raw Events:**
- **Purpose**: Detailed event data from integrations
- **Default**: 90 days
- **Range**: 30-365 days
- **Impact**: Longer = more storage, better historical analysis

**Aggregated Daily Data:**
- **Purpose**: Daily rollup data
- **Default**: 365 days (1 year)
- **Range**: 90-1095 days
- **Impact**: Used for monthly/quarterly reports

**Aggregated Monthly Data:**
- **Purpose**: Monthly rollup data
- **Default**: 1095 days (3 years)
- **Range**: 365-3650 days
- **Impact**: Used for yearly reports and trends

**Generated Reports:**
- **Purpose**: PDF/Excel report files
- **Default**: 1095 days (3 years)
- **Range**: 90-3650 days
- **Impact**: Storage space

**Audit Logs:**
- **Purpose**: Security and compliance logs
- **Default**: 2555 days (7 years)
- **Range**: 2555-3650 days (cannot be less than 7 years)
- **Note**: Required for ISO 27001 compliance

**Integration Sync Logs:**
- **Purpose**: Sync history and errors
- **Default**: 365 days
- **Range**: 90-730 days

---

#### Cleanup Schedule

**Nightly Cleanup Job:**
- **Runs**: Daily at 2:00 AM system time
- **Process**:
  1. Soft delete expired data (mark as deleted)
  2. Grace period: 30 days
  3. Hard delete after grace period
  4. Log all deletions to audit log

**Manual Cleanup:**
- Click **"Run Cleanup Now"** button
- Immediately processes expired data
- Shows summary of deleted records
- Use for testing or immediate cleanup

---

### 5. Authentication Settings

**Purpose:** Configure how users log in to the platform.

#### Authentication Methods

**Local Authentication:**
- **Enabled by Default**: Yes
- **Description**: Username/password login
- **Password Requirements**:
  - Minimum 8 characters
  - At least 1 uppercase letter
  - At least 1 lowercase letter
  - At least 1 number
  - At least 1 special character
- **Toggle**: Can disable if using only SSO

**Microsoft 365 SSO:**
- **Purpose**: Single sign-on with Microsoft accounts
- **Configuration**:
  - Tenant ID
  - Client ID
  - Client Secret
- **Setup**: Requires Azure AD app registration
- **Benefits**: No separate password, MFA support

**SAML SSO:**
- **Purpose**: Enterprise single sign-on
- **Configuration**:
  - Identity Provider URL
  - Certificate
  - Attribute mappings
- **Providers**: Okta, OneLogin, Auth0, etc.

**LDAP/Active Directory:**
- **Purpose**: Authenticate against on-premise AD
- **Configuration**:
  - LDAP server URL
  - Base DN
  - Bind credentials
- **Use Case**: On-premise deployments

---

#### Security Settings

**Require 2FA (Two-Factor Authentication):**
- **Options**: Optional, Required, Disabled
- **Recommended**: Required for admins, Optional for users
- **Methods**: TOTP (Google Authenticator, Authy)

**Session Timeout:**
- **Purpose**: Auto-logout after inactivity
- **Default**: 60 minutes
- **Range**: 15-480 minutes (8 hours)
- **Best Practice**: 60 minutes for security

**Password Expiry:**
- **Purpose**: Force password changes
- **Default**: 90 days
- **Range**: 30-365 days, or Never
- **Recommended**: 90 days for compliance

**Failed Login Lockout:**
- **Purpose**: Prevent brute force attacks
- **Threshold**: 5 failed attempts
- **Lockout Duration**: 30 minutes
- **Reset**: Auto-unlock or admin unlock

---

### 6. Integration Defaults

**Purpose:** Default settings for all integrations.

#### Sync Settings

**Auto-Sync Enabled:**
- **Purpose**: Automatic scheduled syncs
- **Default**: Enabled
- **Toggle**: Disable for manual-only syncs

**Sync Schedule:**
- **Purpose**: How often integrations sync
- **Default**: Every 4 hours
- **Options**: 
  - Every 1 hour
  - Every 2 hours
  - Every 4 hours
  - Every 6 hours
  - Every 12 hours
  - Daily
- **Recommendation**: Every 4 hours balances freshness and API limits

**Sync Failure Notifications:**
- **Purpose**: Email alerts when syncs fail
- **Recipients**: Comma-separated email addresses
- **Example**: "admin@tts.com.au, ops@tts.com.au"
- **Threshold**: Alert after 3 consecutive failures

---

#### Customer Mapping Defaults

**Auto-Match Enabled:**
- **Purpose**: Automatically match customers across integrations
- **Default**: Enabled
- **Recommendation**: Enable for efficiency

**Auto-Match Threshold:**
- **Purpose**: Minimum confidence score for auto-match
- **Default**: 85%
- **Range**: 70-95%
- **Impact**:
  - Higher = fewer auto-matches, more accurate
  - Lower = more auto-matches, may have errors

**Manual Match Required:**
- **Purpose**: Require admin approval for all matches
- **Default**: Disabled
- **Use Case**: High-security environments, small customer base

---

### 7. Report Defaults

**Purpose:** Default settings for report generation.

#### Report Settings

**Default Report Destination:**
- **Purpose**: Fallback email if customer email not configured
- **Example**: "reports@tts.com.au"
- **Required**: Yes

**Report Timezone:**
- **Purpose**: Default timezone for report schedules
- **Default**: Company timezone
- **Options**: All timezones
- **Recommendation**: Use company or customer timezone

**Report Format:**
- **Default Format**: PDF
- **Options**: PDF, Excel, Both
- **PDF Page Size**: A4 or Letter

**Include Raw Data:**
- **Purpose**: Include detailed data tables in reports
- **Default**: Disabled
- **Impact**: Larger file sizes

**Compress Attachments:**
- **Purpose**: ZIP reports before emailing
- **Default**: Enabled for files >5MB
- **Benefits**: Faster email delivery

---

## Saving Changes

### Save Process
1. Make desired changes in any section
2. Scroll to bottom of page
3. Click **"Save Changes"** button
4. System validates all settings
5. Confirmation message appears
6. Changes apply immediately

### Validation Errors
- Red error messages appear next to invalid fields
- Common errors:
  - Invalid email format
  - Invalid hex color code
  - Missing required fields
  - Invalid URL format
- Fix errors and save again

### Unsaved Changes Warning
- Browser warns if you navigate away with unsaved changes
- Click "Stay" to save changes
- Click "Leave" to discard changes

---

## Best Practices

✅ **DO:**
- Test SMTP settings before going live
- Use high-quality logo images
- Choose accessible color combinations
- Set appropriate data retention periods
- Enable 2FA for all users
- Monitor sync failure notifications
- Document all configuration changes

❌ **DON'T:**
- Use personal email addresses for company settings
- Choose colors with poor contrast
- Set retention periods too short (lose data)
- Disable authentication methods without testing alternatives
- Ignore sync failure notifications
- Share SMTP credentials
- Make changes without testing

---

## Permissions Reference

| Action | Required Permission |
|--------|-------------------|
| View settings | `company_settings.view` |
| Edit settings | `company_settings.edit` |
| Test SMTP | `company_settings.edit` |
| Run cleanup | `company_settings.edit` (admin) |

---

## Troubleshooting

### Logo Not Appearing
**Cause:** File too large or wrong format
**Solution:**
1. Verify file is PNG, JPG, or SVG
2. Check file size <2MB
3. Try different image
4. Clear browser cache

### SMTP Test Failing
**Cause:** Incorrect credentials or firewall
**Solution:**
1. Verify SMTP host and port
2. Check username and password
3. Ensure TLS/SSL setting matches server
4. Check firewall allows outbound SMTP
5. Try app-specific password (Gmail, Office365)

### Colors Not Updating
**Cause:** Browser cache or invalid hex code
**Solution:**
1. Verify hex code format (#RRGGBB)
2. Clear browser cache
3. Hard refresh (Ctrl+F5)
4. Try different browser

### Changes Not Saving
**Cause:** Validation errors or permissions
**Solution:**
1. Check for red error messages
2. Fix all validation errors
3. Verify user has `company_settings.edit` permission
4. Try saving again

### SSO Not Working
**Cause:** Misconfigured SSO settings
**Solution:**
1. Verify all SSO credentials
2. Check certificate is valid
3. Test with SSO provider's test tool
4. Review audit logs for errors
5. Contact SSO provider support

---

## FAQ

**Q: Do settings changes require restart?**
A: No, changes apply immediately except authentication method changes (may require re-login).

**Q: Can I preview branding changes before saving?**
A: Yes, logo and colors preview in real-time. Full preview available after saving.

**Q: What happens if I delete the company logo?**
A: Platform uses default logo until new logo uploaded.

**Q: Can I have different branding per customer?**
A: Yes, configure customer-specific branding in Customer settings (overrides company defaults).

**Q: How do I reset settings to default?**
A: Click "Reset to Defaults" button in each section. Or contact support for full reset.

**Q: Are SMTP credentials encrypted?**
A: Yes, encrypted in database using AES-256.

**Q: Can I use multiple SMTP servers?**
A: Not directly. Configure primary SMTP server. Contact support for multi-server setup.

**Q: What happens to data after retention period?**
A: Soft-deleted (marked as deleted), then hard-deleted after 30-day grace period.

**Q: Can I recover deleted data?**
A: Yes, within 30-day grace period. Contact support. After grace period, permanently deleted.

**Q: How do I enable SSO?**
A: Configure SSO settings in Authentication section. Test thoroughly before disabling local auth.

---

## Related Pages
- **Security**: Manage user passwords and API keys
- **Users**: Manage user accounts and permissions
- **Integrations**: Configure specific integration settings
- **Customers**: Customer-specific branding overrides
