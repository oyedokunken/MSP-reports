# SaaS Alerts API - Testing Guide

## Overview

This guide provides step-by-step instructions for testing the SaaS Alerts External Partner API using Postman.

---

## API Credentials

- **API Key:** `MjAzZTIwYjktMzRjOC00NmUzLTg4ZmUtYjAwNzk4YzQ1ZmZhOmV1MS1ZNjc1ajFmb0Z4b3JReUFUc3g5VQ==`
- **Partner ID:** `eu1-Y675j1foFxorQyATsx9U`
- **Base URL:** `https://us-central1-the-byway-248217.cloudfunctions.net/reportApi/api/v1`

---

## Authentication

The API uses **API Key authentication** via custom header:

```
Header Name: api_key
Header Value: MjAzZTIwYjktMzRjOC00NmUzLTg4ZmUtYjAwNzk4YzQ1ZmZhOmV1MS1ZNjc1ajFmb0Z4b3JReUFUc3g5VQ==
```

**Important:** The header name is `api_key` (not `Authorization` or `X-API-Key`)

---

## Postman Environment Setup

### Create Environment Variables

1. Open Postman
2. Click **Environments** in the left sidebar
3. Click **Create Environment**
4. Name it: `SaaS Alerts - Production`
5. Add the following variables:

| Variable | Initial Value | Current Value |
|----------|--------------|---------------|
| `base_url` | `https://us-central1-the-byway-248217.cloudfunctions.net/reportApi/api/v1` | `https://us-central1-the-byway-248217.cloudfunctions.net/reportApi/api/v1` |
| `api_key` | `MjAzZTIwYjktMzRjOC00NmUzLTg4ZmUtYjAwNzk4YzQ1ZmZhOmV1MS1ZNjc1ajFmb0Z4b3JReUFUc3g5VQ==` | `MjAzZTIwYjktMzRjOC00NmUzLTg4ZmUtYjAwNzk4YzQ1ZmZhOmV1MS1ZNjc1ajFmb0Z4b3JReUFUc3g5VQ==` |
| `partner_id` | `eu1-Y675j1foFxorQyATsx9U` | `eu1-Y675j1foFxorQyATsx9U` |

6. Click **Save**
7. Select this environment from the dropdown in the top-right corner

---

## API Endpoints (Read-Only)

**Note:** We only use GET endpoints to retrieve data. Customer management is done in the SaaS Alerts platform directly.

### 1. Get All Customers (Companies)

**Endpoint:** `GET /customers`

**Description:** Retrieve a list of all customers/companies for your partner account.

**Request:**
```
Method: GET
URL: {{base_url}}/customers

Headers:
  api_key: {{api_key}}
  Content-Type: application/json
```

**Expected Response:**
```json
[
  {
    "id": "3s6hZNFny1P2AV0P1pzp",
    "name": "Customer Company Name",
    "email": "customer@example.com",
    "partnerId": "eu1-Y675j1foFxorQyATsx9U",
    "status": "active",
    "createTime": "2021-03-29 14:00:40",
    "countries": [
      {
        "code": "US",
        "phone": "1",
        "label": "United States"
      }
    ],
    "products": [
      {
        "id": "IwXYsdhFSj8ct3GOsEFH",
        "name": "Microsoft",
        "type": "MS",
        "licenseDetails": ["E3", "E5"]
      }
    ],
    "ipRanges": []
  }
]
```

---

### 2. Get Specific Customer by ID

**Endpoint:** `GET /customers/{id}`

**Description:** Retrieve details for a specific customer.

**Request:**
```
Method: GET
URL: {{base_url}}/customers/{customerId}

Headers:
  api_key: {{api_key}}
  Content-Type: application/json

Path Parameters:
  id: {customerId from previous request}
```

**Example:**
```
GET {{base_url}}/customers/3s6hZNFny1P2AV0P1pzp
```

---

### 3. Get Partner Profile

**Endpoint:** `GET /reports/partners/profile`

**Description:** Retrieve your partner profile information including name, address, branding, etc.

**Request:**
```
Method: GET
URL: {{base_url}}/reports/partners/profile

Headers:
  api_key: {{api_key}}
  Content-Type: application/json
```

**Expected Response:**
```json
{
  "id": "coThCxxb3wqZ6gnqYL57",
  "name": "Your Partner Name",
  "domain": "yourpartner.com",
  "companyPhone": "1234567890",
  "createTime": "2020-08-20 17:40:30",
  "address": {
    "suite": "Suite 100",
    "route": "Main Street",
    "locality": "City",
    "administrative_area_level_1": "State",
    "postal_code": "12345",
    "country": {
      "code": "US",
      "label": "United States",
      "phone": "1"
    }
  },
  "branding": {
    "display": 1,
    "color": "#1a56db",
    "logo": "https://example.com/logo.png",
    "reportOptions": {
      "hideSaasAlertsCoBranding": false
    }
  }
}
```

