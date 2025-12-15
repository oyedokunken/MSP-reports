# Integration Sync Strategies - Complete Guide

## Overview

This document explains what each integration fetches, how often, and what time windows/ranges are used.

---

## Quick Reference Table

| Integration | Sync Frequency | Regular Sync Window | Historical Range | Data Change Rate |
|------------|---------------|-------------------|------------------|------------------|
| **Datto** | 5 min | 1 hour (redundant) | 90 days | High (alerts) |
| **Autotask** | 10 min | 10 minutes | 30 days | Medium (tickets) |
| **ITGlue** | 6 hours | Current snapshot | N/A (snapshot) | Low (docs) |
| **LibreNMS** | 10 min | Current state | 30 days | High (devices) |
| **SaaS Alerts** | 6 hours | Current customers | 365 days (events) | Low (security) |
| **Sendmarc** | 6 hours | Current snapshot | 90 days | Low (email sec) |
| **Barracuda** | 6 hours | Current snapshot | 90 days | Low (email sec) |
| **BitDefender** | 6 hours | Current snapshot | 30 days | Low (endpoints) |

---

## 1. Datto RMM Integration

### What It Is
**Real-time Monitoring & Management (RMM)** platform tracking device health, alerts, and uptime.

### Regular Sync (Every 5 Minutes)

**Fetches:**
```php
sync() {
    1. Customers/Sites         → Full list (~110 sites)
    2. Open Alerts (all sites)  → Current state
    3. Resolved Alerts          → Last 1 HOUR window
    4. Devices                  → Every 30 min (separate command)
}
```

**Time Window Strategy:**
- **Open alerts**: All current (no window)
- **Resolved alerts**: Last **1 hour** (redundant fetching)
- **Why 1 hour?**: Safety/reliability - each alert fetched ~12 times before aging out

**Example:**
```
10:00 sync → Fetches resolved from 09:00-10:00
10:05 sync → Fetches resolved from 09:05-10:05  (overlaps previous)
10:10 sync → Fetches resolved from 09:10-10:10  (still overlaps)
...continues until alert is 1 hour old
```

### Historical Import

**Fetches:**
```php
getHistoricalData($startDate, $endDate) {
    1. Devices (current snapshot only)
    2. Open alerts (current state)
    3. Resolved alerts → Full date range ($startDate to $endDate)
}
```

**Recommended Range**: **90 days**
- Alerts older than 90 days rarely needed
- Reduces API calls
- Still captures quarterly trends

---

## 2. Autotask PSA Integration

### What It Is
**Professional Services Automation (PSA)** - tickets, time tracking, billing.

### Regular Sync (Every 10 Minutes)

**Fetches:**
```php
sync() {
    1. Companies               → Full list (~484 companies)
    2. Tickets                 → Last 10 MINUTES (lastActivityDate filter)
    3. Time Entries            → Last 10 MINUTES (dateWorked filter)
}
```

**Time Window**: **10 minutes** (matches sync frequency)
- No overlap/redundancy
- Catches all activity in rolling 10-min window
- Uses `lastActivityDate` (catches updates, not just new tickets)

### Historical Import

**Fetches:**
```php
getHistoricalData($startDate, $endDate) {
    1. Companies (current list)
    2. Tickets → Full date range (lastActivityDate)
    3. Time Entries → Full date range (dateWorked)
    4. Resources/Employees → Full list (active only)
}
```

**Recommended Range**: **30 days**
- Tickets: 30 days covers active tickets
- Time entries: Month-end billing cycle
- Resources: Monthly refresh

**Processing**: Chunks by day to manage memory

---

## 3. ITGlue Integration

### What It Is
**IT Documentation Platform** - static/snapshot data (SSL certs, domains, licenses, assets).

### Regular Sync (Every 6 Hours)

**Fetches:**
```php
sync() {
    1. Organizations           → Full list (current state)
    2. SSL Certificates        → Expiring in next 90 DAYS
    3. Domains                 → Expiring in next 90 DAYS
    4. Flexible Assets         → License types only (filtered by keywords)
}
```

**Time Window**: **90 days** (expiry window, not activity window)
- Not time-series activity
- Static documentation that rarely changes
- 90 days = enough lead time for renewal alerts

