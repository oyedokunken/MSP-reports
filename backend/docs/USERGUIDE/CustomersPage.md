# Customers Page - User Guide

## Overview
The Customers page provides a unified view of all your customers across multiple integrations. It handles customer mapping (matching customers from different systems), manages customer information, and configures report schedules.

---

## Page Layout

### Header Section
- **Title**: "Customers" with customer icon
- **Statistics Cards**:
  - Total Customers (mapped)
  - Unmapped Customers (needs attention)
  - Active Reports
  - Total Integrations

### Unmapped Banner (if applicable)
- **Alert**: Shows count of unmapped customers
- **Action Button**: "Start Mapping" to begin mapping process

### Filter Bar
- **Search**: Filter by customer name
- **Status Filter**: All, Active, Inactive
- **Report Filter**: All, Has Reports, No Reports
- **Integration Filter**: Filter by source integration

### Customer Cards Grid
Each customer card displays:
- **Customer Name**: Unified canonical name
- **Status Badge**: Active (green) or Inactive (gray)
- **Integration Count**: Number of integrations this customer appears in
- **Report Count**: Active report schedules
- **Last Report**: When last report was generated
- **Action Buttons**: Setup Reports, View History, More Options

---

## Features & How to Use

### 1. Customer Mapping (Unifying Customers)

**Purpose:** Match customers from different integrations that represent the same real-world customer.

**Example:** 
- Datto shows "ACME Corp"
- Autotask shows "ACME Corporation"
- ITGlue shows "Acme Corp."
→ All three should map to one unified "ACME Corp" customer

#### Auto-Mapping
**How it works:**
- System automatically suggests matches based on name similarity
- Confidence score shown (85%+ = high confidence)
- Auto-approved if confidence > 90% (configurable)

**Steps:**
1. Click **"Start Mapping"** in unmapped banner
2. Review suggested matches with confidence scores
3. Click **"Approve"** for correct matches
4. Click **"Reject"** for incorrect suggestions
5. System learns from your decisions

#### Manual Mapping
**When to use:** Auto-mapping confidence is low or no suggestion found

**Steps:**
1. Click **"Start Mapping"** in unmapped banner
2. Select unmapped customer from list
3. Choose action:
   - **Map to Existing**: Select existing customer from dropdown
   - **Create New Customer**: This is a genuinely new customer
   - **Ignore**: Not a customer (vendor, internal, etc.)
4. Click **"Confirm Mapping"**

**Permissions Required:** `customers.map`

---

### 2. View Customer Details

**Steps:**
1. Click on customer card
2. View detailed information:
   - **Overview Tab**: 
     - Canonical name
     - Status
     - Contact information
     - Custom metadata
   - **Integrations Tab**:
     - List of all integrations this customer appears in
     - External IDs per integration
     - Last seen timestamp
     - Integration-specific data
   - **Reports Tab**:
     - Active report schedules
     - Recent generated reports
     - Report configuration
   - **History Tab**:
     - Mapping history
     - Changes log
     - Merge/split operations

**Permissions Required:** `customers.view`

---

### 3. Edit Customer Information

**Steps:**
1. Click **three-dot menu** (⋮) on customer card
2. Select **"Edit Customer"**
3. Modify:
   - **Canonical Name**: The "correct" name for this customer
   - **Status**: Active or Inactive
   - **Contact Email**: Primary contact for reports
   - **Custom Metadata**: Additional fields (tags, account manager, etc.)
4. Click **"Save Changes"**

**Use Cases:**
- Correct spelling/capitalization
- Update contact information
- Add customer-specific notes
- Mark customer as inactive (no longer a client)

**Permissions Required:** `customers.edit`

---

### 4. Setup Report Schedules

**Steps:**
1. Click **"Setup Reports"** button on customer card
2. Or navigate via: Customer → More Options → Configure Reports
3. See report configuration page with:
   - Available report types
   - Current schedules
   - Delivery settings
4. Add new report schedule:
   - Select report type
   - Choose frequency (Daily, Weekly, Monthly, Yearly)
   - Set time and timezone
   - Configure delivery (email, API, download)
5. Click **"Create Schedule"**

**Report Types Available:**
- Monthly Uptime Report
- Security Summary
- Backup Status Report
- Ticket Summary
- Device Health Report
- Custom Reports (if configured)

**Permissions Required:** `reports.configure`

---

### 5. View Report History

**Steps:**
1. Click **"View History"** button on customer card
2. See list of generated reports:
   - Report type
   - Generation date
   - Status (Completed, Failed, Pending)
   - File size
   - Delivery status
3. Actions available:
   - **Download**: Get PDF/Excel file
   - **Resend**: Email report again
   - **Delete**: Remove report file

