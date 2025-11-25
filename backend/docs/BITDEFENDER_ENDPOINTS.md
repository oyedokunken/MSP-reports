# BitDefender GravityZone API Endpoints

## Overview

This document provides comprehensive information about BitDefender GravityZone API endpoints for testing and integration.

## Authentication

**Method:** HTTP Basic Authentication  
**Username:** API Key  
**Password:** (leave empty)

**API Key:**
```
ddff78bfef66982e91beaff8e336664ee272e5a93cb9145e3756cc773ce70cb2
```

**Base URL:**
```
https://cloud.gravityzone.bitdefender.com/api/v1.0/jsonrpc
```

## API Protocol

BitDefender uses **JSON-RPC 2.0** protocol. All requests follow this format:

```json
{
    "jsonrpc": "2.0",
    "method": "methodName",
    "params": {
        // method parameters
    },
    "id": "unique_request_id"
}
```

## Response Format

**Success Response:**
```json
{
    "id": "request_id",
    "jsonrpc": "2.0",
    "result": {
        // response data
    }
}
```

**Error Response:**
```json
{
    "id": "request_id",
    "jsonrpc": "2.0",
    "error": {
        "code": -32000,
        "message": "Error message",
        "data": {
            "details": "Detailed error description"
        }
    }
}
```

---

## ✅ Confirmed Working Endpoints

The following endpoints have been tested and confirmed working with the provided API key.

### Summary of Available Data

With the current API access, you can retrieve:

1. **Client Information** ✅
   - Company/client list with IDs and names
   - Method: `getCompaniesList`

2. **Device/Endpoint Information** ✅
   - All devices across all companies
   - Devices for specific company
   - Device details (name, OS, IP, MAC, policy, etc.)
   - Device counts per company
   - Method: `getEndpointsList`

3. **Security Incidents** ❌
   - NOT available with current API access
   - Must use web console or different API tier

4. **Quarantine Items** ❌
   - NOT available with current API access

5. **Policies** ❌
   - NOT available with current API access

---

## Network Endpoints

Base endpoint: `/api/v1.0/jsonrpc/network`

### 1. Get Companies List

Get all companies/clients in your BitDefender account.

**Method:** `getCompaniesList`

**Request:**
```json
{
    "jsonrpc": "2.0",
    "method": "getCompaniesList",
    "params": {},
    "id": "1"
}
```

**Response:**
```json
{
    "id": "1",
    "jsonrpc": "2.0",
    "result": [
        {
            "id": "66d60e69294bd6bae808050c",
            "name": "Adelaide Hills Mazda"
        },
        {
            "id": "66d614510ac6852cde0aa6d9",
            "name": "Adelaide Hills Motor Group"
        }
    ]
}
```

**Use Case:** Get list of all clients for syncing customer data.

---

### 2. Get Endpoints List (All Companies)

Get all managed endpoints/devices across all companies.

**Method:** `getEndpointsList`

**Request:**
```json
{
    "jsonrpc": "2.0",
    "method": "getEndpointsList",
    "params": {
        "page": 1,
        "perPage": 100
    },
    "id": "2"
}
```

**Response:**
```json
{
    "id": "2",
    "jsonrpc": "2.0",
    "result": {
        "total": 2,
        "page": 1,
        "perPage": 100,
        "pagesCount": 1,
        "items": [
            {
                "id": "67e0a71d74d621c4a2f0ca3d",
                "name": "TTS-DRW-ADMIN21",
                "label": "",
                "fqdn": "tts-drw-admin21.techsolutions.com.au",
                "groupId": "65af0d20a85ef6b89903725d",
                "isManaged": true,
                "machineType": 1,
                "operatingSystemVersion": "Windows 11 Pro",
                "ip": "192.168.254.100",
                "macs": ["f875a443cd4b"],
                "ssid": "1-5-21-1494569763-1512994731-355810188-38743",
                "managedWithBest": true,
                "policy": {
                    "id": "675b85b83c20f594880961fb",
                    "name": "All Generic Windows Workstations",
                    "applied": true
                }
            }
        ]
    }
}
```

**Use Case:** Get total device count and device details across all clients.

