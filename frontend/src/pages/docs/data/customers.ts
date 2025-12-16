import { DocCategory } from '../types'

export const customersCategory: DocCategory = {
  id: 'customers',
  title: 'Customer Management',
  description: 'Manage and map customers across integrations',
  icon: 'Users',
  articles: [
    {
      id: 'customer-overview',
      title: 'Customer Management Overview',
      description: 'Understanding the customer management system',
      icon: 'Users',
      content: `
# Customer Management Overview

The customer management system helps you maintain a unified view of your customers across all connected integrations.

## The Challenge

Different systems often have the same customer with different names:
- "ACME Corp" in Autotask
- "ACME Corporation" in Datto
- "Acme Corp." in your billing system

This makes unified reporting difficult without a way to link these records.

## The Solution: Customer Mapping

The platform solves this with a two-tier customer system:

### Master Customer List
- Your authoritative list of customers
- Each customer appears once
- Used for report generation and delivery
- Contains canonical customer information

### Integration Customers
- Raw customer records from each integration
- May have different names/formats
- Linked to master customers via mapping

## How It Works

### 1. Integration Sync
When integrations sync, they bring in customer records as "Integration Customers."

### 2. Auto-Matching
The system automatically attempts to match integration customers to master customers using:
- Exact name matching
- Fuzzy matching (similar names)
- Confidence scoring

### 3. Manual Review
Unmatched or low-confidence matches are flagged for manual review.

### 4. Unified View
Once mapped, you can see all data for a customer across all integrations in one place.

## Key Concepts

### Source of Truth
You can designate one integration as the "source of truth":
- New customers from this integration auto-create master records
- Other integrations' customers map TO these

### Confidence Score
Auto-matches include a confidence score:
- **90-100%** - Very likely correct
- **80-89%** - Probably correct, worth reviewing
- **Below 80%** - Needs manual review
      `,
      relatedArticles: ['master-customer-list', 'mapping-customers', 'customer-details']
    },
    {
      id: 'master-customer-list',
      title: 'Master Customer List',
      description: 'Managing your primary customer list',
      icon: 'Database',
      content: `
# Master Customer List

The Master Customer List is your authoritative record of all customers.

## Accessing the Master List

1. Click **Customers** in the sidebar
2. Select **Master List**

## List Features

### Search and Filter
- **Search box** - Find customers by name
- **Status filter** - Active, Inactive, All
- **Integration filter** - Show customers from specific integrations

### Columns
- **Customer Name** - The canonical name
- **Status** - Active or Inactive
- **Mapped Integrations** - Icons showing linked integrations
- **Last Report** - When a report was last generated
- **Actions** - View, edit, configure reports

## Customer Actions

### View Details
Click a customer name to see full information, mapped integrations, and report history.

### Edit Customer
1. Click the edit icon
2. Modify customer details
3. Save changes

### Configure Reports
1. Click **Configure Reports** for a customer
2. Select which reports to generate
3. Set delivery preferences
4. Save configuration

## Adding Customers Manually

1. Click **Add Customer**
2. Enter customer details
3. Click **Save**

Note: Manually added customers won't have integration data until mapped.

## Bulk Operations

### Export
Download customer list as CSV or Excel.

### Bulk Status Update
Select multiple customers and update status in bulk.
      `,
      relatedArticles: ['customer-overview', 'mapping-customers', 'customer-details']
    },
    {
      id: 'mapping-customers',
      title: 'Mapping Customers',
      description: 'How to map integration customers to your master list',
      icon: 'MapPin',
      content: `
# Mapping Customers

Customer mapping links records from your integrations to your master customer list.

## Why Mapping Matters

Without mapping:
- Reports can't combine data from multiple integrations
- You might have duplicate customer records
- Data remains siloed in each integration

With mapping:
- All data for a customer is unified
- Reports include data from all relevant integrations
- Single source of truth for customer information

## Accessing the Mapping Interface

1. Click **Customers** in the sidebar
2. Select **Map Customers**

## Understanding the Interface

### Unmapped Customers Panel
Shows integration customers not yet linked:
- Customer name from integration
- Source integration
- Suggested matches (if any)
- Confidence scores

### Actions
- **Accept Match** - Confirm a suggested mapping
- **Select Different** - Choose a different master customer
- **Create New** - Create a new master customer
- **Ignore** - Mark as not a customer (e.g., vendor)

## Mapping Process

### Auto-Matched Customers
For high-confidence matches:
1. Review the suggested match
2. If correct, click **Accept**
3. The mapping is created

### Manual Mapping
For unmatched or incorrect suggestions:
1. Click **Select Different**
2. Search the master list
3. Select the correct customer
4. Confirm the mapping

### Creating New Customers
If the integration customer is genuinely new:
1. Click **Create New**
2. Review/edit the customer details
3. Confirm creation

## Bulk Mapping

### Accept All High-Confidence
1. Click **Bulk Actions**
2. Select **Accept All Above 90%**
3. Confirm

## Reviewing Mappings

To review existing mappings:
1. Go to **Customers > Master List**
2. Click on a customer
3. View **Mapped Integrations** section
      `,
      relatedArticles: ['customer-overview', 'master-customer-list', 'customer-details']
    },
    {
      id: 'customer-details',
      title: 'Customer Details & Configuration',
      description: 'Viewing and configuring individual customer settings',
      icon: 'UserCog',
      content: `
# Customer Details & Configuration

Each customer has a detail page for viewing information and configuring reporting.

## Accessing Customer Details

1. Go to **Customers > Master List**
2. Click on a customer name

## Detail Page Sections

### Overview
- Customer name and status
- Created and updated dates
- Contact information

### Mapped Integrations
Shows all integration customers linked:
- Integration name and icon
- Customer name in that integration
- Last sync date

### Report Configuration
- Enabled report types
- Delivery settings
- Schedule information

### Report History
- List of generated reports
- Generation date and status
- Download links

## Configuring Reports

### Enabling Reports
1. Click **Configure Reports**
2. Toggle on desired report types
3. Configure content options

### Delivery Settings
- Primary recipient email
- CC recipients
- Custom email subject

### Report Branding
- Customer-specific logo (optional)
- Custom report title
- Footer notes

## Editing Customer Information

1. Click **Edit**
2. Modify fields as needed
3. Click **Save**

## Customer Actions

### Generate Report Now
1. Click **Generate Report**
2. Select report type
3. Choose date range
4. Click **Generate**

### Deactivate Customer
1. Click **Edit**
2. Set status to **Inactive**
3. Save changes
      `,
      relatedArticles: ['customer-overview', 'master-customer-list', 'report-configuration']
    }
  ]
}
