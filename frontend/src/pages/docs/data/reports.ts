import type { DocCategory } from '../types'

export const reportsCategory: DocCategory = {
  id: 'reports',
  title: 'Reports',
  description: 'Generate, schedule, and manage customer reports',
  icon: 'FileText',
  articles: [
    {
      id: 'reports-overview',
      title: 'Reports Overview',
      description: 'Understanding the reporting system',
      icon: 'FileText',
      content: `
# Reports Overview

The reporting system generates professional, branded reports for your customers.

## What Are Reports?

Reports are formatted documents presenting data from your integrations:
- **Service summaries** - Tickets, response times, resolutions
- **System health** - Uptime, alerts, performance metrics
- **Backup status** - Success rates, protected data
- **Security summaries** - Threats detected, patches applied
- **Microsoft 365 Summary** - Users, MFA, licenses, Defender, conditional access, email protection (via CIPP)
- **Network & Infrastructure** - Device health, uptime, interface statistics (via LibreNMS)

## Report Lifecycle

### 1. Configuration
Set up which reports to generate for each customer.

### 2. Scheduling
Define when reports should generate (daily, weekly, monthly).

### 3. Generation
System gathers data, applies template, generates PDF.

### 4. Delivery
Reports delivered via email or available for download.

### 5. Storage
Generated reports stored and accessible in history.

## Report Features

### White-Label Branding
- Company logo and colours
- Contact information
- Custom footer text

### Customer Branding
- Customer-specific logo (optional)
- Custom report title
- Personalised notes

### Data Visualisation
- Charts and graphs
- Status indicators
- Trend lines

## Accessing Reports

### Reports List
Navigate to **Reports** to see all generated reports.

### Customer Reports
From a customer's detail page, view reports for that customer.
      `,
      relatedArticles: ['generating-reports', 'report-schedules', 'report-configuration']
    },
    {
      id: 'generating-reports',
      title: 'Generating Reports',
      description: 'How to generate reports manually and automatically',
      icon: 'Play',
      content: `
# Generating Reports

Reports can be generated automatically via schedules or manually.

## Manual Report Generation

### From the Reports Page
1. Navigate to **Reports**
2. Click **Generate Report**
3. Select customer, report type, and date range
4. Click **Generate**

### From Customer Details
1. Go to customer detail page
2. Click **Generate Report**
3. Select report type and date range
4. Click **Generate**

### Preview Before Generating
Before generating a report, you can preview the data:
1. Click **Preview** instead of Generate
2. Review the data that will be included
3. Verify accuracy and completeness
4. If satisfied, proceed to generate
5. Export preview to PDF if needed

This helps prevent generating reports with incorrect or missing data.

### Generation Process
1. Report enters the queue
2. System gathers required data
3. Template is rendered
4. PDF is generated
5. Report appears in history
6. Delivery triggered (if configured)

## Automatic Generation

Reports generate automatically based on schedules:
1. You configure a schedule
2. System checks schedules hourly
3. When due, generation triggers
4. Report generates and delivers automatically

## Monitoring Generation

### Generation Status
- **Queued** - Waiting to process
- **Generating** - Currently being created
- **Completed** - Successfully generated
- **Failed** - Error during generation

### Handling Failures
1. Check the error message
2. Address the underlying issue
3. Retry generation

## Report Output

### PDF Format
- Professional formatted document
- Includes charts and tables
- Branded with your company info

### Viewing Reports
1. Find the report in the list
2. Click **View** or **Download**
3. PDF opens or downloads

### Folder View
Organise reports by customer:
1. Toggle to **Folders** view in the Reports page
2. Reports are grouped by customer name as folder icons
3. Click a folder to see all reports for that customer
4. Use search to filter folders by customer name

### Bulk Generation
Generate reports for multiple customers at once:
1. Click **Bulk Generate** in the Reports page
2. Select customers and report types
3. Choose the date range
4. Click **Generate All** — reports queue in the background

### Secure Sharing
Share reports via secure links:
1. Click **Share** on any report
2. A unique secure link is generated
3. Share with your customer — no login required
4. Links can have optional expiry dates
5. All access is tracked in the audit log
      `,
      relatedArticles: ['reports-overview', 'report-schedules', 'report-delivery']
    },
    {
      id: 'report-schedules',
      title: 'Report Schedules',
      description: 'Setting up automatic report generation schedules',
      icon: 'Calendar',
      content: `
# Report Schedules

Schedules automate report generation so reports are created without manual intervention.

## Accessing Schedules

Navigate to **Schedules** in the sidebar.

## Schedule List

Shows:
- **Customer** - Who the report is for
- **Report Type** - What kind of report
- **Frequency** - How often it runs
- **Next Run** - When it will next generate
- **Status** - Active or paused

## Creating a Schedule

### Step 1: Start New Schedule
Click **Add Schedule**.

### Step 2: Select Customer
Choose the customer for this schedule.

### Step 3: Select Report Type
Choose which report to generate.

### Step 4: Configure Frequency

**Daily:**
- Runs every day or selected days
- Choose days of week
- Set time of day

**Weekly:**
- Runs once per week
- Select day of week
- Set time of day

**Monthly:**
- Runs once per month
- Select day of month
- Set time of day

**Yearly:**
- Runs once per year
- Select month and day
- Set time of day

### Step 5: Save
Click **Save Schedule** to activate.

## Managing Schedules

### Editing
Click **Edit** to modify settings.

### Pausing
Click **Pause** to temporarily stop. Click **Resume** to reactivate.

### Deleting
Click **Delete** to remove. Historical reports are preserved.

## Best Practices

### Timing Recommendations
- **Monthly reports** - 1st of month, morning
- **Weekly reports** - Monday morning
- **Daily reports** - Early morning

### Avoid Peak Times
Spread schedules throughout the day.
      `,
      relatedArticles: ['reports-overview', 'generating-reports', 'report-delivery']
    },
    {
      id: 'report-delivery',
      title: 'Report Delivery',
      description: 'How reports are delivered to customers',
      icon: 'Send',
      content: `
# Report Delivery

Once reports are generated, they're delivered according to your configuration.

## Delivery Methods

### Email Delivery
- Report attached as PDF
- Branded email template
- Multiple recipients supported

### Platform Download
Reports always available in the platform for download.

## Email Configuration

### Per-Customer Settings
- **Primary Email** - Main recipient
- **CC Emails** - Additional recipients
- **Custom Subject** - Override default subject

### Email Content
- Your company branding
- Report summary
- Attached PDF
- Contact information

## Delivery Status

### Status Indicators
- **Pending** - Queued for delivery
- **Sent** - Successfully delivered
- **Failed** - Delivery failed
- **Bounced** - Email bounced back

### Viewing Status
1. Go to **Reports**
2. Find the report
3. Check **Delivery Status** column

## Handling Delivery Failures

### Common Reasons
- Invalid email address
- Mailbox full
- Spam filter rejection

### Resolving Failures
1. Check the failure reason
2. Correct the issue
3. Resend the report

### Resending Reports
1. Find the report
2. Click **Resend**
3. Optionally modify recipients
4. Confirm resend

## Best Practices

- Verify customer email addresses
- Use distribution lists for multiple recipients
- Review delivery status regularly
      `,
      relatedArticles: ['reports-overview', 'generating-reports', 'report-configuration']
    },
    {
      id: 'report-configuration',
      title: 'Report Configuration',
      description: 'Configuring report types and settings',
      icon: 'Settings',
      content: `
# Report Configuration

Configure which reports to generate and how they should be formatted.

## Report Types

### Available Reports

| Report Type | Required Integration | Description |
|-------------|---------------------|-------------|
| **Autotask Monthly Summary** | Autotask | Tickets, time entries, billing, and contracts |
| **Microsoft 365 Summary** | CIPP | Users, MFA, licenses, security, Defender, conditional access, email |
| **Datto RMM Summary** | Datto RMM | Device health, alerts, patch status, endpoint monitoring |
| **BitDefender Summary** | BitDefender | Endpoint protection, threat detections, security posture |
| **ThreatLocker Summary** | ThreatLocker | Application whitelisting, endpoint inventory, audit events |
| **Vonahi Summary** | Vonahi | Pen test assessments, vulnerability findings, agent status |
| **SaaS Alerts Summary** | SaaS Alerts | Cloud security alerts, user behaviour, compliance |
| **Sendmarc Summary** | Sendmarc | Email authentication (DMARC/SPF/DKIM), domain compliance |
| **IT Glue Documentation** | ITGlue | Configuration items, password updates, document changes |
| **Combined Monthly Report** | Autotask + optional others | All-in-one report from multiple integrations |

### Report Requirements
Each report type requires:
- The **required integration** connected and actively syncing
- Relevant data synced for the reporting period
- Customer properly mapped to integration customers

## Configuring Customer Reports

### Accessing Configuration
1. Go to **Customers > Master List**
2. Click on a customer
3. Click **Configure Reports**

### Enabling Report Types
1. View available report types
2. Toggle on desired reports
3. Configure options for each

## Branding Configuration

### Company Branding
Set in company settings:
- Logo
- Primary/secondary colours
- Contact information
- Footer text

### Customer Branding
Optional per-customer overrides:
- Customer logo
- Custom report title
- Personalised notes

## Delivery Configuration

### Email Settings
- Primary recipient
- CC recipients
- Custom subject line

## Best Practices

### Start Simple
Enable core reports first, add more as needed.

### Match to Service
Enable reports relevant to services provided.

### Test First
Generate test reports before scheduling.
      `,
      relatedArticles: ['reports-overview', 'generating-reports', 'report-schedules']
    }
  ]
}
