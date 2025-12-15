# Reports Page - User Guide

## Overview
The Reports page displays all generated reports across all customers. You can view, download, resend, and delete reports. Reports are automatically generated based on schedules configured in the Customers or Schedules pages.

---

## Page Layout

### Header Section
- **Title**: "Generated Reports" with document icon
- **Customer Filter** (if viewing specific customer):
  - Customer name
  - "View All Reports" button to see all customers
- **Statistics**:
  - Total reports generated
  - Success rate
  - Failed reports (needs attention)

### Filter Bar
- **Search**: Filter by customer name or report type
- **Status Filter**: All, Completed, Failed, Pending
- **Report Type Filter**: Filter by specific report type
- **Date Range Picker**: Filter by generation date

### Reports Grid
Each report card displays:
- **Report Type Icon**: Visual identifier for report type
- **Report Title**: Customer name + Report type
- **Generation Date**: When report was created
- **Status Badge**:
  - ✅ Completed (green)
  - ❌ Failed (red)
  - ⏳ Pending (yellow)
  - 📧 Delivered (blue)
- **File Size**: Size of generated PDF/Excel
- **Delivery Status**: Email sent, Downloaded, Not delivered
- **Action Buttons**: Download, Resend, Delete

---

## Features & How to Use

### 1. View All Reports

**Default View:**
- Shows all reports across all customers
- Sorted by generation date (newest first)
- Paginated (20 reports per page)

**Navigation:**
- Use pagination controls at bottom
- Scroll through pages
- Jump to specific page number

**Permissions Required:** `reports.view`

---

### 2. Filter Reports by Customer

**Steps:**
1. Click customer name in filter bar
2. Or navigate from Customers page → "View History"
3. See only reports for selected customer
4. Click **"View All Reports"** to return to full list

**Use Cases:**
- Review specific customer's reports
- Verify customer is receiving reports
- Troubleshoot customer-specific issues

---

### 3. Download Report

**Steps:**
1. Locate report card
2. Click **"Download"** button (download icon)
3. File downloads to your browser's download folder
4. Open with PDF viewer or Excel

**File Formats:**
- **PDF**: Most report types (formatted, branded)
- **Excel**: Data-heavy reports (license consumption, etc.)

**What's Included:**
- Customer branding (logo, colors)
- Report data for specified period
- Charts and visualizations
- Summary statistics
- Detailed data tables

**Permissions Required:** `reports.download`

---

### 3a. Quick Share Link

**Purpose:** Generate a time-limited link to allow downloading a completed report without requiring the recipient to log in.

**Steps:**
1. Locate a report with **✅ Completed** status
2. Open the report actions menu
3. Select **"Quick Share Link"**
4. Copy the generated link and send it to the recipient

**How it works (high level):**
- The UI calls `POST /api/reports/{id}/share`
- The backend returns a URL like `/api/reports/shared/{token}`
- The token expires (typically 1 hour)

**Security notes:**
- Share links are time-limited
- Tokens are signed (HMAC) to detect tampering
- Public downloads are rate-limited

---

### 4. Resend Report via Email

**Purpose:** Re-deliver report if original email failed or customer didn't receive it.

**Steps:**
1. Locate report card
2. Click **three-dot menu** (⋮)
3. Select **"Resend Email"**
4. Confirm recipient email address
5. Optionally add additional recipients (CC)
6. Click **"Send"**
7. Success notification appears

**Email Contains:**
- Report attached as PDF/Excel
- Customer branding
- Professional email template
- Support contact information

**Permissions Required:** `reports.download`

---

### 5. Delete Report

**Purpose:** Remove old or incorrect reports to free up storage.

**Steps:**
1. Locate report card
2. Click **three-dot menu** (⋮)
3. Select **"Delete Report"**
4. Confirm deletion
5. Report file is permanently deleted

**Warning:** Cannot be undone! Report will need to be regenerated if needed later.

**Permissions Required:** `reports.delete` (admin only)

---

### 6. View Report Details

**Steps:**
1. Click on report card (not on action buttons)
2. See detailed information:
   - **Generation Details**:
     - Start time
     - End time
     - Duration
     - Data sources used
   - **Report Period**:
     - Date range covered
     - Data points included
   - **Delivery History**:
     - Email sent timestamp
     - Recipients
     - Delivery status
     - Bounce/error messages
   - **File Information**:
     - File size
     - Format
     - Storage location