---

### 3. Get Endpoints List (Specific Company)

Get managed endpoints for a specific company.

**Method:** `getEndpointsList`

**Request:**
```json
{
    "jsonrpc": "2.0",
    "method": "getEndpointsList",
    "params": {
        "parentId": "66d60e69294bd6bae808050c",
        "page": 1,
        "perPage": 100
    },
    "id": "3"
}
```

**Parameters:**
- `parentId` - Company ID from `getCompaniesList`
- `page` - Page number (default: 1)
- `perPage` - Results per page (default: 100, max: 100)

**Response:** Same format as endpoint #2

**Use Case:** Get device count and details for a specific client.

---

### 4. Get Endpoints List (With Filters)

Get endpoints with specific filters applied.

**Method:** `getEndpointsList`

**Request:**
```json
{
    "jsonrpc": "2.0",
    "method": "getEndpointsList",
    "params": {
        "filters": {
            "type": {
                "computers": true,
                "virtualMachines": true
            }
        },
        "page": 1,
        "perPage": 100
    },
    "id": "4"
}
```

**Available Filters:**
- `type` - Filter by machine type
  - `computers` - Physical computers (true/false)
  - `virtualMachines` - Virtual machines (true/false)
- `label` - Filter by custom label (string)
- `security` - Filter by security status

**Note:** The `isManaged` and `operatingSystem` filters are NOT supported in this method. All returned endpoints are managed by default.

**Use Case:** Get specific types of devices (e.g., only physical computers, not VMs).

---

### 5. Get Device Count for Company

Get device counts by querying endpoints.

**Method:** `getEndpointsList`

**Request:**
```json
{
    "jsonrpc": "2.0",
    "method": "getEndpointsList",
    "params": {
        "parentId": "66d60e69294bd6bae808050c",
        "perPage": 1
    },
    "id": "8"
}
```

**Response:**
```json
{
    "id": "8",
    "jsonrpc": "2.0",
    "result": {
        "total": 25,
        "page": 1,
        "perPage": 1,
        "pagesCount": 25,
        "items": [...]
    }
}
```

**Use Case:** Get device count for a company (check `total` field). Set `perPage: 1` to minimize data transfer when you only need the count.

---

## ❌ Non-Working / Unavailable Endpoints

The following endpoints were tested but are **NOT available** with this API key or API version:

### Incidents Endpoints
- ❌ `getIncidentsList` - Method not found
- ❌ `getIncidentDetails` - Method not found

### Statistics Endpoints
- ❌ `getStatistics` - Method not found
- ❌ `getCompaniesStatistics` - Method not found
- ❌ `getCompanyDetails` - Method not found

### Other Endpoints
- ❌ `getNetworkInventoryItems` - Not tested (likely unavailable)
- ❌ `getEndpointDetails` - Not tested (likely unavailable)
- ❌ `getQuarantineItemsList` - Not tested (likely unavailable)
- ❌ `getPoliciesList` - Not tested (likely unavailable)
- ❌ `getReportsList` - Not tested (likely unavailable)

**Note:** These endpoints may be available with:
- Different API access levels
- Enterprise vs MSP product versions
- Different authentication methods
- Web console access only

---

## Common Parameters

### Pagination
- `page` - Page number (starts at 1)
- `perPage` - Results per page (max: 100)

### Date Formats
- ISO 8601 format: `"2024-11-25T05:00:00"`
- UTC timezone recommended

### Company/Parent ID
- Use company ID from `getCompaniesList`
- Omit to get data across all companies

---

## Error Codes

| Code | Message | Description |
|------|---------|-------------|
| -32000 | Server error | General server error |
| -32601 | Method not found | Invalid API method name |
| -32602 | Invalid params | Invalid method parameters |
| -32700 | Parse error | Invalid JSON |

**Common Errors:**

**Authorization Error:**
```json
{
    "error": {
        "code": -32000,
        "message": "Server error",
        "data": {
            "details": "Authorization header not passed, HTTP Basic Authentication should be used."
        }
    }
}
```
**Solution:** Ensure HTTP Basic Auth is configured with API key as username.