**Filtering:**
- By report type
- By date range
- By status

**Permissions Required:** `reports.view`

---

### 6. View Customer Integrations

**Purpose:** See which integrations this customer appears in and their external IDs.

**Steps:**
1. Click **three-dot menu** (⋮) on customer card
2. Select **"View Integrations"**
3. See table showing:
   - Integration name
   - External ID (customer ID in that system)
   - Customer name in that system
   - Last seen timestamp
   - Mapping confidence score
4. Actions:
   - **View Data**: See raw integration data
   - **Unmap**: Remove mapping (if incorrect)
   - **Update**: Refresh mapping

**Use Cases:**
- Verify correct customer mapping
- Find customer ID in external system
- Troubleshoot missing data
- Audit customer mappings

**Permissions Required:** `customers.view`

---

### 7. Merge Customers

**Purpose:** Combine two customer records that were accidentally created separately.

**When to use:**
- Duplicate customers exist
- Customer was renamed/acquired
- Incorrect initial mapping

**Steps:**
1. Click **three-dot menu** (⋮) on customer card
2. Select **"Merge with Another Customer"**
3. Search and select target customer to merge into
4. Review merge preview:
   - Primary customer (keeps this name/data)
   - Secondary customer (will be merged in)
   - All integration mappings will transfer
   - All report schedules will transfer
5. Click **"Merge Customers"**
6. Secondary customer is soft-deleted

**Warning:** Cannot be easily undone. Verify before merging!

**Permissions Required:** `customers.edit`

---

### 8. Split Customer

**Purpose:** Separate one customer record into two when incorrectly merged.

**When to use:**
- Two real customers were mapped to one record
- Integration customer was incorrectly mapped
- Company split/divested

**Steps:**
1. Click **three-dot menu** (⋮) on customer card
2. Select **"Split Customer"**
3. Review integration mappings
4. Select which integration customers belong to:
   - **Keep with Current**: Stay with this customer
   - **Move to New**: Create new customer record
5. Enter new customer name
6. Click **"Split Customer"**
7. New customer record created with selected mappings

**Permissions Required:** `customers.edit`

---

### 9. View Integration Data

**Purpose:** See raw data from specific integration for this customer.

**Steps:**
1. Click **three-dot menu** (⋮) on customer card
2. Select **"View Integrations"**
3. Click **"View Data"** for specific integration
4. See detailed data:
   - Devices/endpoints
   - Tickets/alerts
   - Configurations
   - Historical metrics
5. Export data as Excel/CSV

**Use Cases:**
- Verify data is syncing
- Troubleshoot report issues
- Audit customer information
- Export for analysis

**Permissions Required:** `customers.view`

---

### 10. More Info (Integration Details)

**Steps:**
1. Click **"More Info"** button on customer card
2. Navigate to detailed integration view
3. See per-integration breakdown:
   - Device count
   - Active alerts
   - Recent tickets
   - Backup status
   - License information

**Permissions Required:** `customers.view`

---

## Search & Filtering

### Search Bar
- **Type**: Customer name
- **Real-time**: Filters as you type
- **Case-insensitive**: Matches partial names
- **Searches**: Canonical name and integration names

### Status Filter
- **All**: Show all customers
- **Active**: Currently active clients
- **Inactive**: Former clients or disabled

### Report Filter
- **All**: All customers
- **Has Reports**: Customers with active report schedules
- **No Reports**: Customers without report schedules (needs setup)

### Integration Filter
- **All Integrations**: Show all customers
- **Specific Integration**: Only customers from selected integration
- **Multiple Integrations**: Customers appearing in 2+ integrations

---

## Customer Mapping Strategies

### Strategy 1: Master Customer List (Recommended)
1. Set one integration as "Master Customer List" (e.g., Autotask PSA)
2. All customers from master are auto-created
3. Other integrations' customers map TO master customers
4. Reduces mapping conflicts

### Strategy 2: Auto-Match with Review
1. Enable auto-matching (Settings → Customer Mapping)
2. Set confidence threshold (85% recommended)
3. Review suggested matches daily
4. Approve/reject suggestions
5. System learns from your decisions

### Strategy 3: Manual Mapping
1. Disable auto-matching
2. Review unmapped customers weekly
3. Manually map each customer
4. Best for small customer base (<100 customers)

---

## Customer Card Information

### Status Indicators
| Indicator | Meaning |
|-----------|---------|
| **Active** | Current client with active services |
| **Inactive** | Former client or services disabled |

### Integration Count Badge
- Shows number of integrations customer appears in
- Click to view integration details
- Higher count = more data available for reports

