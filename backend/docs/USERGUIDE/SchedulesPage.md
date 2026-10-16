# Schedules Page - User Guide

## Overview
The Schedules page displays all configured report schedules across all customers. You can view, edit, enable/disable, and create new schedules. Schedules automatically generate reports at specified times and frequencies.

---

## Page Layout

### Header Section
- **Title**: "Report Schedules" with calendar icon
- **Create Schedule Button**: Add new report schedule
- **Statistics Cards**:
  - Total Schedules
  - Active Schedules
  - Paused Schedules
  - Next Generation (upcoming report)

### Filter Bar
- **Search**: Filter by customer name or report type
- **Status Filter**: All, Active, Paused, Error
- **Frequency Filter**: All, Daily, Weekly, Monthly, Yearly, On-Demand

### Schedules Grid
Each schedule card displays:
- **Customer Name**: Who receives this report
- **Report Type**: Type of report being generated
- **Frequency Badge**: Daily, Weekly, Monthly, Yearly
- **Status Badge**:
  - Active (generating reports)
  - Paused (temporarily disabled)
  - Error (generation failing)
- **Next Generation**: When next report will generate
- **Last Generated**: When last report was created
- **Success Rate**: Percentage of successful generations
- **Action Buttons**: Edit, Pause/Resume, Generate Now, Delete

---

## Features & How to Use

### 1. Create New Schedule

**Steps:**
1. Click **"Create Schedule"** button (top right)
2. **Select Customer**:
   - Search customer name
   - Or select from dropdown
   - Only customers with mapped integrations shown
3. **Choose Report Type**:
   - Monthly Uptime Report
   - Security Summary
   - Backup Status Report
   - Ticket Summary
   - Device Health Report
   - License Consumption Report
   - Custom Reports
4. **Configure Frequency**:
   - Daily, Weekly, Monthly, Yearly, or On-Demand
5. **Set Schedule Details** (varies by frequency)
6. **Configure Delivery**:
   - Email recipients
   - CC recipients (optional)
   - Email subject template
   - Email body template
7. **Review & Create**:
   - Preview next 5 generation times
   - Verify settings
   - Click **"Create Schedule"**

**Permissions Required:** `reports.configure`

---

### 2. Configure Schedule Frequencies

#### Daily Schedule
**Options:**
- **Every Day**: Generate report daily at specified time
- **Weekdays Only**: Monday-Friday only
- **Custom Days**: Select specific days of week

**Configuration:**
- Time: HH:MM (24-hour format)
- Timezone: Customer's timezone
- Days: Select which days

**Example:** "Daily at 9:00 AM, Monday-Friday"

**Use Cases:**
- Daily backup reports
- Daily security summaries
- Daily ticket updates

---

#### Weekly Schedule
**Options:**
- Day of week: Monday, Tuesday, etc.
- Time: HH:MM
- Timezone: Customer's timezone

**Configuration:**
- Select day: Monday, Tuesday, Wednesday, Thursday, Friday, Saturday, Sunday
- Time: 09:00 AM (example)

**Example:** "Every Monday at 9:00 AM"

**Use Cases:**
- Weekly security reports
- Weekly performance summaries
- Weekly ticket summaries

---

#### Monthly Schedule
**Options:**
- **Day of Month**: 1st, 2nd, 3rd, etc. (1-31)
- **Last Day of Month**: Always last day (handles different month lengths)
- **Specific Weekday**: First Monday, Last Friday, etc.

**Configuration:**
- Day: 1-31 or "Last Day"
- Time: HH:MM
- Timezone: Customer's timezone
- Fallback: If day doesn't exist (e.g., Feb 31), use last day of month

**Examples:**
- "1st of every month at 9:00 AM"
- "Last day of month at 5:00 PM"
- "First Monday of month at 9:00 AM"

**Use Cases:**
- Monthly uptime reports
- Monthly billing reports
- Monthly executive summaries

---

#### Yearly Schedule
**Options:**
- Month: January-December
- Day: 1-31 or "Last Day"
- Time: HH:MM
- Timezone: Customer's timezone

**Configuration:**
- Month: Select month
- Day: Select day
- Time: HH:MM

**Example:** "January 1st at 9:00 AM" (annual report)

**Use Cases:**
- Annual compliance reports
- Yearly performance reviews
- Annual security audits

---

#### On-Demand Schedule
**Purpose:** Report template saved but not automatically generated.

**Configuration:**
- No schedule settings
- Generate manually only
- Template saved for quick generation

**Use Cases:**
- Ad-hoc reports
- Customer-requested reports
- Quarterly business reviews (manual trigger)

---

### 3. Edit Schedule

**Steps:**
1. Locate schedule card
2. Click **"Edit"** button (pencil icon)
3. Modify any settings:
   - Report type (if changing)
   - Frequency
   - Time and timezone
   - Delivery settings
   - Email recipients
4. Click **"Save Changes"**
5. Next generation time recalculates

