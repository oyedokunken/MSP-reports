# IT Glue API - Postman Testing Guide

## TTS Use Case

**Purpose:** Pull Microsoft license consumption data for customer reporting

**Key Workflow:**
1. Fetch organizations (customer list)
2. Get flexible asset types (find Microsoft license assets)
3. Pull flexible assets filtered by Microsoft license type
4. Extract license consumption from asset traits:
   - Total seats purchased
   - Seats assigned/used
   - License expiry/renewal dates
   - License types (Microsoft 365, Office, Azure, etc.)
5. Generate monthly license utilization reports per customer

**Additional Monitoring:**
- SSL certificate expiry alerts
- Domain registration expiry alerts
- Other flexible asset expiry tracking

---

## Production Credentials

**Base URL:** `https://api.au.itglue.com`  
**API Key:** `ITG.8545f2b437bd94259e7b47f65e508090.O_6C4JoG-xR3-8qqCk1k19jdXs5SQ-dd_rbb7XmDPZ58doJXcRynx59Qpjpis8TK`  
**Source ID:** `10`  
**Source Name:** `IT Glue`  
**Type:** `pull`  
**Driver:** `itglue`  
**Status:** `active`  
**Region:** `Australia`

---

## API Endpoints

### 1. Get All Organizations
**Endpoint:** `GET /organizations`  
**URL:** `https://api.au.itglue.com/organizations?page[size]=50&page[number]=1`

**Headers:**
```
x-api-key: ITG.8545f2b437bd94259e7b47f65e508090.O_6C4JoG-xR3-8qqCk1k19jdXs5SQ-dd_rbb7XmDPZ58doJXcRynx59Qpjpis8TK
Content-Type: application/vnd.api+json
```

**Query Parameters:**
- `page[size]` - Number of results per page (default: 50)
- `page[number]` - Page number for pagination

**Response Structure:**
```json
{
  "data": [
    {
      "id": "12345",
      "type": "organizations",
      "attributes": {
        "name": "Example Company",
        "short-name": "Example",
        "organization-type-id": 1,
        "organization-type-name": "Client",
        "organization-status-id": 1,
        "organization-status-name": "Active",
        "parent-id": null,
        "ancestor-ids": [],
        "primary": true,
        "created-at": "2024-01-01T00:00:00.000Z",
        "updated-at": "2024-12-01T00:00:00.000Z"
      }
    }
  ],
  "meta": {
    "total-count": 150,
    "total-pages": 3,
    "current-page": 1,
    "next-page": 2
  }
}
```

**cURL:**
```bash
curl -X GET "https://api.au.itglue.com/organizations?page[size]=50&page[number]=1" \
  -H "x-api-key: ITG.8545f2b437bd94259e7b47f65e508090.O_6C4JoG-xR3-8qqCk1k19jdXs5SQ-dd_rbb7XmDPZ58doJXcRynx59Qpjpis8TK" \
  -H "Content-Type: application/vnd.api+json"
```

---

### 2. Get Single Organization
**Endpoint:** `GET /organizations/{id}`  
**URL:** `https://api.au.itglue.com/organizations/{id}`

**Headers:**
```
x-api-key: ITG.8545f2b437bd94259e7b47f65e508090.O_6C4JoG-xR3-8qqCk1k19jdXs5SQ-dd_rbb7XmDPZ58doJXcRynx59Qpjpis8TK
Content-Type: application/vnd.api+json
```

**Response Structure:**
```json
{
  "data": {
    "id": "12345",
    "type": "organizations",
    "attributes": {
      "name": "Example Company",
      "short-name": "Example",
      "organization-type-name": "Client",
      "organization-status-name": "Active",
      "primary": true
    }
  }
}
```

**cURL:**
```bash
curl -X GET "https://api.au.itglue.com/organizations/12345" \
  -H "x-api-key: ITG.8545f2b437bd94259e7b47f65e508090.O_6C4JoG-xR3-8qqCk1k19jdXs5SQ-dd_rbb7XmDPZ58doJXcRynx59Qpjpis8TK" \
  -H "Content-Type: application/vnd.api+json"
```

