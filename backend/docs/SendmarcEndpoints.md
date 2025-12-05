# Sendmarc API Endpoints Documentation

## Base URL
```
https://portal.sendmarc.com/api/v1
```

## Authentication
**Method:** Bearer Token

**Headers Required for ALL requests:**
```
Authorization: Bearer {bearer_token}
Accept: application/json
```

---

## 1. Get Account List (GET)
**Endpoint:** `/accounts`

**Method:** GET

**Body Required:** ❌ No

**Purpose:** Retrieve all accounts accessible with the bearer token

**Response Format:**
```json
{
    "data": [
        {
            "uuid": "account-uuid-here",
            "name": "Account Name",
            ...
        }
    ]
}
```

**Used in Code:** Line 183

---

## 2. Get Account Domains (GET)
**Endpoint:** `/accounts/{accountUuid}/domains`

**Method:** GET

**Body Required:** ❌ No

**URL Parameters:**
- `{accountUuid}` - UUID of the account

**Purpose:** Get all domains for a specific account

**Response Format:**
```json
{
    "data": [
        {
            "uuid": "domain-uuid-here",
            "domain_name": "example.com",
            "account_uuid": "account-uuid-here",
            "suid": "...",
            "token": "...",
            "type": "...",
            "is_favourite": false,
            "enable_dkim_public_keys": false,
            "enable_bimi": false,
            "score": 0,
            ...
        }
    ]
}
```

**Used in Code:** Line 208

---

## 3. Get Domain List (Legacy) (GET)
**Endpoint:** `/domains`

**Method:** GET

**Body Required:** ❌ No

**Purpose:** Get all domains (legacy endpoint, prefer account-based endpoint)

**Response Format:**
```json
{
    "data": [...]
}
```

**Used in Code:** Line 298

---

## 4. Get Sender Statistics (GET)
**Endpoint:** `/accounts/{accountUuid}/domains/{domainUuid}/statistics/dmarc/senders`

**Method:** GET

**Body Required:** ❌ No

**URL Parameters:**
- `{accountUuid}` - UUID of the account
- `{domainUuid}` - UUID of the domain

**Purpose:** Get DMARC sender statistics for a domain

**Response Format:**
```json
[
    {
        "uuid": "sender-uuid",
        "organisation": "Google",
        "service": "Gmail",
        "service_type": "email",
        "logo": "https://...",
        "spf_value": "...",
        "category": "legitimate",
        "domain_uuid": "domain-uuid",
        "is_spf_configured": true,
        "total_incidents": 1000,
        "total_threat_incidents": 0,
        "total_blacklisted_incidents": 0,
        "total_complete_passes": 950,
        "total_dkim_fails_spf_passes": 30,
        "total_complete_failures": 20,
        "total_overrides_none": 0,
        "total_overrides_quarantine": 0,
        "total_overrides_reject": 0,
        "total_spf_fails_dkim_passes": 0,
        "total_forwards": 0,
        "timeline": [...]
    }
]
```

**Used in Code:** Line 403-404

---

## 5. Get Volume Totals (GET)
**Endpoint:** `/accounts/{accountUuid}/domains/{domainUuid}/statistics/dmarc/volume/totals`

**Method:** GET

**Body Required:** ❌ No

**URL Parameters:**
- `{accountUuid}` - UUID of the account
- `{domainUuid}` - UUID of the domain

**Purpose:** Get total volume statistics for DMARC

**Response Format:**
```json
{
    "total_incidents": 5000,
    "total_complete_passes": 4500,
    "total_dkim_fails_spf_passes": 300,
    "total_complete_failures": 200,
    "total_overrides_none": 0,
    "total_overrides_quarantine": 0,
    "total_overrides_reject": 0,
    "total_spf_fails_dkim_passes": 0,
    "total_sources": 25
}
```

**Used in Code:** Line 551-552

---

## 6. Get Volume Timeline (GET)
**Endpoint:** `/accounts/{accountUuid}/domains/{domainUuid}/statistics/dmarc/volume/timeline`

**Method:** GET

**Body Required:** ❌ No

**URL Parameters:**
- `{accountUuid}` - UUID of the account
- `{domainUuid}` - UUID of the domain

**Purpose:** Get time-series volume statistics for DMARC