### Report Count Badge
- Shows number of active report schedules
- Click to view/edit schedules
- Zero = needs report setup

### Last Report Timestamp
- When last report was generated
- Hover for full date/time
- "Never" if no reports generated yet

---

## Common Tasks

### Mapping Your First Customers
1. Navigate to Customers page
2. Note unmapped count in banner
3. Click **"Start Mapping"**
4. Review auto-suggestions first (high confidence)
5. Approve correct matches
6. Manually map remaining customers
7. Create new customers for genuinely new clients
8. Ignore non-customers (vendors, internal)

### Setting Up Reports for Customer
1. Find customer card
2. Click **"Setup Reports"**
3. Add "Monthly Uptime Report"
   - Frequency: Monthly
   - Day: 1st of month
   - Time: 9:00 AM
   - Delivery: Email to customer contact
4. Add "Security Summary"
   - Frequency: Weekly
   - Day: Monday
   - Time: 9:00 AM
5. Save schedules

### Fixing Incorrect Mapping
1. Find incorrectly mapped customer
2. Click **three-dot menu** → "View Integrations"
3. Identify incorrect integration mapping
4. Click **"Unmap"** on incorrect integration
5. Return to unmapped customers
6. Map to correct customer or create new

### Merging Duplicate Customers
1. Identify duplicate customers (similar names)
2. Choose primary customer (keep this one)
3. Click **three-dot menu** → "Merge with Another Customer"
4. Search for duplicate customer
5. Review merge preview
6. Confirm merge
7. Verify integration mappings transferred

---

## Best Practices

**DO:**
- Review unmapped customers weekly
- Set Master Customer List for consistency
- Use canonical names (proper capitalization)
- Keep customer status updated
- Setup reports for all active customers
- Document custom metadata fields

**DON'T:**
- Ignore unmapped customers
- Merge customers without verification
- Delete customers (mark inactive instead)
- Use inconsistent naming conventions
- Map vendors as customers
- Skip report setup

---

## Permissions Reference

| Action | Required Permission |
|--------|-------------------|
| View customers | `customers.view` |
| Edit customer | `customers.edit` |
| Map customer | `customers.map` |
| Merge customers | `customers.edit` |
| Split customer | `customers.edit` |
| Setup reports | `reports.configure` |
| View reports | `reports.view` |

---

## Troubleshooting

### Customer Not Appearing
**Cause:** Not synced from integration yet
**Solution:**
1. Go to Integrations page
2. Find relevant integration
3. Click "Sync Now"
4. Wait for sync to complete
5. Return to Customers page

### Wrong Customer Name
**Cause:** Integration has incorrect name
**Solution:**
1. Edit customer → Update canonical name
2. Or fix in source integration and re-sync

### Cannot Map Customer
**Cause:** Insufficient permissions
**Solution:**
1. Check user permissions
2. Request `customers.map` permission from admin

### Duplicate Customers After Merge
**Cause:** Merge didn't complete or new sync created duplicate
**Solution:**
1. Verify merge completed in History tab
2. Check if integration re-created customer
3. Re-map or merge again

### Missing Integration Data
**Cause:** Integration not synced or customer not in that system
**Solution:**
1. Verify customer exists in source integration
2. Check integration sync status
3. Run manual sync
4. View sync logs for errors

---

## FAQ

**Q: What's the difference between "mapped" and "unmapped" customers?**
A: Unmapped customers exist in an integration but haven't been matched to a unified customer record yet. Mapped customers are linked to a unified record.

**Q: Can one customer appear in multiple integrations?**
A: Yes! That's the point of mapping. "ACME Corp" in Datto, Autotask, and ITGlue all map to one unified "ACME Corp" customer.

**Q: What happens if I delete a customer?**
A: Customers are soft-deleted (marked inactive). Integration mappings remain but reports stop generating. Can be reactivated later.

**Q: How do I know if a customer mapping is correct?**
A: Check "View Integrations" to see all integration names and IDs. Verify they all refer to the same real-world customer.

**Q: Can I undo a customer merge?**
A: Yes, use "Split Customer" to separate them. However, you'll need to reconfigure report schedules.

**Q: Why are some customers showing "No Reports"?**
A: Reports haven't been configured yet. Click "Setup Reports" to create schedules.

**Q: What's a "Master Customer List"?**
A: The integration designated as the authoritative source for customer names. Other integrations' customers map TO these customers.

**Q: How often should I review unmapped customers?**
A: Weekly is recommended, or immediately after adding a new integration.

---

## Related Pages
- **Integrations**: Sync customer data from external systems
- **Reports**: View and download generated reports
- **Schedules**: Manage report generation schedules
- **Settings**: Configure auto-mapping thresholds and rules