**Permissions Required:** `reports.view`

---

### 7. Search Reports

**Search Capabilities:**
- **Customer Name**: Find all reports for customer
- **Report Type**: Find specific report type
- **Partial Match**: Searches as you type
- **Case-Insensitive**: Matches any capitalization

**Examples:**
- Search "ACME" → Shows all ACME Corp reports
- Search "Uptime" → Shows all uptime reports
- Search "Security" → Shows all security reports

---

### 8. Filter by Status

**Status Options:**

#### ✅ Completed
- Report generated successfully
- File available for download
- Ready for delivery

#### ❌ Failed
- Report generation failed
- Error message available
- Needs troubleshooting

**Common Failure Reasons:**
- Missing data from integration
- Integration not synced
- Insufficient historical data
- Configuration error

#### ⏳ Pending
- Report generation in progress
- Queued for processing
- Check back in a few minutes

#### 📧 Delivered
- Email sent successfully
- Customer received report
- Delivery confirmed

---

### 9. Filter by Report Type

**Available Report Types:**

#### Monthly Uptime Report
- Device/endpoint uptime percentages
- Downtime incidents
- Availability trends
- SLA compliance

#### Security Summary
- Security events
- Threat detections
- Vulnerability scans
- Compliance status

#### Backup Status Report
- Backup job results
- Success/failure rates
- Storage usage
- Recovery point objectives

#### Ticket Summary
- Tickets opened/closed
- Response times
- Resolution times
- Ticket categories

#### Device Health Report
- Device status
- Performance metrics
- Alerts and warnings
- Maintenance recommendations

#### License Consumption Report
- License usage
- Available licenses
- Overages
- Cost analysis

#### Custom Reports
- Company-specific reports
- Configured per customer
- Custom data sources

---

### 10. Filter by Date Range

**Steps:**
1. Click **Date Range** picker
2. Select:
   - **Today**: Reports generated today
   - **Last 7 Days**: Past week
   - **Last 30 Days**: Past month
   - **Last 90 Days**: Past quarter
   - **Custom Range**: Pick specific dates
3. Reports filter automatically

**Use Cases:**
- Review recent reports
- Audit historical reports
- Find specific report from date
- Analyze report generation trends

---

## Report Status Indicators

### Status Badges

| Badge | Meaning | Action Required |
|-------|---------|-----------------|
| ✅ **Completed** | Successfully generated | None - ready to download |
| ❌ **Failed** | Generation failed | Check error message, verify data |
| ⏳ **Pending** | In progress | Wait for completion |
| 📧 **Delivered** | Email sent | None - customer received |

### Delivery Status

| Status | Meaning |
|--------|---------|
| 📧 **Sent** | Email delivered successfully |
| ⏳ **Pending** | Queued for delivery |
| ❌ **Failed** | Email delivery failed |
| 📥 **Downloaded** | User downloaded manually |
| ⚠️ **Bounced** | Email address invalid |

---

## Common Tasks

### Downloading Multiple Reports
1. Use filters to narrow down reports
2. Click download on each report card
3. Or use bulk download (if available):
   - Select multiple reports (checkbox)
   - Click "Download Selected"
   - ZIP file downloads with all reports

### Troubleshooting Failed Reports
1. Filter by Status: Failed
2. Click on failed report card
3. View error message
4. Common fixes:
   - **"No data available"**: Run integration sync
   - **"Missing historical data"**: Import historical data
   - **"Configuration error"**: Check report schedule settings
   - **"Customer not mapped"**: Map customer in Customers page
5. After fixing, regenerate report:
   - Go to Schedules page
   - Find schedule
   - Click "Generate Now"

### Verifying Customer Received Reports
1. Filter by customer name
2. Check delivery status on report cards
3. Look for 📧 "Delivered" badge
4. If failed, click "Resend Email"
5. Verify customer email address is correct

### Cleaning Up Old Reports
1. Filter by date range (e.g., >90 days old)
2. Review reports to delete
3. Click three-dot menu → "Delete Report"
4. Confirm deletion
5. Repeat for other old reports
6. Or use bulk delete (if available)

---

## Report Generation Process

### How Reports Are Generated

1. **Schedule Triggers**:
   - Scheduled time reached (e.g., 9:00 AM on 1st of month)
   - Or manual "Generate Now" clicked

