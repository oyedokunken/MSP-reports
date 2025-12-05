# Datto RMM API Endpoints

## Overview
This document provides comprehensive information about Datto RMM API endpoints, authentication, and testing procedures.

---

## Authentication

### OAuth2 Token Endpoint

**URL:** `POST {baseUrl}/auth/oauth/token`

**Example:** `POST https://syrah-api.centrastage.net/auth/oauth/token`

**Headers:**
```
Accept: application/json
Content-Type: application/x-www-form-urlencoded
```

**Authorization:**
```
Type: Basic Auth
Username: public-client
Password: public
```

**Body (x-www-form-urlencoded):**
```
grant_type: password
username: {YOUR_API_KEY}
password: {YOUR_API_SECRET}
```

**Example Credentials (TTS Production):**
```
username: ***REMOVED_DATTO_API_KEY***
password: ***REMOVED_DATTO_API_SECRET***
```

**Response:**
```json
{
  "access_token": "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...",
  "token_type": "bearer",
  "expires_in": 360000,
  "refresh_token": "..."
}
```

**Token Details:**
- **Expiry:** 100 hours (360,000 seconds)
- **Cache Duration:** 99 hours (to ensure refresh before expiry)
- **Storage:** Cached in Redis with key `datto_rmm_token_{source_id}`

---

## API Endpoints

### Base URL Structure
```
{baseUrl}/api/v2/{endpoint}
```

**Example Base URL:** `https://syrah-api.centrastage.net/api/v2`

**All API requests require:**
```
Authorization: Bearer {access_token}
Accept: application/json
```

---

## 1. Sites (Customers)

### Get All Sites

**Endpoint:** `GET /api/v2/account/sites`

**Query Parameters:**
- `max` - Maximum results per page (default: 250, max: 250)
- `page` - Page number (starts at 0)

**Full URL Example:**
```
GET https://syrah-api.centrastage.net/api/v2/account/sites?max=250&page=0
```

**Real Response Example:**
```json
{
    "pageDetails": {
        "count": 111,
        "totalCount": 111,
        "prevPageUrl": null,
        "nextPageUrl": null
    },
    "sites": [
        {
            "id": 143681,
            "uid": "fb190437-1a25-46ea-b4ec-dd57b98f8377",
            "accountUid": "syref600001",
            "name": "Managed",
            "description": "This is the default site for your Managed devices.",
            "notes": null,
            "onDemand": false,
            "splashtopAutoInstall": true,
            "proxySettings": null,
            "devicesStatus": {
                "numberOfDevices": 10,
                "numberOfOnlineDevices": 1,
                "numberOfOfflineDevices": 9
            },
            "autotaskCompanyName": null,
            "autotaskCompanyId": null,
            "portalUrl": "https://syrah.rmm.datto.com/site/143681"
        },
        {
            "id": 143682,
            "uid": "04f2f069-e101-4cc0-88af-b5f239a8b7f9",
            "accountUid": "syref600001",
            "name": "OnDemand",
            "description": "This is the default site for your OnDemand devices.",
            "notes": null,
            "onDemand": true,
            "splashtopAutoInstall": true,
            "proxySettings": null,
            "devicesStatus": {
                "numberOfDevices": 38,
                "numberOfOnlineDevices": 0,
                "numberOfOfflineDevices": 38
            },
            "autotaskCompanyName": null,
            "autotaskCompanyId": null,
            "portalUrl": "https://syrah.rmm.datto.com/site/143682"
        }
    ]
}
```

**Key Fields:**
- `uid` - Unique site identifier (use this for device/alert queries)
- `autotaskCompanyId` - Links to Autotask PSA company
- `devicesStatus` - Real-time device counts

---

## 2. Devices

### Get Devices for Site

**Endpoint:** `GET /api/v2/site/{siteUid}/devices`

**Query Parameters:**
- `max` - Maximum results per page (default: 250, max: 250)
- `page` - Page number (starts at 0)

**Full URL Example:**
```
GET https://syrah-api.centrastage.net/api/v2/site/fb190437-1a25-46ea-b4ec-dd57b98f8377/devices?max=250&page=0
```

