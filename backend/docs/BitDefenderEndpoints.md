# BitDefender GravityZone API Endpoints Documentation

## Base URL
```
https://cloud.gravityzone.bitdefender.com/api/v1.0/jsonrpc
```

**Note:** The actual base URL varies by account. Your specific URL can be found in GravityZone Control Center under **My Account > API Keys**.

Common URL patterns:
- `https://cloud.gravityzone.bitdefender.com/api/v1.0/jsonrpc`
- `https://cloudgz.gravityzone.bitdefender.com/api/v1.0/jsonrpc`
- `https://[your-region].gravityzone.bitdefender.com/api/v1.0/jsonrpc`

## Authentication
**Method:** Basic Authentication with API Key

**Credentials (Territory Technology Solutions):**
```
Username: ddff78bfef66982e91beaff8e336664ee272e5a93cb9145e3756cc773ce70cb2
Password: (None - leave empty)
```

**Headers Required for ALL requests:**
```
Content-Type: application/json
Authorization: Basic {base64_encoded_api_key:}
```

**Note:** The API key is base64 encoded with a colon suffix (e.g., `base64_encode('YOUR_API_KEY:')`)

**Example Authorization Header:**
```
Authorization: Basic ZGRmZjc4YmZlZjY2OTgyZTkxYmVhZmY4ZTMzNjY2NGVlMjcyZTVhOTNjYjkxNDVlMzc1NmNjNzczY2U3MGNiMjo=
```

---

## API Structure

BitDefender GravityZone uses **JSON-RPC 2.0** protocol. All requests are POST requests with the following structure:

```json
{
    "jsonrpc": "2.0",
    "method": "methodName",
    "params": {
        "param1": "value1"
    },
    "id": "unique-request-id"
}
```

### API Categories

The API is organized into category endpoints:
- `/network` - Network, companies, and endpoints
- `/packages` - Software packages and updates
- `/policies` - Security policies
- `/reports` - Report generation
- `/quarantine` - Quarantine management

---

## 1. Get Companies List (GET)

**Endpoint:** `POST {baseUrl}/network`

**Method:** `getCompaniesList`

**Purpose:** Retrieve all managed companies (customers/organizations)

**Request Example:**
```json
{
    "jsonrpc": "2.0",
    "method": "getCompaniesList",
    "params": {},
    "id": "unique-id-123"
}
```

**Full URL Example:**
```
https://cloud.gravityzone.bitdefender.com/api/v1.0/jsonrpc/network
```