**Why 6 Hours?**
- Documentation changes infrequently
- No need for frequent polling
- Much cheaper on API limits

### Historical Import

**Fetches:**
```php
getHistoricalData($startDate, $endDate) {
    // IGNORES $startDate and $endDate - snapshot only!
    
    1. Organizations  → Current snapshot
    2. SSL Certs      → Next 90 days
    3. Domains        → Next 90 days
    4. Flexible Assets → ALL types (not just licenses)
}
```

**Recommended Range**: **1 day** (parameter ignored anyway)
- ITGlue has no historical time-series
- Always fetches current state
- Historical import just populates ALL asset types (not just licenses)

---

## 4. LibreNMS Integration

### What It Is
**Network Monitoring System** - device availability, uptime, performance metrics.

### Regular Sync (Every 10 Minutes)

**Fetches:**
```php
sync() {
    1. Devices                 → All devices (current state)
    2. Device Details          → For each device
    3. Availability Metrics    → Recent availability %
    4. Uptime Data             → Current uptime stats
    5. Device Groups           → Group associations
}
```

**Time Window**: **Current state** (no historical window in regular sync)
- Fetches current device snapshot
- Stores as time-series for tracking changes
- No rolling window like Datto

### Historical Import

**Fetches:**
```php
getHistoricalData($startDate, $endDate) {
    1. Devices → Current snapshot for each day in range
    2. Creates historical timeline of device states
}
```

**Recommended Range**: **30 days**
- Device state history
- Availability trends
- Capacity planning data

---

## 5. SaaS Alerts Integration

### What It Is
**Security monitoring for SaaS applications** (Microsoft 365, Google Workspace, etc.) - tracks security events like login anomalies, permission changes, data exfiltration.

### Regular Sync (Every 6 Hours)

**Fetches:**
```php
sync() {
    1. Customers → Full list (current state)
    // NO EVENTS in regular sync!
}
```

**What's Missing?**
- Regular sync does NOT fetch security events
- Only syncs customer list
- Events are historical import only

**Why 6 Hours?**
- Customer list changes rarely
- Security events handled separately

### Historical Import

**Fetches:**
```php
getHistoricalData($startDate, $endDate) {
    For each customer:
        1. Security Events → Full date range (start to end)
           - Login anomalies
           - Permission changes
           - Data access events
           - Configuration changes
}
```

**Recommended Range**: **365 days** (full year)
- Security events for compliance
- Audit trail requirements
- Incident investigation
- Trend analysis

**Processing**: 
- 1000 events per request
- Chunks of 50 for processing

---

## 6. Sendmarc Integration

### What It Is
**Email security monitoring** - DMARC, SPF, DKIM compliance for email authentication.

### Regular Sync (Every 6 Hours)

**Fetches:**
```php
sync() {
    1. Domains → Full list (current state)
    // Only customer/domain list
}
```

### Historical Import

**Fetches:**
```php
getHistoricalData($startDate, $endDate) {
    For each domain:
        1. DMARC Reports → Date range
        2. SPF Records → Current state
        3. DKIM Status → Current state
}
```

**Recommended Range**: **90 days**
- Email authentication reports
- Quarterly compliance review

---

## 7. Barracuda Integration

### What It Is
**Email security appliance** - spam filtering, threat protection.

### Regular Sync (Every 6 Hours)

**Fetches:**
```php
sync() {
    1. Customers → Full list
    // Only customer list
}
```

### Historical Import

**Fetches:**
```php
getHistoricalData($startDate, $endDate) {
    For each customer:
        1. Security Reports → Date range
        2. Blocked Threats → Date range
        3. Email Statistics → Date range
}
```

**Recommended Range**: **90 days**
- Threat reports
- Email volume trends

---

## 8. BitDefender Integration

### What It Is
**Endpoint security & antivirus** - malware detection, threat protection for devices.

### Regular Sync (Every 6 Hours)

**Fetches:**
```php
sync() {
    For each company:
        1. Endpoints → Current list
        2. Threat Detections → Current threats
}
```

### Historical Import