**Real Response Example:**
```json
{
    "pageDetails": {
        "count": 10,
        "prevPageUrl": null,
        "nextPageUrl": null
    },
    "devices": [
        {
            "id": 2499432,
            "uid": "7c8a8964-1e7b-ad59-3201-8ed3a7c2afe4",
            "siteId": 143681,
            "siteUid": "fb190437-1a25-46ea-b4ec-dd57b98f8377",
            "siteName": "Managed",
            "deviceType": {
                "category": "Desktop",
                "type": "Desktop"
            },
            "hostname": "HDBF-PC37",
            "intIpAddress": "192.168.40.132",
            "operatingSystem": "Microsoft Windows 11 Pro 10.0.26100",
            "lastLoggedInUser": "AzureAD\\IsabelRichards",
            "domain": "WORKGROUP",
            "cagVersion": "10062",
            "displayVersion": "4.4.10062.10062",
            "extIpAddress": "139.130.35.174",
            "description": "HDBF-PC37",
            "a64Bit": true,
            "rebootRequired": true,
            "online": false,
            "suspended": false,
            "deleted": false,
            "lastSeen": 1756797354000,
            "lastReboot": 1753660325000,
            "lastAuditDate": 1756776607000,
            "creationDate": 1752285586000,
            "udf": {
                "udf1": null,
                "udf2": null,
                "udf3": "Diskname: NVMe Micron_3400_MTFDKBA1T0TFH MediaType: SSD Bustype:NVMe / ",
                "udf4": null,
                "udf5": null,
                "udf6": null,
                "udf7": null,
                "udf8": null,
                "udf9": "6CRNW-WM7WR-6F9QK-DVVMM-QJ3PJ (Windows)",
                "udf10": null,
                "udf11": null,
                "udf12": null,
                "udf13": null,
                "udf14": null,
                "udf15": null,
                "udf16": null,
                "udf17": null,
                "udf18": null,
                "udf19": null,
                "udf20": null,
                "udf21": null,
                "udf22": null,
                "udf23": null,
                "udf24": null,
                "udf25": null,
                "udf26": null,
                "udf27": null,
                "udf28": null,
                "udf29": null,
                "udf30": null
            },
            "snmpEnabled": false,
            "deviceClass": "device",
            "portalUrl": "https://syrah.rmm.datto.com/device/2499432",
            "warrantyDate": null,
            "antivirus": {
                "antivirusProduct": "ESET Endpoint Security",
                "antivirusStatus": "RunningAndUpToDate"
            },
            "patchManagement": {
                "patchStatus": "RebootRequired",
                "patchesApprovedPending": 4,
                "patchesNotApproved": 2,
                "patchesInstalled": 2
            },
            "softwareStatus": "Not Compliant",
            "webRemoteUrl": "https://syrah.centrastage.net/csm/remote/rto/2499432?ref=datto-rmm-api",
            "networkProbe": false,
            "onboardedViaNetworkMonitor": false
        },
        {
            "id": 2505091,
            "uid": "d5046374-92a4-5821-8e3e-1dad3f3abfee",
            "siteId": 143681,
            "siteUid": "fb190437-1a25-46ea-b4ec-dd57b98f8377",
            "siteName": "Managed",
            "deviceType": {
                "category": "Desktop",
                "type": "Desktop"
            },
            "hostname": "HDBF-PC30",
            "intIpAddress": "192.168.40.60",
            "operatingSystem": "Microsoft Windows 11 Enterprise 10.0.22631",
            "lastLoggedInUser": "AzureAD\\HarnShengKhor",
            "domain": "WORKGROUP",
            "cagVersion": "10162",
            "displayVersion": "4.4.10162.10162",
            "extIpAddress": "139.130.35.174",
            "description": "HDBF-PC30",
            "a64Bit": true,
            "rebootRequired": false,
            "online": false,
            "suspended": false,
            "deleted": false,
            "lastSeen": 1759884777000,
            "lastReboot": 1759884527000,
            "lastAuditDate": 1759883532000,
            "creationDate": 1752703649000,
            "udf": {
                "udf1": null,
                "udf2": null,
                "udf3": "Diskname: NVMe WD PC SN740 SDDQNQD-512G-1014 MediaType: SSD Bustype:NVMe / ",
                "udf4": null,
                "udf5": null,
                "udf6": null,
                "udf7": null,
                "udf8": null,
                "udf9": "NMBP9-Q3CMK-3V4HY-H647D-MDW48 (Windows)",
                "udf10": null,
                "udf11": null,
                "udf12": null,
                "udf13": null,
                "udf14": null,
                "udf15": null,
                "udf16": null,
                "udf17": null,
                "udf18": null,
                "udf19": null,
                "udf20": null,
                "udf21": null,
                "udf22": null,
                "udf23": null,
                "udf24": null,
                "udf25": null,
                "udf26": null,
                "udf27": null,
                "udf28": null,
                "udf29": null,
                "udf30": null
            },
            "snmpEnabled": false,
            "deviceClass": "device",
            "portalUrl": "https://syrah.rmm.datto.com/device/2505091",
            "warrantyDate": null,
            "antivirus": {
                "antivirusProduct": "ESET Endpoint Security",
                "antivirusStatus": "RunningAndUpToDate"
            },
            "patchManagement": {
                "patchStatus": "FullyPatched",
                "patchesApprovedPending": 0,
                "patchesNotApproved": 5,
                "patchesInstalled": 5
            },
            "softwareStatus": "Not Compliant",
            "webRemoteUrl": "https://syrah.centrastage.net/csm/remote/rto/2505091?ref=datto-rmm-api",
            "networkProbe": false,
            "onboardedViaNetworkMonitor": false
        }
    ]
}
```