**Response Format:**
```json
[
    {
        "total_incidents": 100,
        "total_complete_passes": 90,
        "total_dkim_fails_spf_passes": 5,
        "total_complete_failures": 5,
        "total_overrides_none": 0,
        "total_overrides_quarantine": 0,
        "total_overrides_reject": 0,
        "total_spf_fails_dkim_passes": 0,
        "total_sources": 5
    },
    ...
]
```

**Used in Code:** Line 638-639

---

## 7. Get Sender Details (GET)
**Endpoint:** `/accounts/{accountUuid}/domains/{domainUuid}/statistics/dmarc/senders/{senderUuid}`

**Method:** GET

**Body Required:** ❌ No

**URL Parameters:**
- `{accountUuid}` - UUID of the account
- `{domainUuid}` - UUID of the domain
- `{senderUuid}` - UUID of the sender

**Purpose:** Get detailed statistics for a specific sender including timeline

**Response Format:**
```json
{
    "uuid": "sender-uuid",
    "organisation": "Google",
    "service": "Gmail",
    "service_type": "email",
    "logo": "https://...",
    "spf_value": "...",
    "category": "legitimate",
    "is_spf_configured": true,
    "total_incidents": 1000,
    "total_threat_incidents": 0,
    "total_blacklisted_incidents": 0,
    "total_complete_passes": 950,
    "total_dkim_fails_spf_passes": 30,
    "total_complete_failures": 20,
    "total_overrides_none": 0,
    "total_overrides_quarantine": 0,
    "total_overrides_reject": 0,
    "total_spf_fails_dkim_passes": 0,
    "total_forwards": 0,
    "timeline": [...]
}
```

**Used in Code:** Line 730-731

---

## 8. Get IP Address Statistics (GET)
**Endpoint:** `/accounts/{accountUuid}/domains/{domainUuid}/statistics/dmarc/ip-addresses`

**Method:** GET

**Body Required:** ❌ No

**URL Parameters:**
- `{accountUuid}` - UUID of the account
- `{domainUuid}` - UUID of the domain

**Purpose:** Get statistics for all IP addresses (sources) sending for the domain

**Response Format:**
```json
[
    {
        "uuid": "source-uuid",
        "ip_address": "192.168.1.1",
        "hostname": "mail.example.com",
        "organisation": "Example Org",
        "service": "Email Service",
        "category": "legitimate",
        "total_incidents": 500,
        "total_complete_passes": 450,
        ...
    }
]
```

**Used in Code:** Line 1357-1358

---

## 9. Get IP Address Details (GET)
**Endpoint:** `/accounts/{accountUuid}/domains/{domainUuid}/statistics/dmarc/ip-addresses/{sourceUuid}`

**Method:** GET

**Body Required:** ❌ No

**URL Parameters:**
- `{accountUuid}` - UUID of the account
- `{domainUuid}` - UUID of the domain
- `{sourceUuid}` - UUID of the IP address/source

**Purpose:** Get detailed statistics for a specific IP address including timeline

**Response Format:**
```json
{
    "uuid": "source-uuid",
    "ip_address": "192.168.1.1",
    "hostname": "mail.example.com",
    "organisation": "Example Org",
    "service": "Email Service",
    "category": "legitimate",
    "total_incidents": 500,
    "total_complete_passes": 450,
    "timeline": [...]
}
```

**Used in Code:** Line 882-883

---

## Legacy/Alternative Endpoints

### 10. Volume Aggregates Totals (Legacy)
**Endpoint:** `/domains/{domain}/statistics/volumes/aggregates/totals`

**Method:** GET

**Used in Code:** Line 1224

---

### 11. DMARC Volume Totals (Legacy)
**Endpoint:** `/domains/{domain}/statistics/dmarc/volume/totals`

**Method:** GET

**Used in Code:** Line 1251

---

### 12. DMARC Volume Timeline (Legacy)
**Endpoint:** `/domains/{domain}/statistics/dmarc/volume/timeline`

**Method:** GET

**Used in Code:** Line 1262

---

### 13. DMARC Senders (Legacy)
**Endpoint:** `/domains/{domain}/statistics/dmarc/senders`

**Method:** GET

**Used in Code:** Line 1289

---

### 14. Sender IP Addresses (Legacy)
**Endpoint:** `/domains/{domain}/statistics/dmarc/senders/ip-addresses`

**Method:** GET

**Used in Code:** Line 1308

---