---

### 4. Get MSP User Information

**Endpoint:** `GET /reports/msp-user`

**Description:** Retrieve information about the authenticated MSP user.

**Request:**
```
Method: GET
URL: {{base_url}}/reports/msp-user

Headers:
  api_key: {{api_key}}
  Content-Type: application/json
```

**Expected Response:**
```json
{
  "id": "nhjBE6y5rpaVG4C2fOdHyYLcHsM2",
  "name": "User Name",
  "email": "user@partner.com",
  "role": "msp_user",
  "partnerId": "eu1-Y675j1foFxorQyATsx9U",
  "status": "active",
  "isMSPAdmin": true,
  "isPartner": true
}
```

---

### 5. Get Users for a Customer

**Endpoint:** `GET /reports/users`

**Description:** Retrieve all users/accounts for a specific customer.

**Request:**
```
Method: GET
URL: {{base_url}}/reports/users?customerId={customerId}

Headers:
  api_key: {{api_key}}
  Content-Type: application/json

Query Parameters:
  customerId: {customerId from customers endpoint}
```

**Example:**
```
GET {{base_url}}/reports/users?customerId=3s6hZNFny1P2AV0P1pzp
```

**Expected Response:**
```json
[
  {
    "id": "dbmid:AADHKhq-5cUUO6dNDRQ93Rwz054CApzlLPM",
    "name": "John Doe",
    "email": "john.doe@customer.com",
    "username": "john.doe",
    "accountEnabled": true,
    "isLicensed": true,
    "isBillable": true,
    "isServiceAccount": false,
    "time": "2021-03-29 14:06:08",
    "product": {
      "id": "IwXYsdhFSj8ct3GOsEFH",
      "name": "Microsoft",
      "type": "MS"
    },
    "customerConnectionId": "IwXYsdhFSj8ct3GOsEFH",
    "role": "user",
    "lastEvent": {
      "time": "2024-11-25T10:30:00Z",
      "ip": "192.168.1.1",
      "location": {
        "country": "US",
        "city": "New York",
        "region": "NY"
      }
    }
  }
]
```

---

### 6. Get Events (Security Alerts)

**Endpoint:** `GET /reports/events`

**Description:** Retrieve security events/alerts for customers.

**Request:**
```
Method: GET
URL: {{base_url}}/reports/events

Headers:
  api_key: {{api_key}}
  Content-Type: application/json

Query Parameters (all optional):
  customerId: {customerId}
  userEmail: user@example.com
  alertStatus: critical | medium | low
  eventType: login.success | login.failed | etc.
  start: 2024-01-01 (YYYY-MM-DD)
  end: 2024-12-31 (YYYY-MM-DD)
  from: 0 (pagination offset)
  size: 100 (number of records)
  timeSort: asc | desc
  scroll: 5s (for pagination)
```

**Example:**
```
GET {{base_url}}/reports/events?customerId=3s6hZNFny1P2AV0P1pzp&alertStatus=critical&start=2024-11-01&end=2024-11-30&size=50
```

**Expected Response:**
```json
[
  {
    "_id": "788195cbfa4f4f9da1af7083789e5a1f",
    "eventId": "logins_2021-03-24T07:36:46Z",
    "time": "2021-03-24T07:36:46.000+00:00",
    "type": "logins",
    "alertStatus": "critical",
    "jointType": "outside.own.location",
    "jointDesc": "IAM Event - User Location - Outside approved location",
    "jointDescAdditional": "Login from Ukraine",
    "user": {
      "id": "dbid:AADLbYwZxu9hSRr2dK5B5q8IpTKG8MNFf2k",
      "name": "user@example.com"
    },
    "ip": "185.130.54.55",
    "location": {
      "country": "UA",
      "city": "Kyiv",
      "region": "",
      "ll": [50.45, 30.5233],
      "ip_owner": "ISP Name"
    },
    "product": {
      "id": "L28xuebY4PjgIxpyWd4N",
      "name": "Microsoft",
      "type": "MS"
    },
    "customer": {
      "id": "L1FtOLAV6XjXCP8aKzNn",
      "name": "Customer Name"
    },
    "partner": {
      "id": "eu1-Y675j1foFxorQyATsx9U",
      "name": "Partner Name",
      "domain": "partner.com"
    }
  }
]
```

---

### 7. Get Event Count

**Endpoint:** `GET /reports/events/count`

**Description:** Get a count of events matching specific criteria.