**Real Response Example (Territory Technology Solutions):**
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
        },
        {
            "id": "66d60cc1c25fef51190428ad",
            "name": "Adelaide Hills Nissan"
        },
        {
            "id": "66d614311f45caa65f0082aa",
            "name": "Adelaide Hills Suzuki"
        },
        {
            "id": "68c35941743576133c0da900",
            "name": "All Crops Haulage Pty Ltd"
        }
    ]
}
```

**Notes:**
- Real response shows simplified company objects (only `id` and `name`)
- No `type` or `companyDetails` fields in actual response
- Territory Technology manages multiple customer companies

**Key Fields:**
- `id` - Unique company identifier (use for `parentId` in endpoint queries)
- `name` - Company name

**Used in Code:** Line 72 (BitDefenderClient.php)

---

## 2. Get All Endpoints List (GET)

**Endpoint:** `POST {baseUrl}/network`

**Method:** `getEndpointsList`

**Purpose:** Retrieve all endpoints (devices) across all companies

**Request Example (All Endpoints):**
```json
{
    "jsonrpc": "2.0",
    "method": "getEndpointsList",
    "params": {
        "page": 1,
        "perPage": 100
    },
    "id": "1"
}
```

**Parameters:**
- `page` (optional) - Page number (default: 1)
- `perPage` (optional) - Items per page (default: 30, max: 100)
- `parentId` (optional) - Filter by specific company ID
- `filters` (optional) - Filter by device type

**Full URL Example:**
```
https://cloud.gravityzone.bitdefender.com/api/v1.0/jsonrpc/network
```

**Real Response Example (Territory Technology Solutions):**
```json
{
    "id": "1",
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
                "macs": [
                    "f875a443cd4b"
                ],
                "ssid": "1-5-21-1494569763-1512994731-355810188-38743",
                "managedWithBest": true,
                "policy": {
                    "id": "675b85b83c20f594880961fb",
                    "name": "All Generic Windows Workstations",
                    "applied": true
                }
            },
            {
                "id": "67ecad7eb3a9ea8f9653deb7",
                "name": "TTS-DRW-MSOC96",
                "label": "",
                "fqdn": "tts-drw-msoc96.techsolutions.com.au",
                "groupId": "65af0d20a85ef6b89903725d",
                "isManaged": true,
                "machineType": 1,
                "operatingSystemVersion": "Windows 11 Pro",
                "ip": "192.168.242.50",
                "macs": [
                    "c4efbb8cf5ba"
                ],
                "ssid": "1-5-21-1494569763-1512994731-355810188-37269",
                "managedWithBest": true,
                "policy": {
                    "id": "675b85b83c20f594880961fb",
                    "name": "All Generic Windows Workstations",
                    "applied": false
                }
            }
        ]
    }
}
```

**Key Fields:**
- `id` - Unique endpoint identifier
- `name` - Computer/device name
- `fqdn` - Fully qualified domain name
- `machineType` - Device type (1 = computer, 2 = virtual machine, 3 = EC2 instance)
- `operatingSystemVersion` - OS version
- `ip` - IP address
- `macs` - MAC addresses
- `ssid` - Security identifier
- `policy` - Applied security policy
- `movingInfo` - (Optional) Device migration status

**Pagination:**
- `total` - Total number of endpoints
- `pagesCount` - Total number of pages
- Use `page` parameter to iterate through all pages

**Used in Code:** Line 110 (BitDefenderClient.php)

---

## 3. Get Endpoints for Specific Company (GET)

**Endpoint:** `POST {baseUrl}/network`

**Method:** `getEndpointsList`

**Purpose:** Retrieve endpoints (devices) for a specific company using `parentId`

**Request Example:**
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

**Full URL Example:**
```
https://cloud.gravityzone.bitdefender.com/api/v1.0/jsonrpc/network
```

**Real Response Example (Adelaide Hills Mazda):**
```json
{
    "id": "3",
    "jsonrpc": "2.0",
    "result": {
        "total": 1,
        "page": 1,
        "perPage": 100,
        "pagesCount": 1,
        "items": [
            {
                "id": "688976ce6fafc62d1d92955c",
                "name": "AHMG-ADL-LT12",
                "label": "",
                "fqdn": "ahmg-adl-lt12.kerrysholden.local",
                "groupId": "66d60e6a294bd6bae8080511",
                "isManaged": true,
                "machineType": 1,
                "operatingSystemVersion": "Windows 11 Pro",
                "ip": "172.16.7.66",
                "macs": [
                    "bccd999efc9b"
                ],
                "ssid": "1-5-21-3315943025-3798111552-361811919-9142",
                "managedWithBest": true,
                "movingInfo": {
                    "state": 1,
                    "destinationCompanyName": "Adelaide Hills Nissan"
                },
                "policy": {
                    "id": "675b85b83c20f594880961fb",
                    "name": "All Generic Windows Workstations",
                    "applied": false
                }
            }
        ]
    }
}
```

**Parameters:**
- `parentId` (required) - Company ID from `getCompaniesList`
- `page` (optional) - Page number (default: 1)
- `perPage` (optional) - Items per page (default: 30, max: 100)

**Notes:**
- Use `parentId` to filter endpoints by company
- `movingInfo` appears when device is being migrated between companies
- This is the method used in the integration code (Line 110)

---

## 4. Get Endpoints with Filters (GET)

**Endpoint:** `POST {baseUrl}/network`

**Method:** `getEndpointsList`

**Purpose:** Retrieve endpoints filtered by device type

**Request Example:**
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

**Full URL Example:**
```
https://cloud.gravityzone.bitdefender.com/api/v1.0/jsonrpc/network
```

**Real Response Example:**
```json
{
    "id": "4",
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
                "macs": [
                    "f875a443cd4b"
                ],
                "ssid": "1-5-21-1494569763-1512994731-355810188-38743",
                "managedWithBest": true,
                "policy": {
                    "id": "675b85b83c20f594880961fb",
                    "name": "All Generic Windows Workstations",
                    "applied": true
                }
            },
            {
                "id": "67ecad7eb3a9ea8f9653deb7",
                "name": "TTS-DRW-MSOC96",
                "label": "",
                "fqdn": "tts-drw-msoc96.techsolutions.com.au",
                "groupId": "65af0d20a85ef6b89903725d",
                "isManaged": true,
                "machineType": 1,
                "operatingSystemVersion": "Windows 11 Pro",
                "ip": "192.168.242.50",
                "macs": [
                    "c4efbb8cf5ba"
                ],
                "ssid": "1-5-21-1494569763-1512994731-355810188-37269",
                "managedWithBest": true,
                "policy": {
                    "id": "675b85b83c20f594880961fb",
                    "name": "All Generic Windows Workstations",
                    "applied": false
                }
            }
        ]
    }
}
```

**Filter Options:**
- `filters.type.computers` - Include physical computers
- `filters.type.virtualMachines` - Include virtual machines
- Can combine multiple filters

---

## 5. Get Network Inventory Items (GET)

**Endpoint:** `POST {baseUrl}/network`

**Method:** `getNetworkInventoryItems`

**Purpose:** Retrieve network inventory items (groups, folders) for a company

**Request Example:**
```json
{
    "jsonrpc": "2.0",
    "method": "getNetworkInventoryItems",
    "params": {
        "parentId": "66d60e69294bd6bae808050c",
        "page": 1,
        "perPage": 100
    },
    "id": "5"
}
```

**Full URL Example:**
```
https://cloud.gravityzone.bitdefender.com/api/v1.0/jsonrpc/network
```

**Real Response Example:**
```json
{
    "id": "5",
    "jsonrpc": "2.0",
    "result": {
        "total": 2,
        "page": 1,
        "perPage": 100,
        "pagesCount": 1,
        "items": [
            {
                "id": "66d60e6a294bd6bae8080511",
                "name": "Custom Groups",
                "type": 4,
                "parentId": "66d60e69294bd6bae808050c",
                "companyId": "66d60e69294bd6bae808050c"
            },
            {
                "id": "66d60e6a294bd6bae8080512",
                "name": "Deleted",
                "type": 4,
                "parentId": "66d60e69294bd6bae808050c",
                "companyId": "66d60e69294bd6bae808050c"
            }
        ]
    }
}
```

**Key Fields:**
- `id` - Inventory item identifier
- `name` - Item name (e.g., "Custom Groups", "Deleted")
- `type` - Item type (4 = group/folder)
- `parentId` - Parent company/group ID
- `companyId` - Owning company ID

**Notes:**
- Returns organizational structure within a company
- Useful for understanding endpoint grouping

---

## 6. Get Incidents List (DEPRECATED)

**Endpoint:** `POST {baseUrl}/incidents`

**Method:** `getIncidentsList`

**Purpose:** Retrieve security incidents (NOT AVAILABLE)

**Request Example:**
```json
{
    "jsonrpc": "2.0",
    "method": "getIncidentsList",
    "params": {
        "parentId": "66d60e69294bd6bae808050c",
        "perPage": 500,
        "page": 1
    },
    "id": "9"
}
```

**Full URL Example:**
```
https://cloud.gravityzone.bitdefender.com/api/v1.0/jsonrpc/incidents
```

**Real Response Example:**
```json
{
    "id": "9",
    "jsonrpc": "2.0",
    "error": {
        "code": -32601,
        "message": "Method not found",
        "data": {
            "details": "Method not found"
        }
    }
}
```

**Status:** ❌ **NOT AVAILABLE**

**Notes:**
- This endpoint/method does not exist in the current API
- Error code `-32601` indicates "Method not found"
- Use quarantine endpoints instead for threat data

---

## 7. Get Quarantine Items List (GET)

**Endpoint:** `POST {baseUrl}/quarantine`

**Method:** `getQuarantineItemsList`

**Purpose:** Retrieve quarantined threats and malware detections

**Request Example:**
```json
{
    "jsonrpc": "2.0",
    "method": "getQuarantineItemsList",
    "params": {
        "page": 1,
        "perPage": 100
    },
    "id": "unique-id-789"
}
```

**Parameters:**
- `page` (optional) - Page number (default: 1)
- `perPage` (optional) - Items per page (default: 30, max: 100)

**Response Format:**
```json
{
    "jsonrpc": "2.0",
    "id": "unique-id-789",
    "result": {
        "page": 1,
        "perPage": 100,
        "total": 15,
        "pagesCount": 1,
        "items": [
            {
                "id": "quarantine-item-id-1",
                "endpointId": "endpoint-id-1",
                "endpointName": "DESKTOP-ABC123",
                "companyId": "company-id-1",
                "threatName": "Trojan.Generic.12345",
                "threatType": "malware",
                "filePath": "C:\\Users\\John\\Downloads\\suspicious.exe",
                "detectionTime": "2025-12-05T14:23:45Z",
                "actionTaken": "quarantined",
                "status": "quarantined"
            },
            {
                "id": "quarantine-item-id-2",
                "endpointId": "endpoint-id-2",
                "endpointName": "LAPTOP-XYZ789",
                "companyId": "company-id-1",
                "threatName": "Gen:Variant.Ransom.123",
                "threatType": "ransomware",
                "filePath": "C:\\Temp\\malicious.dll",
                "detectionTime": "2025-12-04T09:15:22Z",
                "actionTaken": "quarantined",
                "status": "deleted"
            }
        ]
    }
}
```

**Key Fields:**
- `id` - Quarantine item identifier
- `endpointId` - Affected endpoint ID
- `threatName` - Name of detected threat
- `threatType` - Type of threat (malware, ransomware, trojan, etc.)
- `filePath` - Location of infected file
- `detectionTime` - When threat was detected
- `actionTaken` - Action performed (quarantined, deleted, disinfected)
- `status` - Current status

**Notes:**
- Currently not implemented in integration code
- Available for future threat detection features
- Returns actual malware/threat detections

**Used in Code:** Line 156 (BitDefenderClient.php) - Not currently called in sync

---

## Error Handling

### JSON-RPC Error Response
```json
{
    "jsonrpc": "2.0",
    "id": "unique-id-123",
    "error": {
        "code": -32600,
        "message": "Invalid Request",
        "data": "Additional error details"
    }
}
```

### Common Error Codes
- `-32700` - Parse error (Invalid JSON)
- `-32600` - Invalid Request
- `-32601` - Method not found
- `-32602` - Invalid params
- `-32603` - Internal error
- `401` - Authentication failed (invalid API key)
- `403` - Forbidden (insufficient permissions)

---

## Rate Limiting

**BitDefender Rate Limits:**
- **60 requests per minute** (recommended)
- **1000 requests per hour** (recommended)

**Driver Behavior:**
- Automatic rate limiting via RateLimiter service
- 60-second timeout when waiting for rate limit slot
- Requests are queued when limit is reached

**Best Practices:**
- Use pagination (`perPage: 100`) to reduce API calls
- Implement exponential backoff on errors
- Monitor rate limit usage
- Batch operations when possible

---

## Authentication Example

### cURL Example
```bash
# Generate base64 encoded auth header
API_KEY="your-api-key-here"
AUTH_HEADER=$(echo -n "${API_KEY}:" | base64)