**Key Fields:**
- `uid` - Unique device identifier
- `online` - Boolean device status
- `lastSeen` - Unix timestamp in milliseconds
- `udf` - Custom fields containing hardware details
- `antivirus` - Antivirus product and status
- `patchManagement` - Windows Update status

**UDF Field Parsing:**
- `udf1` - System info (memory, processor, storage, TPM)
- `udf3` - Disk drive details
- `udf9` - Windows product key

---

### Get Single Device

**Endpoint:** `GET /api/v2/devices/{deviceUid}`

**Full URL Example:**
```
GET https://syrah-api.centrastage.net/api/v2/devices/7c8a8964-1e7b-ad59-3201-8ed3a7c2afe4
```

**Response:** Returns the same structure as a single device from the devices list endpoint (see above).

**Use Case:** Fetch detailed information for a specific device by UID.

**Note:** The response structure is identical to individual device objects in the `/site/{siteUid}/devices` endpoint.

---

## 3. Alerts

### Get Open Alerts for Site

**Endpoint:** `GET /api/v2/site/{siteUid}/alerts/open`

**Full URL Example:**
```
GET https://syrah-api.centrastage.net/api/v2/site/fb190437-1a25-46ea-b4ec-dd57b98f8377/alerts/open
```

**Real Response Example:**
```json
{
    "pageDetails": {
        "count": 1,
        "prevPageUrl": null,
        "nextPageUrl": null
    },
    "alerts": [
        {
            "alertUid": "e1acb586-8fee-4640-8c48-d8885d4e5bf8",
            "priority": "Information",
            "diagnostics": "W32Time started",
            "resolved": false,
            "resolvedBy": null,
            "resolvedOn": null,
            "muted": false,
            "ticketNumber": null,
            "timestamp": 1757970535000,
            "alertMonitorInfo": {
                "sendsEmails": false,
                "createsTicket": false
            },
            "alertContext": {
                "@class": "srvc_status_ctx",
                "serviceName": "W32Time",
                "status": "STOPPED"
            },
            "alertSourceInfo": {
                "deviceUid": "bced82ca-d9e4-c6d1-ebf6-53221df1a373",
                "deviceName": "HDBF-PC33",
                "siteUid": "fb190437-1a25-46ea-b4ec-dd57b98f8377",
                "siteName": "Managed"
            },
            "responseActions": null,
            "autoresolveMins": 5
        }
    ]
}
```