---

### 3. Get Expiring SSL Certificates
**Endpoint:** `GET /ssl_certificates`  
**URL:** `https://api.au.itglue.com/ssl_certificates?filter[expiration]=next90days&page[size]=50`

**Headers:**
```
x-api-key: ITG.8545f2b437bd94259e7b47f65e508090.O_6C4JoG-xR3-8qqCk1k19jdXs5SQ-dd_rbb7XmDPZ58doJXcRynx59Qpjpis8TK
Content-Type: application/vnd.api+json
```

**Query Parameters:**
- `filter[expiration]` - Filter by expiration period (`next90days`, `next30days`, `next14days`)
- `page[size]` - Number of results per page
- `page[number]` - Page number

**Response Structure:**
```json
{
  "data": [
    {
      "id": "789",
      "type": "ssl-certificates",
      "attributes": {
        "name": "*.example.com",
        "expires-on": "2025-03-15",
        "organization-id": 12345
      }
    }
  ],
  "meta": {
    "total-count": 5
  }
}
```

**Alert Thresholds:**
- **Critical:** Expires in ≤ 14 days
- **Warning:** Expires in ≤ 30 days
- **Info:** Expires in ≤ 90 days

**cURL:**
```bash
curl -X GET "https://api.au.itglue.com/ssl_certificates?filter[expiration]=next90days&page[size]=50" \
  -H "x-api-key: ITG.8545f2b437bd94259e7b47f65e508090.O_6C4JoG-xR3-8qqCk1k19jdXs5SQ-dd_rbb7XmDPZ58doJXcRynx59Qpjpis8TK" \
  -H "Content-Type: application/vnd.api+json"
```

---

### 4. Get Expiring Domains
**Endpoint:** `GET /domains`  
**URL:** `https://api.au.itglue.com/domains?filter[expiration]=next90days&page[size]=50`

**Headers:**
```
x-api-key: ITG.8545f2b437bd94259e7b47f65e508090.O_6C4JoG-xR3-8qqCk1k19jdXs5SQ-dd_rbb7XmDPZ58doJXcRynx59Qpjpis8TK
Content-Type: application/vnd.api+json
```

**Query Parameters:**
- `filter[expiration]` - Filter by expiration period
- `page[size]` - Number of results per page
- `page[number]` - Page number

**Response Structure:**
```json
{
  "data": [
    {
      "id": "456",
      "type": "domains",
      "attributes": {
        "name": "example.com",
        "expires-on": "2025-06-20",
        "organization-id": 12345
      }
    }
  ],
  "meta": {
    "total-count": 3
  }
}
```

**Alert Thresholds:**
- **Critical:** Expires in ≤ 14 days
- **Warning:** Expires in ≤ 30 days
- **Info:** Expires in ≤ 90 days

**cURL:**
```bash
curl -X GET "https://api.au.itglue.com/domains?filter[expiration]=next90days&page[size]=50" \
  -H "x-api-key: ITG.8545f2b437bd94259e7b47f65e508090.O_6C4JoG-xR3-8qqCk1k19jdXs5SQ-dd_rbb7XmDPZ58doJXcRynx59Qpjpis8TK" \
  -H "Content-Type: application/vnd.api+json"
```

---

### 5. Get Flexible Asset Types
**Endpoint:** `GET /flexible_asset_types`  
**URL:** `https://api.au.itglue.com/flexible_asset_types?page[size]=50`

**Headers:**
```
x-api-key: ITG.8545f2b437bd94259e7b47f65e508090.O_6C4JoG-xR3-8qqCk1k19jdXs5SQ-dd_rbb7XmDPZ58doJXcRynx59Qpjpis8TK
Content-Type: application/vnd.api+json
```

**Query Parameters:**
- `page[size]` - Number of results per page
- `page[number]` - Page number