**Note:** Editing schedule doesn't affect already-generated reports.

**Permissions Required:** `reports.configure`

---

### 4. Pause/Resume Schedule

**Purpose:** Temporarily stop report generation without deleting schedule.

**Pause Steps:**
1. Locate schedule card
2. Click **"Pause"** button (pause icon)
3. Schedule status changes to Paused
4. No reports will generate until resumed

**Resume Steps:**
1. Locate paused schedule card
2. Click **"Resume"** button (play icon)
3. Schedule status changes to Active
4. Next generation time recalculates

**Use Cases:**
- Customer on vacation
- Temporary service suspension
- Integration maintenance
- Testing/troubleshooting

**Permissions Required:** `reports.configure`

---

### 5. Generate Report Now (Manual Trigger)

**Purpose:** Generate report immediately without waiting for scheduled time.

**Steps:**
1. Locate schedule card
2. Click **"Generate Now"** button (lightning icon)
3. Confirm generation
4. Report queues for immediate generation
5. Navigate to Reports page to download

**Use Cases:**
- Customer requests immediate report
- Testing new schedule
- Regenerating failed report
- Ad-hoc report needs

**Note:** Doesn't affect next scheduled generation time.

**Permissions Required:** `reports.generate`

---

### 6. Delete Schedule

**Steps:**
1. Locate schedule card
2. Click **three-dot menu** (⋮)
3. Select **"Delete Schedule"**
4. Confirm deletion
5. Schedule permanently deleted

**Warning:** 
- Cannot be undone
- Existing generated reports remain
- Customer will stop receiving this report

**Permissions Required:** `reports.configure`

---

### 7. View Schedule History

**Steps:**
1. Click on schedule card
2. View detailed history:
   - **Generation History**:
     - Last 30 generations
     - Status (Success/Failed)
     - Duration
     - File size
   - **Delivery History**:
     - Email sent timestamps
     - Recipients
     - Delivery status
   - **Configuration Changes**:
     - Who modified schedule
     - What changed
     - When changed

**Use Cases:**
- Troubleshoot failed generations
- Verify reports are generating
- Audit schedule changes
- Monitor success rates

**Permissions Required:** `reports.view`

---

### 8. Preview Next Generations

**Purpose:** See when next reports will generate to verify schedule is correct.

**Steps:**
1. Click on schedule card
2. View **"Next Generations"** section
3. See next 5 scheduled times:
   - Date and time
   - Timezone
   - Days until generation

**Use Cases:**
- Verify schedule configuration
- Check timezone is correct
- Confirm frequency is right
- Plan around report generation

---

### 9. Configure Email Delivery

**Settings Available:**

#### Recipients
- **To**: Primary recipients (required)
- **CC**: Carbon copy recipients (optional)
- **BCC**: Blind carbon copy (optional)

**Format:** Separate multiple emails with commas
**Example:** `john@acme.com, jane@acme.com`

#### Email Template
- **Subject**: Email subject line
  - Variables: `{customer_name}`, `{report_type}`, `{date}`
  - Example: `{customer_name} - {report_type} for {date}`
- **Body**: Email message
  - Variables: `{customer_name}`, `{report_type}`, `{date}`, `{company_name}`
  - HTML supported
  - Example: "Please find attached your {report_type} for {date}."

#### Branding
- Uses company white-label settings
- Customer logo (if configured)
- Company colors
- Professional template

**Permissions Required:** `reports.configure`

---

## Search & Filtering

### Search Bar
- **Customer Name**: Find schedules for specific customer
- **Report Type**: Find specific report types
- **Partial Match**: Real-time filtering
- **Case-Insensitive**: Matches any capitalization

### Status Filter
- **All**: Show all schedules
- **Active**: Currently generating reports
- **Paused**: Temporarily disabled
- **Error**: Failing to generate

### Frequency Filter
- **All**: All frequencies
- **Daily**: Daily schedules
- **Weekly**: Weekly schedules
- **Monthly**: Monthly schedules
- **Yearly**: Yearly schedules
- **On-Demand**: Manual generation only

---

## Schedule Status Indicators

| Status | Meaning | Action Required |
|--------|---------|-----------------|
| **Active** | Generating reports on schedule | None |
| **Paused** | Temporarily disabled | Resume when ready |
| **Error** | Failing to generate | Check error message, fix issue |
| **Pending** | First generation not yet run | Wait for scheduled time |

---

## Common Tasks

### Setting Up Monthly Reports for All Customers
1. Click **"Create Schedule"**
2. Select first customer
3. Choose "Monthly Uptime Report"
4. Set frequency: Monthly, 1st of month, 9:00 AM
5. Configure email delivery
6. Click "Create Schedule"
7. Repeat for each customer (or use bulk create if available)

### Changing Report Time for Customer
1. Find customer's schedule
2. Click **"Edit"**
3. Change time (e.g., 9:00 AM → 5:00 PM)
4. Save changes
5. Next generation time updates automatically