**Key Fields:**
- `alertUid` - Unique alert identifier
- `priority` - Critical, High, Warning, Information
- `resolved` - Boolean alert status (false for open alerts)
- `diagnostics` - Human-readable alert message
- `alertContext` - Alert-specific context (varies by alert type)
- `alertSourceInfo` - Device and site information
- `autoresolveMins` - Minutes until auto-resolution

---

### Get Resolved Alerts for Site

**Endpoint:** `GET /api/v2/site/{siteUid}/alerts/resolved`

**Query Parameters:**
- `resolvedSince` - ISO 8601 timestamp (optional, filters alerts resolved after this time)

**Full URL Examples:**
```
# Without filter (gets all resolved alerts)
GET https://syrah-api.centrastage.net/api/v2/site/fb190437-1a25-46ea-b4ec-dd57b98f8377/alerts/resolved

# With timestamp filter
GET https://syrah-api.centrastage.net/api/v2/site/fb190437-1a25-46ea-b4ec-dd57b98f8377/alerts/resolved?resolvedSince=2025-10-31T00:00:00Z
```

**Timestamp Format:** `YYYY-MM-DDTHH:MM:SSZ` (ISO 8601 UTC)

**Real Response Example:**
```json
{
    "pageDetails": {
        "count": 12,
        "prevPageUrl": null,
        "nextPageUrl": null
    },
    "alerts": [
        {
            "alertUid": "cd5e8626-afe0-4246-9d4a-c378ce5bd594",
            "priority": "Information",
            "diagnostics": "W32Time started",
            "resolved": true,
            "resolvedBy": "CAG",
            "resolvedOn": 1763688303132,
            "muted": false,
            "ticketNumber": null,
            "timestamp": 1763650530000,
            "alertMonitorInfo": {
                "sendsEmails": false,
                "createsTicket": false
            },
            "alertContext": {
                "@class": "srvc_status_ctx",
                "serviceName": "W32Time",
                "status": "STOPPED"
            },
            "alertSourceInfo": {
                "deviceUid": "b965fc2f-2f45-a707-e154-3ead54d6f4c0",
                "deviceName": "CCNT2025",
                "siteUid": "fb190437-1a25-46ea-b4ec-dd57b98f8377",
                "siteName": "Managed"
            },
            "responseActions": null,
            "autoresolveMins": 5
        },
        {
            "alertUid": "05785b41-71a0-4992-9cb5-cf1dd148c81c",
            "priority": "Information",
            "diagnostics": "W32Time started",
            "resolved": true,
            "resolvedBy": "CAG",
            "resolvedOn": 1760486486849,
            "muted": false,
            "ticketNumber": null,
            "timestamp": 1760325620000,
            "alertMonitorInfo": {
                "sendsEmails": false,
                "createsTicket": false
            },
            "alertContext": {
                "@class": "srvc_status_ctx",
                "serviceName": "W32Time",
                "status": "STOPPED"
            },
            "alertSourceInfo": {
                "deviceUid": "5a96ec50-7cab-2148-58e9-c0c3f18afd2c",
                "deviceName": "CCNT4024",
                "siteUid": "fb190437-1a25-46ea-b4ec-dd57b98f8377",
                "siteName": "Managed"
            },
            "responseActions": null,
            "autoresolveMins": 5
        }
    ]
}
```

**Key Fields:**
- `resolved` - Boolean (true for resolved alerts)
- `resolvedBy` - Who/what resolved the alert (e.g., "CAG" for auto-resolved, or username)
- `resolvedOn` - Unix timestamp in milliseconds when resolved
- `timestamp` - Unix timestamp in milliseconds when alert was created

---

## Rate Limiting

**Datto RMM Rate Limits:**
- Not explicitly documented by Datto
- HTTP 429 responses indicate rate limit exceeded
- Recommended: 60-second wait before retry

**Driver Behavior:**
- Automatic retry on 429 with 60-second delay
- Token refresh on 401 responses
- Exponential backoff for repeated failures

**Best Practices:**
- Use pagination (max=250) to reduce API calls
- Cache token for 99 hours
- Implement request queuing for bulk operations
- Monitor rate limit headers (if provided)

---

## Postman Testing

### Step 1: Get Access Token

**Request:** `POST https://syrah-api.centrastage.net/auth/oauth/token`