**Response Structure:**
```json
{
  "data": [
    {
      "id": "100",
      "type": "flexible-asset-types",
      "attributes": {
        "name": "Software Licenses",
        "description": "Track software licenses and renewals"
      }
    }
  ],
  "meta": {
    "total-count": 12
  }
}
```

**cURL:**
```bash
curl -X GET "https://api.au.itglue.com/flexible_asset_types?page[size]=50" \
  -H "x-api-key: ITG.8545f2b437bd94259e7b47f65e508090.O_6C4JoG-xR3-8qqCk1k19jdXs5SQ-dd_rbb7XmDPZ58doJXcRynx59Qpjpis8TK" \
  -H "Content-Type: application/vnd.api+json"
```

---

### 6. Get Flexible Assets
**Endpoint:** `GET /flexible_assets`  
**URL:** `https://api.au.itglue.com/flexible_assets?filter[flexible_asset_type_id]={type_id}&include=organization&page[size]=1000`

**Headers:**
```
x-api-key: ITG.8545f2b437bd94259e7b47f65e508090.O_6C4JoG-xR3-8qqCk1k19jdXs5SQ-dd_rbb7XmDPZ58doJXcRynx59Qpjpis8TK
Content-Type: application/vnd.api+json
```

**Query Parameters:**
- `filter[flexible_asset_type_id]` - Filter by asset type ID
- `include` - Include related resources (e.g., `organization`)
- `page[size]` - Number of results per page (max: 1000)
- `page[number]` - Page number

**Response Structure:**
```json
{
  "data": [
    {
      "id": "5000",
      "type": "flexible-assets",
      "attributes": {
        "name": "Microsoft 365 License",
        "organization-id": 12345,
        "organization-name": "Example Company",
        "traits": {
          "license-key": "XXXXX-XXXXX-XXXXX",
          "renewal-date": "2025-12-31",
          "seats": 50
        },
        "updated-at": "2024-12-01T00:00:00.000Z"
      }
    }
  ],
  "meta": {
    "total-count": 25
  }
}
```

**Expiry Detection:**
The system automatically scans flexible asset traits for expiry-related fields:
- Fields containing: `expiry`, `expiration`, or `renewal`
- Excludes status values like: `processing`, `pending`, `active`, `inactive`, `expired`
- Excludes license-type assets from expiry alerts

**Alert Thresholds (Non-License Assets):**
- **Critical:** Expires in ≤ 7 days
- **High:** Expires in ≤ 14 days
- **Warning:** Expires in ≤ 30 days

**cURL:**
```bash
curl -X GET "https://api.au.itglue.com/flexible_assets?filter[flexible_asset_type_id]=100&include=organization&page[size]=1000" \
  -H "x-api-key: ITG.8545f2b437bd94259e7b47f65e508090.O_6C4JoG-xR3-8qqCk1k19jdXs5SQ-dd_rbb7XmDPZ58doJXcRynx59Qpjpis8TK" \
  -H "Content-Type: application/vnd.api+json"
```

---

## Postman Collection

### Import This JSON