**Fetches:**
```php
getHistoricalData($startDate, $endDate) {
    // IGNORES date range - fetches current state
    
    For each company:
        1. Endpoints → Current snapshot
        2. Threat Detections → All current threats
}
```

**Recommended Range**: **30 days**
- Endpoint inventory
- Threat history

---

## Sync Strategy Patterns

### Pattern 1: High-Frequency Real-Time (5-10 min)

**Used for**: Operational monitoring (Datto, Autotask, LibreNMS)

**Characteristics:**
- Frequent changes (alerts, tickets, device status)
- Time-sensitive (need quick visibility)
- Rolling time window (1 hour or sync interval)
- Heavy time-series data

**Example (Datto)**:
```
Regular Sync (ever 5 min):
  - Last 1 hour of resolved alerts (redundant)
  - All open alerts
  
Historical Import (once):
  - 90 days of resolved alerts
  - Current device snapshots
```

### Pattern 2: Low-Frequency Snapshot (6 hours)

**Used for**: Documentation/static data (ITGlue, SaaS, Sendmarc, Barracuda, BitDefender)

**Characteristics:**
- Infrequent changes
- Static/configuration data
- No rolling window (current state only)
- Light data volume

**Example (ITGlue)**:
```
Regular Sync (every 6 hours):
  - Current organizations
  - SSL certs expiring in next 90 days
  
Historical Import (once):
  - Same as regular sync
  - Fetches ALL asset types (not just licenses)
```

### Pattern 3: Hybrid (Customers + Events)

**Used for**: SaaS Alerts

**Characteristics:**
- Regular sync: Customer list only
- Historical import: Event data with date range
- Separates config from activity

**Example**:
```
Regular Sync (every 6 hours):
  - Customer list only
  
Historical Import (once):
  - 365 days of security events per customer
```

---

## Recommended Historical Import Ranges

| Integration | Range | Reason |
|------------|-------|--------|
| Datto | 90 days | Alert history, device trends |
| Autotask | 30 days | Active tickets, monthly time entries |
| ITGlue | 1 day* | *Ignores parameter, fetches current snapshot |
| LibreNMS | 30 days | Device availability trends |
| SaaS Alerts | 365 days | Security compliance, audit trail |
| Sendmarc | 90 days | Email authentication reports |
| Barracuda | 90 days | Threat reports |
| BitDefender | 30 days | Endpoint inventory |

---

## Time Window Strategies Explained

### 1. Redundant Window (Datto)

**Concept**: Fetch same data multiple times for reliability

```
Alert resolved at 10:00 AM:
- 10:05 sync → Fetches 09:05-10:05 (includes alert)
- 10:10 sync → Fetches 09:10-10:10 (includes alert again)
- 10:15 sync → Fetches 09:15-10:15 (includes alert again)
...12 times total before aging out at 11:05
```

**Pros**: Never miss data if sync fails  
**Cons**: More API calls, more database inserts

### 2. Exact Window (Autotask)

**Concept**: Fetch only new/changed  data since last sync

```
10:00 sync → Fetches 09:50-10:00 (10 min window)
10:10 sync → Fetches 10:00-10:10 (next 10 min window)
10:20 sync → Fetches 10:10-10:20 (next 10 min window)
```

**Pros**: Efficient, no redundancy  
**Cons**: If sync fails, might miss data

### 3. Snapshot (ITGlue, BitDefender)

**Concept**: No time window, just current state

```
10:00 sync → Fetches current state
16:00 sync → Fetches current state (may be identical)
22:00 sync → Fetches current state
```

**Pros**: Simple, low API usage  
**Cons**: No historical timeline (relies on snapshots stored over time)

---

## Summary

**High-Activity Integrations** (Need frequent monitoring):
- Datto: 5 min sync, 1-hour redundant window
- Autotask: 10 min sync, 10-min exact window
- LibreNMS: 10 min sync, current state snapshot

**Low-Activity Integrations** (Infrequent changes):
- ITGlue: 6 hours, current snapshot
- SaaS Alerts: 6 hours customers, historical events on demand
- Sendmarc: 6 hours, current snapshot
- Barracuda: 6 hours, current snapshot
- BitDefender: 6 hours, current snapshot

**All integrations** use TimescaleDB time-series pattern (always `create()`, never deduplicate).
