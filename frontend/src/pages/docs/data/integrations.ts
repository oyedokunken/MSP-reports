import type { DocCategory } from '../types'

export const integrationsCategory: DocCategory = {
  id: 'integrations',
  title: 'Integrations',
  description: 'Connect and manage your business system integrations',
  icon: 'Cable',
  articles: [
    {
      id: 'integrations-overview',
      title: 'Integrations Overview',
      description: 'Understanding how integrations work in the platform',
      icon: 'Cable',
      content: `
# Integrations Overview

Integrations are the backbone of the reporting platform, connecting your various business systems to enable unified reporting.

## What Are Integrations?

Integrations are connections to external systems like:
- **PSA Tools** - Autotask, ConnectWise, etc.
- **RMM Platforms** - Datto RMM, Kaseya, etc.
- **Documentation** - ITGlue, Hudu, etc.
- **Security** - BitDefender, Sendmarc, etc.
- **Backup Solutions** - Veeam, Acronis, etc.
- **Monitoring Systems** - Various alerting and monitoring tools

## How Integrations Work

### 1. Connection
You provide API credentials for each system, and the platform establishes a secure connection.

### 2. Syncing
The platform regularly pulls data from each integration:
- **Current state syncs** - Regular updates of current data (every 4 hours by default)
- **Historical imports** - One-time import of historical data with progress tracking

### 3. Data Preview
Before generating reports, you can preview live data from any integration to verify quality and accuracy.

### 3. Data Storage
Synced data is stored securely and used for:
- Customer mapping
- Report generation
- Trend analysis

### 4. Customer Mapping
Customers from each integration are mapped to your master customer list for unified reporting.

## Integration Status Indicators

- **Active** (Green) - Connected and syncing normally
- **Syncing** (Blue) - Currently pulling data
- **Warning** (Yellow) - Minor issues, check configuration
- **Error** (Red) - Connection failed, requires attention
- **Disconnected** (Grey) - Integration disabled

## Best Practices

1. **Keep credentials secure** - Use API keys with minimal required permissions
2. **Monitor sync status** - Check regularly for any sync failures
3. **Map customers promptly** - Keep customer mappings up to date
4. **Review data quality** - Ensure synced data looks correct
      `,
      relatedArticles: ['adding-integration', 'integration-sync', 'integration-troubleshooting']
    },
    {
      id: 'adding-integration',
      title: 'Adding a New Integration',
      description: 'Step-by-step guide to connecting a new integration',
      icon: 'Plus',
      content: `
# Adding a New Integration

This guide walks you through adding a new integration to the platform.

## Prerequisites

Before adding an integration, ensure you have:
- **Admin or Integration Manager permissions**
- **API credentials** for the system you're connecting
- **License** for the integration type (if applicable)

## Step-by-Step Process

### Step 1: Navigate to Integrations
1. Click **Integrations** in the sidebar
2. Click the **Add Integration** button

### Step 2: Select Integration Type
1. Browse available integration types
2. Click on the integration you want to add
3. Review the integration description and requirements

### Step 3: Enter Credentials
Depending on the integration, you'll need to provide:

**For API Key Authentication:**
- API Key
- API Secret (if required)
- API URL/Endpoint (if customisable)

**For OAuth Authentication:**
- Click "Connect" to be redirected to the service
- Authorise the connection
- You'll be redirected back automatically

### Step 4: Configure Settings
1. **Display Name** - A friendly name for this integration
2. **Sync Schedule** - How often to sync (default is usually fine)
3. **Historical Import** - Whether to import historical data

### Step 5: Test Connection
1. Click **Test Connection**
2. Wait for the test to complete
3. Review any warnings or errors

### Step 6: Save and Activate
1. Click **Save Integration**
2. The integration will begin its initial sync
3. Monitor progress in the integration details

## Post-Setup Tasks

### Initial Sync
The first sync may take longer as it imports all current data.

### Historical Import
If enabled, historical data import runs in the background:
- Progress shown as percentage in integration details
- Can take several hours for large datasets
- Recent data prioritised first

### Data Preview
After sync completes:
1. Click on the integration card
2. Click **Preview Data**
3. View live data from the integration
4. Export preview to PDF if needed

### Customer Mapping
After the initial sync:
1. Go to **Customers > Map Customers**
2. Review auto-matched customers
3. Use **Bulk Accept** for high-confidence matches
4. Manually map any unmatched customers
      `,
      relatedArticles: ['integrations-overview', 'integration-sync', 'autotask-setup']
    },
    {
      id: 'integration-sync',
      title: 'Understanding Sync Operations',
      description: 'How data syncing works and how to manage it',
      icon: 'RefreshCw',
      content: `
# Understanding Sync Operations

Syncing is how the platform keeps data up-to-date from your connected integrations.

## Types of Syncs

### Current State Sync
- Pulls the latest data from the integration
- Runs automatically on a schedule
- Updates existing records and adds new ones
- Typically takes a few minutes

### Historical Import
- One-time import of historical data
- Runs when you first add an integration (if enabled)
- Can import weeks or months of past data
- May take several hours for large datasets

### Manual Sync
- Triggered by you when needed
- Useful after making changes in the source system
- Same as a current state sync

## Sync Schedules

Integrations sync automatically based on their configured schedule:
- **Every 15 minutes** - For real-time critical data
- **Hourly** - Standard for most integrations
- **Every 4 hours** - For less time-sensitive data
- **Daily** - For systems with infrequent changes

## Triggering a Manual Sync

1. Go to **Integrations**
2. Find the integration you want to sync
3. Click the **Sync Now** button
4. Wait for the sync to complete
5. Review the sync results

## Sync Logs

Each sync creates a log entry with:
- **Start time** - When the sync began
- **End time** - When it completed
- **Status** - Success, partial, or failed
- **Records processed** - How many records were synced
- **Errors** - Any issues encountered

## Best Practices

1. **Don't over-sync** - More frequent isn't always better
2. **Monitor failures** - Address sync errors promptly
3. **Check after changes** - Sync after major changes in source systems
4. **Review logs** - Periodically check sync logs for issues
      `,
      relatedArticles: ['integrations-overview', 'integration-troubleshooting']
    },
    {
      id: 'integration-troubleshooting',
      title: 'Troubleshooting Integrations',
      description: 'Common integration issues and how to resolve them',
      icon: 'AlertTriangle',
      content: `
# Troubleshooting Integrations

This guide helps you diagnose and resolve common integration issues.

## Connection Failed

**Symptoms:**
- Integration shows "Error" status
- "Connection failed" message

**Solutions:**
1. **Invalid credentials** - Re-enter API credentials
2. **Service unavailable** - Check if the external service is online
3. **Network issues** - Verify firewall allows outbound connections
4. **API permissions** - Ensure API user has required permissions

## Sync Failures

**Symptoms:**
- Syncs complete with errors
- Missing or outdated data

**Solutions:**
1. **Rate limiting** - Reduce sync frequency
2. **Data format issues** - Check source data for unusual characters
3. **Timeout errors** - Try syncing during off-peak hours

## Missing Customers

**Symptoms:**
- Customers from integration not appearing

**Solutions:**
1. **Sync not complete** - Wait for initial sync to finish
2. **Filter settings** - Review integration filter configuration
3. **Permission issues** - Verify API user can access all customers

## Diagnostic Steps

1. **Check Integration Status** - Go to Integrations, review status
2. **Review Sync Logs** - Look for failed syncs and error details
3. **Test Connection** - Click Test Connection button
4. **Verify Credentials** - Check credentials haven't expired

## When to Contact Support

Contact support if:
- Issues persist after troubleshooting
- Error messages are unclear
- You suspect a platform bug
      `,
      relatedArticles: ['integration-sync', 'adding-integration', 'monitoring']
    }
  ]
}