2. **Data Collection**:
   - System queries integration data
   - Pulls historical metrics
   - Aggregates across date range
   - Applies customer mappings

3. **Report Rendering**:
   - Applies report template
   - Inserts customer branding
   - Generates charts/graphs
   - Creates PDF/Excel file

4. **Delivery**:
   - Saves file to storage
   - Sends email (if configured)
   - Updates delivery status
   - Logs generation event

**Typical Duration:**
- Simple reports: 30-60 seconds
- Complex reports: 2-5 minutes
- Multi-integration reports: 5-10 minutes

---

## Best Practices

✅ **DO:**
- Review failed reports daily
- Download important reports for backup
- Verify customers receive emails
- Clean up old reports (>90 days)
- Monitor report generation success rate
- Test report schedules after setup

❌ **DON'T:**
- Delete recent reports (<30 days)
- Ignore failed reports
- Resend reports excessively (spam)
- Download reports unnecessarily (bandwidth)
- Share reports with unauthorized users

---

## Permissions Reference

| Action | Required Permission |
|--------|-------------------|
| View reports | `reports.view` |
| Download reports | `reports.download` |
| Resend reports | `reports.download` |
| Delete reports | `reports.delete` (admin) |
| Generate reports | `reports.generate` |
| Configure reports | `reports.configure` |

---

## Report Branding

### Customer Branding
Each report includes:
- **Customer Logo**: From customer settings or company default
- **Primary Color**: Charts and accents
- **Secondary Color**: Headers and highlights
- **Footer Text**: Custom footer message
- **Contact Information**: Support email and phone

### White-Label Branding
Reports use company white-label settings:
- **Company Logo**: In header
- **Company Colors**: Throughout report
- **Company Contact**: In footer
- **Professional Template**: Branded design

**Configure in:** Settings → White-Label Branding

---

## Troubleshooting

### Report Shows "No Data Available"
**Cause:** Integration hasn't synced or no data for period
**Solution:**
1. Go to Integrations page
2. Find relevant integration
3. Click "Sync Now"
4. Wait for sync to complete
5. Regenerate report

### Report Generation Takes Too Long
**Cause:** Large dataset or slow integration API
**Solution:**
1. Check integration rate limit status
2. Verify integration is Active
3. Wait for completion (can take 10+ minutes)
4. If stuck >30 minutes, contact support

### Email Not Delivered
**Cause:** Invalid email, spam filter, or email service issue
**Solution:**
1. Verify customer email address is correct
2. Check spam/junk folder
3. Whitelist sender email address
4. Try resending
5. Download and send manually if needed

### Report Shows Wrong Data
**Cause:** Incorrect customer mapping or date range
**Solution:**
1. Verify customer mappings in Customers page
2. Check report schedule date range
3. Verify integration data is correct
4. Re-sync integration if needed
5. Regenerate report

### Cannot Download Report
**Cause:** File deleted, storage issue, or permissions
**Solution:**
1. Check report status is "Completed"
2. Verify file size is not 0 bytes
3. Check user permissions
4. Try different browser
5. Regenerate report if file is missing

---

## FAQ

**Q: How long are reports stored?**
A: Based on company retention policy (default: 3 years). Configurable in Settings.

**Q: Can I regenerate an old report?**
A: No, but you can create a new report with the same date range if historical data is available.

**Q: Why are some reports PDF and others Excel?**
A: Report type determines format. Most are PDF for presentation. Data-heavy reports use Excel for analysis.

**Q: Can I customize report templates?**
A: Yes, contact support for custom report templates. Branding is configurable in Settings.

**Q: How do I get reports for a date range in the past?**
A: Historical data must be imported first. Then create a schedule with the desired date range.

**Q: Can I schedule reports to multiple recipients?**
A: Yes, configure in report schedule settings. Separate emails with commas.

**Q: What happens if a report fails?**
A: System retries 3 times. If still failing, marked as Failed. Check error message and fix issue.

**Q: Can I preview a report before sending?**
A: Yes, download the report and review before configuring email delivery.

---

## Related Pages
- **Customers**: Configure which reports customers receive
- **Schedules**: Manage when reports are generated
- **Integrations**: Ensure data is syncing for reports
- **Settings**: Configure report branding and retention