### Temporarily Stopping Reports
1. Find schedule to pause
2. Click **"Pause"** button
3. Schedule stops generating
4. Resume when ready

### Testing New Schedule
1. Create schedule with desired settings
2. Click **"Generate Now"** to test immediately
3. Check Reports page for generated report
4. Download and verify report is correct
5. Adjust schedule settings if needed

### Troubleshooting Failed Schedules
1. Filter by Status: Error
2. Click on failed schedule
3. View error message
4. Common fixes:
   - **"No data available"**: Sync integration
   - **"Customer not mapped"**: Map customer
   - **"Missing historical data"**: Import historical data
   - **"Invalid email"**: Update recipient email
5. Click "Generate Now" to retry

---

## Best Practices

**DO:**
- Set schedules in customer's timezone
- Test schedules with "Generate Now" before going live
- Use descriptive email subjects
- Preview next generations to verify
- Monitor success rates weekly
- Pause schedules instead of deleting
- Document schedule changes

**DON'T:**
- Schedule too many reports (overwhelm customers)
- Use generic email subjects
- Forget to test email delivery
- Delete schedules (pause instead)
- Ignore failed generations
- Schedule reports during off-hours
- Use incorrect timezones

---

## Timezone Handling

### How Timezones Work
- Each schedule has a timezone setting
- Report generates at specified time in that timezone
- System converts to UTC for processing
- Handles daylight saving time automatically

### Choosing Timezone
- **Customer's Timezone**: Recommended (reports arrive at expected time)
- **Company Timezone**: If customer timezone unknown
- **UTC**: For global customers or consistency

### DST (Daylight Saving Time)
- System automatically adjusts for DST
- Schedule time remains consistent (e.g., always 9:00 AM)
- No manual adjustment needed

**Example:**
- Schedule: 9:00 AM AEST (Australian Eastern Standard Time)
- DST starts: Schedule automatically becomes 9:00 AM AEDT
- Customer always receives report at 9:00 AM local time

---

## Permissions Reference

| Action | Required Permission |
|--------|-------------------|
| View schedules | `reports.view` |
| Create schedule | `reports.configure` |
| Edit schedule | `reports.configure` |
| Delete schedule | `reports.configure` |
| Pause/resume schedule | `reports.configure` |
| Generate now | `reports.generate` |

---

## Troubleshooting

### Schedule Not Generating Reports
**Cause:** Schedule paused, integration not synced, or configuration error
**Solution:**
1. Check schedule status (Active?)
2. Verify integration is syncing
3. Check customer is mapped
4. Review error messages
5. Test with "Generate Now"

### Reports Generating at Wrong Time
**Cause:** Incorrect timezone setting
**Solution:**
1. Edit schedule
2. Verify timezone is correct
3. Check next generation preview
4. Save changes

### Email Not Delivering
**Cause:** Invalid email address or spam filter
**Solution:**
1. Verify recipient email is correct
2. Check spam/junk folder
3. Whitelist sender email
4. Test with "Generate Now"

### Cannot Create Schedule for Customer
**Cause:** Customer not mapped or no integrations
**Solution:**
1. Go to Customers page
2. Verify customer is mapped
3. Check customer has integration data
4. Sync integrations if needed

### Schedule Shows "Error" Status
**Cause:** Multiple failed generations
**Solution:**
1. Click on schedule to view errors
2. Fix underlying issue
3. Click "Generate Now" to test
4. If successful, status resets to Active

---

## FAQ

**Q: How many schedules can I create per customer?**
A: Unlimited. However, recommend 2-4 reports per customer to avoid overwhelming them.

**Q: Can I schedule the same report type multiple times?**
A: Yes. For example, weekly and monthly uptime reports for the same customer.

**Q: What happens if a scheduled time is missed (server down)?**
A: System generates report when back online. Configurable to skip or catch up.

**Q: Can I schedule reports for specific date ranges?**
A: Yes, configure in report settings. Example: "Last 30 days" or "Previous month".

**Q: How do I know if a schedule is working?**
A: Check success rate on schedule card. 100% = all generations successful.

**Q: Can I bulk create schedules for all customers?**
A: Yes, use bulk operations (if available) or create template and apply to multiple customers.

**Q: What's the difference between Pause and Delete?**
A: Pause temporarily stops generation (can resume). Delete permanently removes schedule.

**Q: Can customers manage their own schedules?**
A: Not by default. Customer portal (if enabled) allows limited self-service.

**Q: How far in advance are reports generated?**
A: Exactly at scheduled time. Not pre-generated.

**Q: Can I test a schedule without sending email?**
A: Yes, use "Generate Now" and download from Reports page instead of emailing.

---

## Related Pages
- **Customers**: Configure which customers receive reports
- **Reports**: View and download generated reports
- **Integrations**: Ensure data is available for reports
- **Settings**: Configure default schedules and email templates