```json
{
  "info": {
    "name": "IT Glue API - Australia",
    "description": "API testing for IT Glue AU instance",
    "schema": "https://schema.getpostman.com/json/collection/v2.1.0/collection.json"
  },
  "variable": [
    {
      "key": "base_url",
      "value": "https://api.au.itglue.com",
      "type": "string"
    },
    {
      "key": "api_key",
      "value": "ITG.8545f2b437bd94259e7b47f65e508090.O_6C4JoG-xR3-8qqCk1k19jdXs5SQ-dd_rbb7XmDPZ58doJXcRynx59Qpjpis8TK",
      "type": "string"
    }
  ],
  "item": [
    {
      "name": "1. Get All Organizations",
      "event": [
        {
          "listen": "test",
          "script": {
            "exec": [
              "pm.test(\"Status code is 200\", function () {",
              "    pm.response.to.have.status(200);",
              "});",
              "",
              "pm.test(\"Response has data array\", function () {",
              "    var jsonData = pm.response.json();",
              "    pm.expect(jsonData.data).to.be.an('array');",
              "});",
              "",
              "pm.test(\"Response has meta object\", function () {",
              "    var jsonData = pm.response.json();",
              "    pm.expect(jsonData.meta).to.be.an('object');",
              "});",
              "",
              "if (pm.response.json().data && pm.response.json().data.length > 0) {",
              "    pm.environment.set(\"org_id\", pm.response.json().data[0].id);",
              "    console.log(\"Saved org_id: \" + pm.response.json().data[0].id);",
              "}"
            ]
          }
        }
      ],
      "request": {
        "method": "GET",
        "header": [
          {
            "key": "x-api-key",
            "value": "{{api_key}}",
            "type": "text"
          },
          {
            "key": "Content-Type",
            "value": "application/vnd.api+json",
            "type": "text"
          }
        ],
        "url": {
          "raw": "{{base_url}}/organizations?page[size]=50&page[number]=1",
          "host": ["{{base_url}}"],
          "path": ["organizations"],
          "query": [
            {
              "key": "page[size]",
              "value": "50"
            },
            {
              "key": "page[number]",
              "value": "1"
            }
          ]
        }
      }
    },
    {
      "name": "2. Get Single Organization",
      "event": [
        {
          "listen": "test",
          "script": {
            "exec": [
              "pm.test(\"Status code is 200\", function () {",
              "    pm.response.to.have.status(200);",
              "});",
              "",
              "pm.test(\"Response has data object\", function () {",
              "    var jsonData = pm.response.json();",
              "    pm.expect(jsonData.data).to.be.an('object');",
              "});",
              "",
              "pm.test(\"Organization has required fields\", function () {",
              "    var jsonData = pm.response.json();",
              "    pm.expect(jsonData.data).to.have.property('id');",
              "    pm.expect(jsonData.data).to.have.property('type');",
              "    pm.expect(jsonData.data).to.have.property('attributes');",
              "});"
            ]
          }
        }
      ],
      "request": {
        "method": "GET",
        "header": [
          {
            "key": "x-api-key",
            "value": "{{api_key}}",
            "type": "text"
          },
          {
            "key": "Content-Type",
            "value": "application/vnd.api+json",
            "type": "text"
          }
        ],
        "url": {
          "raw": "{{base_url}}/organizations/{{org_id}}",
          "host": ["{{base_url}}"],
          "path": ["organizations", "{{org_id}}"]
        }
      }
    },
    {
      "name": "3. Get Expiring SSL Certificates",
      "event": [
        {
          "listen": "test",
          "script": {
            "exec": [
              "pm.test(\"Status code is 200\", function () {",
              "    pm.response.to.have.status(200);",
              "});",
              "",
              "pm.test(\"Response has data array\", function () {",
              "    var jsonData = pm.response.json();",
              "    pm.expect(jsonData.data).to.be.an('array');",
              "});",
              "",
              "pm.test(\"SSL certificates have expiration dates\", function () {",
              "    var jsonData = pm.response.json();",
              "    if (jsonData.data.length > 0) {",
              "        pm.expect(jsonData.data[0].attributes).to.have.property('expires-on');",
              "    }",
              "});"
            ]
          }
        }
      ],
      "request": {
        "method": "GET",
        "header": [
          {
            "key": "x-api-key",
            "value": "{{api_key}}",
            "type": "text"
          },
          {
            "key": "Content-Type",
            "value": "application/vnd.api+json",
            "type": "text"
          }
        ],
        "url": {
          "raw": "{{base_url}}/ssl_certificates?filter[expiration]=next90days&page[size]=50",
          "host": ["{{base_url}}"],
          "path": ["ssl_certificates"],
          "query": [
            {
              "key": "filter[expiration]",
              "value": "next90days"
            },
            {
              "key": "page[size]",
              "value": "50"
            }
          ]
        }
      }
    },
    {
      "name": "4. Get Expiring Domains",
      "event": [
        {
          "listen": "test",
          "script": {
            "exec": [
              "pm.test(\"Status code is 200\", function () {",
              "    pm.response.to.have.status(200);",
              "});",
              "",
              "pm.test(\"Response has data array\", function () {",
              "    var jsonData = pm.response.json();",
              "    pm.expect(jsonData.data).to.be.an('array');",
              "});",
              "",
              "pm.test(\"Domains have expiration dates\", function () {",
              "    var jsonData = pm.response.json();",
              "    if (jsonData.data.length > 0) {",
              "        pm.expect(jsonData.data[0].attributes).to.have.property('expires-on');",
              "    }",
              "});"
            ]
          }
        }
      ],
      "request": {
        "method": "GET",
        "header": [
          {
            "key": "x-api-key",
            "value": "{{api_key}}",
            "type": "text"
          },
          {
            "key": "Content-Type",
            "value": "application/vnd.api+json",
            "type": "text"
          }
        ],
        "url": {
          "raw": "{{base_url}}/domains?filter[expiration]=next90days&page[size]=50",
          "host": ["{{base_url}}"],
          "path": ["domains"],
          "query": [
            {
              "key": "filter[expiration]",
              "value": "next90days"
            },
            {
              "key": "page[size]",
              "value": "50"
            }
          ]
        }
      }
    },
    {
      "name": "5. Get Flexible Asset Types",
      "event": [
        {
          "listen": "test",
          "script": {
            "exec": [
              "pm.test(\"Status code is 200\", function () {",
              "    pm.response.to.have.status(200);",
              "});",
              "",
              "pm.test(\"Response has data array\", function () {",
              "    var jsonData = pm.response.json();",
              "    pm.expect(jsonData.data).to.be.an('array');",
              "});",
              "",
              "if (pm.response.json().data && pm.response.json().data.length > 0) {",
              "    pm.environment.set(\"asset_type_id\", pm.response.json().data[0].id);",
              "    console.log(\"Saved asset_type_id: \" + pm.response.json().data[0].id);",
              "}"
            ]
          }
        }
      ],
      "request": {
        "method": "GET",
        "header": [
          {
            "key": "x-api-key",
            "value": "{{api_key}}",
            "type": "text"
          },
          {
            "key": "Content-Type",
            "value": "application/vnd.api+json",
            "type": "text"
          }
        ],
        "url": {
          "raw": "{{base_url}}/flexible_asset_types?page[size]=50",
          "host": ["{{base_url}}"],
          "path": ["flexible_asset_types"],
          "query": [
            {
              "key": "page[size]",
              "value": "50"
            }
          ]
        }
      }
    },
    {
      "name": "6. Get Flexible Assets",
      "event": [
        {
          "listen": "test",
          "script": {
            "exec": [
              "pm.test(\"Status code is 200\", function () {",
              "    pm.response.to.have.status(200);",
              "});",
              "",
              "pm.test(\"Response has data array\", function () {",
              "    var jsonData = pm.response.json();",
              "    pm.expect(jsonData.data).to.be.an('array');",
              "});",
              "",
              "pm.test(\"Assets have traits\", function () {",
              "    var jsonData = pm.response.json();",
              "    if (jsonData.data.length > 0) {",
              "        pm.expect(jsonData.data[0].attributes).to.have.property('traits');",
              "    }",
              "});"
            ]
          }
        }
      ],
      "request": {
        "method": "GET",
        "header": [
          {
            "key": "x-api-key",
            "value": "{{api_key}}",
            "type": "text"
          },
          {
            "key": "Content-Type",
            "value": "application/vnd.api+json",
            "type": "text"
          }
        ],
        "url": {
          "raw": "{{base_url}}/flexible_assets?filter[flexible_asset_type_id]={{asset_type_id}}&include=organization&page[size]=1000",
          "host": ["{{base_url}}"],
          "path": ["flexible_assets"],
          "query": [
            {
              "key": "filter[flexible_asset_type_id]",
              "value": "{{asset_type_id}}"
            },
            {
              "key": "include",
              "value": "organization"
            },
            {
              "key": "page[size]",
              "value": "1000"
            }
          ]
        }
      }
    }
  ]
}
```