**Authorization Tab:**
- Type: `Basic Auth`
- Username: `public-client`
- Password: `public`

**Body (x-www-form-urlencoded):**
```
grant_type: password
username: ***REMOVED_DATTO_API_KEY***
password: ***REMOVED_DATTO_API_SECRET***
```

**Copy the `access_token` from response.**

---

### Step 2: Test Sites Endpoint

**Request:** `GET https://syrah-api.centrastage.net/api/v2/account/sites?max=250&page=0`

**Headers:**
```
Authorization: Bearer {YOUR_ACCESS_TOKEN}
Accept: application/json
```

**Copy a `site.uid` from response.**

---

### Step 3: Test Devices Endpoint

**Request:** `GET https://syrah-api.centrastage.net/api/v2/site/{SITE_UID}/devices?max=250&page=0`

**Headers:**
```
Authorization: Bearer {YOUR_ACCESS_TOKEN}
Accept: application/json
```

---

### Step 4: Test Alerts Endpoint

**Request:** `GET https://syrah-api.centrastage.net/api/v2/site/{SITE_UID}/alerts/open`

**Headers:**
```
Authorization: Bearer {YOUR_ACCESS_TOKEN}
Accept: application/json
```

---

## Postman Environment Variables

Create a Postman environment with these variables:

```json
{
  "base_url": "https://syrah-api.centrastage.net",
  "api_key": "***REMOVED_DATTO_API_KEY***",
  "api_secret": "***REMOVED_DATTO_API_SECRET***",
  "access_token": ""
}
```

**Pre-request Script (Collection Level):**
```javascript
// Auto-refresh token if not present
if (!pm.environment.get("access_token")) {
    pm.sendRequest({
        url: pm.environment.get("base_url") + "/auth/oauth/token",
        method: 'POST',
        header: {
            'Accept': 'application/json',
            'Content-Type': 'application/x-www-form-urlencoded',
            'Authorization': 'Basic ' + btoa('public-client:public')
        },
        body: {
            mode: 'urlencoded',
            urlencoded: [
                {key: 'grant_type', value: 'password'},
                {key: 'username', value: pm.environment.get("api_key")},
                {key: 'password', value: pm.environment.get("api_secret")}
            ]
        }
    }, function (err, response) {
        if (err) {
            console.log(err);
        } else {
            var jsonData = response.json();
            pm.environment.set("access_token", jsonData.access_token);
            console.log("Token refreshed successfully");
        }
    });
}
```

**Use in Requests:**
```
URL: {{base_url}}/api/v2/account/sites?max=250&page=0
Authorization: Bearer {{access_token}}
```

---

## Error Handling

### Common HTTP Status Codes

**401 Unauthorized:**
- Token expired or invalid
- Solution: Refresh token using auth endpoint

**403 Forbidden:**
- API key lacks permissions
- Solution: Verify API key has correct access level

**404 Not Found:**
- Invalid site UID or device UID
- Solution: Verify UID exists using sites endpoint

**429 Too Many Requests:**
- Rate limit exceeded
- Solution: Wait 60 seconds, implement backoff

**500 Internal Server Error:**
- Datto RMM service issue
- Solution: Retry after delay, check Datto status page

---

## Data Mapping

### Platform Detection
```php
if ($device['deviceType']['type'] === 'Router') {
    return 'Router/Network Device';
}
return $device['a64Bit'] ? 'Windows (64bit)' : 'Windows (32bit)';
```

### Severity Mapping
```php
'critical', 'emergency' => 'critical'
'high', 'error' => 'high'
'warning', 'moderate' => 'medium'
'information', 'info', 'notice' => 'low'
```

### Timestamp Conversion
```php
// Datto uses milliseconds since epoch
$timestamp = Carbon::createFromTimestampMs($device['lastSeen']);
```

---

## Integration Architecture

### Driver Location
```
/home/kristian/reporting/tts/app/Services/Sources/Drivers/DattoRMMDriver.php
```

### Key Methods

**Authentication:**
- `getAccessToken()` - Obtains and caches OAuth2 token