curl -X POST "https://cloud.gravityzone.bitdefender.com/api/v1.0/jsonrpc/network" \
  -H "Content-Type: application/json" \
  -H "Authorization: Basic ${AUTH_HEADER}" \
  -d '{
    "jsonrpc": "2.0",
    "method": "getCompaniesList",
    "params": {},
    "id": "test-123"
  }'
```

### PHP Example
```php
$apiKey = 'your-api-key-here';
$baseUrl = 'https://cloud.gravityzone.bitdefender.com/api/v1.0/jsonrpc';

$headers = [
    'Content-Type: application/json',
    'Authorization: Basic ' . base64_encode($apiKey . ':')
];

$payload = [
    'jsonrpc' => '2.0',
    'method' => 'getCompaniesList',
    'params' => [],
    'id' => uniqid()
];

$ch = curl_init($baseUrl . '/network');
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

$response = curl_exec($ch);
curl_close($ch);

$data = json_decode($response, true);
```

---

## Complete Endpoint Summary

| # | Category | Method | Purpose | Pagination | Status | Line |
|---|----------|--------|---------|------------|--------|------|
| 1 | network | `getCompaniesList` | Get all companies | ❌ | ✅ Active | 72 |
| 2 | network | `getEndpointsList` | Get all endpoints | ✅ | ✅ Active | 110 |
| 3 | network | `getEndpointsList` | Get endpoints by company | ✅ | ✅ Active | 110 |
| 4 | network | `getEndpointsList` | Get endpoints with filters | ✅ | ✅ Available | - |
| 5 | network | `getNetworkInventoryItems` | Get inventory groups | ✅ | ✅ Available | - |
| 6 | incidents | `getIncidentsList` | Get incidents | ✅ | ❌ Not Found | - |
| 7 | quarantine | `getQuarantineItemsList` | Get quarantined threats | ✅ | ✅ Available | 156 |

---

## Integration Architecture

### Driver Location
```
/home/kristian/reporting/backend/app/Integrations/BitDefender/
├── BitDefenderClient.php
├── BitDefenderIntegration.php
└── BitDefenderMapper.php
```

### Key Methods

**Authentication:**
- `testConnection()` - Validates API key by fetching companies list

**Data Sync:**
- `getEndpoints()` - Fetches all companies (customers)
- `getEndpointsForCompany()` - Fetches all endpoints for a company
- `getThreatDetections()` - Fetches quarantine items

**Data Processing:**
- `sync()` - Syncs companies and their endpoints
- `getCustomers()` - Returns standardized customer list

### Database Models

**IntegrationCustomer:**
- Stores BitDefender companies
- Links to `integration_id`
- Contains company metadata

**IntegrationData:**
- Stores endpoint data (time-series)
- Links to `integration_customer_id`
- Data types: `bitdefender_endpoint`, `bitdefender_threat_detection`

---

## Data Flow

1. **Get Companies** → `getCompaniesList` (network)
2. **For each company:**
   - Store as IntegrationCustomer
   - Get endpoints → `getEndpointsList` (network)
   - Store each endpoint as IntegrationData
3. **Get Threats** → `getQuarantineItemsList` (quarantine)
4. **Map to Customers** → Auto-match or create from source of truth

---

## Troubleshooting

### Invalid API URL
**Problem:** 401/403 errors or connection failures

**Solution:**
1. Verify your API URL in GravityZone Control Center
2. Check that the URL includes your specific region/account identifier
3. Common formats:
   - `https://cloud.gravityzone.bitdefender.com/api/v1.0/jsonrpc`
   - `https://cloudgz.gravityzone.bitdefender.com/api/v1.0/jsonrpc`