---

## Testing Workflow

### Step 1: Import to Postman
1. Copy the JSON collection above
2. Open Postman → **Import** → **Raw Text**
3. Paste and click **Import**

### Step 2: Run Tests in Order
1. **Get All Organizations** - Saves `org_id`
2. **Get Single Organization** - Uses saved `org_id`
3. **Get Expiring SSL Certificates** - Check SSL expiry
4. **Get Expiring Domains** - Check domain expiry
5. **Get Flexible Asset Types** - Saves `asset_type_id`
6. **Get Flexible Assets** - Uses saved `asset_type_id`

### Step 3: View Results
Each test automatically validates:
- ✅ Status code is 200
- ✅ Response has correct structure
- ✅ Required fields exist
- ✅ Data types are correct

---

## Quick Test Commands

### Test Connection
```bash
curl -X GET "https://api.au.itglue.com/organizations?page[size]=1" \
  -H "x-api-key: ITG.8545f2b437bd94259e7b47f65e508090.O_6C4JoG-xR3-8qqCk1k19jdXs5SQ-dd_rbb7XmDPZ58doJXcRynx59Qpjpis8TK" \
  -H "Content-Type: application/vnd.api+json"
```

### Get Organizations
```bash
curl -X GET "https://api.au.itglue.com/organizations?page[size]=50&page[number]=1" \
  -H "x-api-key: ITG.8545f2b437bd94259e7b47f65e508090.O_6C4JoG-xR3-8qqCk1k19jdXs5SQ-dd_rbb7XmDPZ58doJXcRynx59Qpjpis8TK" \
  -H "Content-Type: application/vnd.api+json"
```