**Request:**
```
Method: GET
URL: {{base_url}}/reports/events/count

Headers:
  api_key: {{api_key}}
  Content-Type: application/json

Query Parameters (same as /reports/events):
  customerId: {customerId}
  alertStatus: critical
  start: 2024-11-01
  end: 2024-11-30
```

**Expected Response:**
```json
{
  "total": 539
}
```

---

### 8. Get Billing Details

**Endpoint:** `GET /reports/billing-details`

**Description:** Retrieve billing summary for the partner and all customers.

**Request:**
```
Method: GET
URL: {{base_url}}/reports/billing-details?billingDate=2024-11-01

Headers:
  api_key: {{api_key}}
  Content-Type: application/json

Query Parameters:
  billingDate: 2024-11-01 (YYYY-MM-DD format)
```

**Expected Response:**
```json
{
  "partnerName": "Your Partner Name",
  "billingCycle": "monthly",
  "billingDueDate": "2024-12-01",
  "lastBillAmount": 1250.50,
  "totalOrganizations": 17,
  "totalAccounts": 1000,
  "totalBillableAccounts": 750,
  "organizations": [
    {
      "name": "Customer 1",
      "accounts": 130,
      "billableAccounts": 100,
      "createdAt": "2024-01-15",
      "rbac": true
    }
  ]
}
```

---

## Advanced: Query Events with POST

For complex event queries, use the POST endpoint:

**Endpoint:** `POST /reports/events/query`

**Request:**
```
Method: POST
URL: {{base_url}}/reports/events/query

Headers:
  api_key: {{api_key}}
  Content-Type: application/json

Body (JSON):
{
  "body": {
    "query": {
      "bool": {
        "must": [
          { "match_all": {} }
        ],
        "filter": [
          {
            "term": {
              "customer.id": "3s6hZNFny1P2AV0P1pzp"
            }
          },
          {
            "term": {
              "alertStatus": "critical"
            }
          },
          {
            "range": {
              "time": {
                "gte": "2024-11-01",
                "lte": "2024-11-30"
              }
            }
          }
        ]
      }
    },
    "sort": {
      "time": "desc"
    },
    "size": 100
  },
  "scroll": "5s"
}
```

---

## Testing Workflow

### Step 1: Verify Authentication
Test the partner profile endpoint to confirm your API key works:
```
GET {{base_url}}/reports/partners/profile
```

### Step 2: Get Customers List
Retrieve all customers to get their IDs:
```
GET {{base_url}}/customers
```

### Step 3: Get Customer Details
Pick a customer ID and get detailed information:
```
GET {{base_url}}/customers/{customerId}
```

### Step 4: Get Users for Customer
Retrieve all users for that customer:
```
GET {{base_url}}/reports/users?customerId={customerId}
```

### Step 5: Get Events
Query security events for the customer:
```
GET {{base_url}}/reports/events?customerId={customerId}&start=2024-11-01&end=2024-11-30
```

---

## Common Response Codes

| Code | Meaning | Description |
|------|---------|-------------|
| 200 | OK | Request successful |
| 400 | Bad Request | Invalid request parameters |
| 401 | Unauthorized | Invalid or missing API key |
| 403 | Forbidden | API key doesn't have permission |
| 404 | Not Found | Resource doesn't exist |
| 406 | Not Acceptable | Request cannot be processed |
| 500 | Internal Server Error | Server-side error |

---

## Troubleshooting

### Issue: 401 Unauthorized
**Solution:** Verify the `api_key` header is set correctly with the exact value (no extra spaces)

### Issue: 404 Not Found
**Solution:** Check the base URL and endpoint path. Ensure you're using the production URL.

### Issue: Empty Response
**Solution:** Your partner account might not have any customers yet. Try the partner profile endpoint first.

### Issue: Customer ID not found
**Solution:** Use the `/customers` endpoint first to get valid customer IDs.

---

## Alternative Environments

### Development
```
Base URL: https://us-central1-saas-alerts-dev.cloudfunctions.net/reportApi/api/v1
```

### QA
```
Base URL: https://us-central1-saas-alerts-qa.cloudfunctions.net/reportApi/api/v1
```

**Note:** You may need different API keys for dev/qa environments.

---

## API Documentation Links

- **Main API Docs:** https://app.swaggerhub.com/apis/SaaS_Alerts/external-partner-api/1.0.2
- **Functions API:** https://app.swaggerhub.com/apis/SaaS_Alerts/functions/0.20.0
- **Support:** support@saasalerts.com

---

## Next Steps

After successful testing in Postman:
1. Implement the SaaS Alerts integration client in Laravel
2. Map SaaS Alerts customers to our Customer model
3. Sync users and events data
4. Build reports using the events data

---

**Last Updated:** November 26, 2024