**Data Sync:**
- `pull()` - Syncs sites and alerts
- `pollDevices()` - Syncs devices for all sites
- `updateSites()` - Updates site list

**Data Processing:**
- `syncDeviceToAgent()` - Maps device to Agent model
- `processAlert()` - Maps alert to Alert model

### Database Models

**AgentSite:**
- Stores Datto RMM sites
- Links to `source_id`
- Contains device counts and metadata

**Agent:**
- Stores Datto RMM devices
- Links to `source_id` and `site_uid`
- Contains hardware, software, and status info

**Alert:**
- Stores Datto RMM alerts
- Links to `source_id` and `agent_id`
- Contains priority, status, and resolution info

---

## Cron Schedule

**Device Polling:**
```
*/5 * * * * php artisan poll:datto-devices
```
Runs every 5 minutes to update device status.

**Alert Sync:**
```
*/15 * * * * php artisan poll:sources
```
Runs every 15 minutes to sync alerts.

---

## Troubleshooting

### Token Issues
**Problem:** 401 Unauthorized on API calls
**Solution:** 
1. Clear token cache: `Cache::forget('datto_rmm_token_5')`
2. Verify API credentials are correct
3. Check token hasn't been revoked in Datto portal

### Missing Devices
**Problem:** Devices not appearing in database
**Solution:**
1. Verify site UID is correct
2. Check device polling logs: `storage/logs/datto.log`
3. Manually trigger poll: `php artisan poll:datto-devices`

### Pagination Issues
**Problem:** Not all sites/devices returned
**Solution:**
1. Check `pageDetails.nextPageUrl` in response
2. Implement pagination loop (see driver code)
3. Increase `max` parameter (up to 250)

### Rate Limiting
**Problem:** Frequent 429 errors
**Solution:**
1. Reduce polling frequency
2. Implement request queuing
3. Use historical import multiplier (0.5)

---

## Additional Resources

**Datto RMM API Documentation:**
- Official docs: https://help.aem.autotask.net/en/Content/APIs/APIs.htm

**Driver Implementation:**
- File: `/home/kristian/reporting/tts/app/Services/Sources/Drivers/DattoRMMDriver.php`
- Lines: 1-861

**Related Commands:**
- `php artisan poll:datto-devices` - Manual device poll
- `php artisan poll:sources` - Manual alert sync

---

## Summary of Real API Responses

### Verified Endpoints (TTS Production)

✅ **Authentication**
- Endpoint: `POST /auth/oauth/token`
- Status: Working
- Token Expiry: 100 hours (360,000 seconds)

✅ **Account Sites**
- Endpoint: `GET /api/v2/account/sites`
- Status: Working
- Total Sites: 111 (Managed + OnDemand)
- Response includes: `pageDetails`, `sites[]` with device counts

✅ **Site Devices**
- Endpoint: `GET /api/v2/site/{siteUid}/devices`
- Status: Working
- Example Site: `fb190437-1a25-46ea-b4ec-dd57b98f8377` (Managed)
- Response includes: Full device details, antivirus, patch status, UDF fields

✅ **Open Alerts**
- Endpoint: `GET /api/v2/site/{siteUid}/alerts/open`
- Status: Working
- Response includes: Alert details, device info, auto-resolve settings

✅ **Resolved Alerts**
- Endpoint: `GET /api/v2/site/{siteUid}/alerts/resolved`
- Status: Working
- Supports `resolvedSince` parameter for filtering
- Response includes: Resolution details, timestamps, resolved by info

### Key Findings

**Device Status:**
- Most devices are **offline** in the test environment
- Devices include full hardware details in `udf` fields
- Antivirus: ESET Endpoint Security
- Patch management tracked per device

**Alert Types:**
- Service status alerts (e.g., W32Time service)
- Auto-resolution after 5 minutes
- Resolved by "CAG" (Datto agent) or manual resolution

**Pagination:**
- All endpoints support `max` (up to 250) and `page` parameters
- `pageDetails` includes `count`, `totalCount`, `nextPageUrl`, `prevPageUrl`

---

**Last Updated:** January 13, 2025
**API Version:** v2
**Tested Against:** TTS Production Environment (Syrah Region)
**Driver Version:** 1.0