**Method Not Found:**
```json
{
    "error": {
        "code": -32601,
        "message": "Method not found"
    }
}
```
**Solution:** Check method name spelling and endpoint URL.

---

## Postman Setup

### 1. Create Environment

| Variable | Value |
|----------|-------|
| `bitdefender_url` | `https://cloud.gravityzone.bitdefender.com/api/v1.0/jsonrpc` |
| `bitdefender_api_key` | `ddff78bfef66982e91beaff8e336664ee272e5a93cb9145e3756cc773ce70cb2` |

### 2. Configure Request

**URL:** `{{bitdefender_url}}/network`

**Authorization:**
- Type: Basic Auth
- Username: `{{bitdefender_api_key}}`
- Password: (empty)

**Headers:**
```
Content-Type: application/json
```

**Body (raw JSON):**
```json
{
    "jsonrpc": "2.0",
    "method": "getCompaniesList",
    "params": {},
    "id": "1"
}
```

### 3. Test Script

Add to Tests tab:
```javascript
pm.test("Status code is 200", function () {
    pm.response.to.have.status(200);
});

pm.test("Response has result", function () {
    var jsonData = pm.response.json();
    pm.expect(jsonData).to.have.property('result');
});

pm.test("No errors", function () {
    var jsonData = pm.response.json();
    pm.expect(jsonData).to.not.have.property('error');
});
```

---

## Integration Implementation Notes

### For Backend Integration

When implementing BitDefender integration in the backend:

1. **Authentication:** Use HTTP Basic Auth with API key as username, empty password
2. **Base URL:** `https://cloud.gravityzone.bitdefender.com/api/v1.0/jsonrpc/network`
3. **Available Endpoints:**
   - ✅ `/network` - For companies and endpoints only
   - ❌ `/incidents` - NOT available
   - ❌ `/quarantine` - NOT available

4. **Customer Mapping:**
   - Use `getCompaniesList` to sync customers
   - Map company `id` to `IntegrationCustomer.external_id`
   - Map company `name` to `IntegrationCustomer.customer_name`

5. **Data Types (Available):**
   - `bitdefender_company` - Company/client data
   - `bitdefender_endpoint` - Device/endpoint data

6. **Data Types (NOT Available):**
   - ❌ `bitdefender_incident` - Security incidents
   - ❌ `bitdefender_quarantine` - Quarantined items
   - ❌ `bitdefender_policy` - Security policies

7. **Sync Strategy:**
   - Pull companies first using `getCompaniesList`
   - Pull endpoints per company using `getEndpointsList` with `parentId`
   - Store company data and endpoint counts
   - Store in `IntegrationData` table

8. **Limitations:**
   - Cannot retrieve security incidents via API
   - Cannot retrieve quarantine data via API
   - Cannot retrieve policy details via API
   - Limited to company and device information only

---

## Testing Checklist

### ✅ Completed Tests
- [x] Test authentication (HTTP Basic Auth working)
- [x] Get companies list (working - returns all clients)
- [x] Get all endpoints (working - returns device count)
- [x] Get endpoints for specific company (working with `parentId`)
- [x] Get endpoints with filters (working with `type` filter)
- [x] Test error handling (confirmed invalid methods return -32601)
- [x] Verify response data structure

### ❌ Failed Tests
- [x] Get incidents - Method not found
- [x] Get company statistics - Method not found
- [x] Get company details - Method not found

### ⏸️ Not Tested
- [ ] Get quarantine items (likely unavailable)
- [ ] Get policies (likely unavailable)
- [ ] Test pagination with > 100 results
- [ ] Get endpoint details (likely unavailable)
- [ ] Get network inventory (likely unavailable)

---

## Rate Limits

BitDefender GravityZone API rate limits:
- **Requests per minute:** 60
- **Requests per hour:** 1000
- **Burst allowance:** 10
- **Recommended:** Implement exponential backoff on rate limit errors

---

## Support & Documentation

**Official Documentation:**
- https://www.bitdefender.com/business/support/en/77209-128843-gravityzone-api.html

**API Version:** v1.0

**Last Updated:** November 25, 2024