### Authentication Failed
**Problem:** 401 Unauthorized

**Solution:**
1. Verify API key is correct
2. Ensure API key has necessary permissions
3. Check that base64 encoding includes colon suffix: `base64_encode($apiKey . ':')`

### Pagination Issues
**Problem:** Not all endpoints returned

**Solution:**
1. Check `pagesCount` in response
2. Iterate through all pages: `page = 1` to `pagesCount`
3. Use `perPage: 100` for maximum efficiency

### Rate Limiting
**Problem:** Too many requests

**Solution:**
1. Implement request queuing
2. Use exponential backoff
3. Respect 60 req/min limit
4. Monitor rate limit usage

---

## Notes

- **Historical Data:** Not currently implemented (returns empty array)
- **Supports Historical:** `false` - BitDefender API provides current state only
- **Data Types:** Endpoints and threat detections
- **Customer Mapping:** Companies map to IntegrationCustomers
- **Real-time Data:** All data is current state, not historical

---

## Additional Resources

**BitDefender GravityZone API Documentation:**
- Official docs: https://www.bitdefender.com/business/support/en/77209-128843-gravityzone-api.html

**Driver Implementation:**
- File: `/home/kristian/reporting/backend/app/Integrations/BitDefender/BitDefenderClient.php`
- Lines: 1-247

---

**Last Updated:** December 6, 2025  
**API Version:** v1.0  
**Protocol:** JSON-RPC 2.0  
**Driver Status:** Active (Historical data not implemented)