### 15. Source Details (Legacy)
**Endpoint:** `/domains/{domain}/statistics/sources/{source}`

**Method:** GET

**Used in Code:** Line 1385

---

## Alternative IP Address Endpoints (Tried in fallback)

The code tries multiple endpoints for IP addresses in this order:

1. `/accounts/{accountUuid}/domains/{domainUuid}/statistics/dmarc/ip-addresses`
2. `/accounts/{accountUuid}/domains/{domainUuid}/statistics/dmarc/sources`
3. `/accounts/{accountUuid}/domains/{domainUuid}/ip-addresses`
4. `/accounts/{accountUuid}/domains/{domainUuid}/threat-intelligence/ip-addresses`

**Used in Code:** Lines 1529-1533

---

## Complete Endpoint Summary

| # | Endpoint | Method | Body | Purpose | Line |
|---|----------|--------|------|---------|------|
| 1 | `/accounts` | GET | ❌ | Get all accounts | 183 |
| 2 | `/accounts/{accountUuid}/domains` | GET | ❌ | Get domains for account | 208 |
| 3 | `/domains` | GET | ❌ | Get all domains (legacy) | 298 |
| 4 | `/accounts/{accountUuid}/domains/{domainUuid}/statistics/dmarc/senders` | GET | ❌ | Get sender statistics | 403 |
| 5 | `/accounts/{accountUuid}/domains/{domainUuid}/statistics/dmarc/volume/totals` | GET | ❌ | Get volume totals | 551 |
| 6 | `/accounts/{accountUuid}/domains/{domainUuid}/statistics/dmarc/volume/timeline` | GET | ❌ | Get volume timeline | 638 |
| 7 | `/accounts/{accountUuid}/domains/{domainUuid}/statistics/dmarc/senders/{senderUuid}` | GET | ❌ | Get sender details | 730 |
| 8 | `/accounts/{accountUuid}/domains/{domainUuid}/statistics/dmarc/ip-addresses` | GET | ❌ | Get IP address stats | 1357 |
| 9 | `/accounts/{accountUuid}/domains/{domainUuid}/statistics/dmarc/ip-addresses/{sourceUuid}` | GET | ❌ | Get IP address details | 882 |

---

## Authentication Example

```bash
curl -X GET "https://portal.sendmarc.com/api/v1/accounts" \
  -H "Authorization: Bearer YOUR_BEARER_TOKEN_HERE" \
  -H "Accept: application/json"
```

---

## Rate Limiting

The code implements retry logic with exponential backoff:
- Max retries: 3
- Retry delays: Exponential backoff
- Handles rate limit responses automatically

**Implementation:** Line 967 (`makeApiRequest` method)

---

## Error Handling

All API calls include:
- Automatic retry on failure (up to 3 attempts)
- Detailed logging of requests and responses
- Graceful fallback for missing data
- Exception handling with context

---

## Data Flow

1. **Get Accounts** → `/accounts`
2. **For each account, get domains** → `/accounts/{accountUuid}/domains`
3. **For each domain:**
   - Get volume totals → `/statistics/dmarc/volume/totals`
   - Get volume timeline → `/statistics/dmarc/volume/timeline`
   - Get sender statistics → `/statistics/dmarc/senders`
   - Get IP address statistics → `/statistics/dmarc/ip-addresses`
4. **For detailed analysis:**
   - Get specific sender details → `/statistics/dmarc/senders/{senderUuid}`
   - Get specific IP details → `/statistics/dmarc/ip-addresses/{sourceUuid}`

---

## Notes

- **Preferred Endpoints:** Use account-based endpoints (`/accounts/{accountUuid}/domains/...`) over legacy domain-based endpoints
- **UUID Requirements:** Most endpoints require both account UUID and domain UUID
- **Response Format:** Most endpoints return data in a `data` array or directly as an array
- **Pagination:** Not explicitly implemented in the code (appears to return all results)
- **Date Filtering:** Not shown in endpoints (may be query parameters if needed)

---

## Files Reference

- **Driver Implementation:** `app/Services/Sources/Drivers/SendmarcDriver.php`
- **Models:**
  - `app/Models/SendmarcDomain.php`
  - `app/Models/SendmarcSender.php`
  - `app/Models/SendmarcSource.php`
  - `app/Models/SendmarcIpAddress.php`
  - `app/Models/SendmarcVolumeStats.php`