### Get Expiring SSL Certificates
```bash
curl -X GET "https://api.au.itglue.com/ssl_certificates?filter[expiration]=next90days&page[size]=50" \
  -H "x-api-key: ITG.8545f2b437bd94259e7b47f65e508090.O_6C4JoG-xR3-8qqCk1k19jdXs5SQ-dd_rbb7XmDPZ58doJXcRynx59Qpjpis8TK" \
  -H "Content-Type: application/vnd.api+json"
```

---

## Response Codes

| Code | Status | Meaning |
|------|--------|---------|
| 200 | OK | Success |
| 401 | Unauthorized | Invalid API key or wrong region |
| 404 | Not Found | Resource doesn't exist |
| 429 | Too Many Requests | Rate limit exceeded |
| 500 | Server Error | IT Glue error |

---

## Important Notes

### Authentication
- **Header:** `x-api-key` (NOT `Authorization: Bearer`)
- **Content-Type:** `application/vnd.api+json` (required)
- **API Key Format:** Must start with `ITG.`

### Region-Specific URLs
- **Australia:** `https://api.au.itglue.com` ✅ (Your region)
- **United States:** `https://api.itglue.com`
- **Europe:** `https://api.eu.itglue.com`

### Pagination
- IT Glue uses JSON:API pagination
- Default page size: 50
- Max page size varies by endpoint (flexible_assets: 1000)
- Response includes `meta` object with pagination info

### Rate Limiting
- Built-in 100ms delay between paginated requests
- Automatic retry on rate limit errors
- Maximum 10 pages per request (safety limit)

---

## Troubleshooting

### 401 Unauthorized
**Possible causes:**
1. Wrong region URL (using US instead of AU)
2. Invalid API key
3. Wrong header name (should be `x-api-key`)

**Solution:**
- Verify you're using `https://api.au.itglue.com`
- Check API key starts with `ITG.`
- Ensure header is `x-api-key`, not `Authorization`

### No Data Returned
**Possible causes:**
1. No organizations/assets in IT Glue
2. API key lacks permissions
3. Wrong pagination parameters

**Solution:**
- Check IT Glue web interface for data
- Verify API key permissions
- Test with `page[size]=1` first
