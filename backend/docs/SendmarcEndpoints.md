# Sendmarc API Endpoints Documentation

## Base URL
```
https://portal.sendmarc.com/api/v1
```

## Authentication
**Method:** Bearer Token

**Headers Required for ALL requests:**
```
Authorization: Bearer 286|pnHqEJURKpr2cG5bwbpOyfTEfZ5MOp3hbZm1gM8fe9fd9be4
Accept: application/json
```

---

## 1. Get Account List (GET)
**Endpoint:** `/accounts`

**Full URL:** `GET https://portal.sendmarc.com/api/v1/accounts`

**Method:** GET

**Body Required:** No

**Purpose:** Retrieve all accounts accessible with the bearer token

**Real Response Example (Territory Technology):**
```json
[
    {
        "uuid": "9590e4da-a649-453d-808d-a1869d2bb000",
        "package_synced_at": "2025-06-16T05:20:54.000000Z",
        "name": "Territory Technology",
        "email": "dmarcs@techsolutions.com.au",
        "is_on_demand": false,
        "enable_acronis": false,
        "level": "partner administrator",
        "phase": "implementation",
        "package_name": "premium (per user)",
        "package_status": "active",
        "lookalike_package_name": null,
        "lookalike_package_status": null,
        "identity_package_name": null,
        "identity_package_status": null,
        "status": "active",
        "sso_status": "forced",
        "sso_provider": "microsoft",
        "sso_base_url": null,
        "sso_client_id": null,
        "two_factor_status": "enabled",
        "trial_set_at": "2025-05-14T23:45:12.000000Z",
        "billing_period_expires_at": null,
        "created_at": "2025-04-23T04:12:26.000000Z",
        "updated_at": "2025-06-16T05:20:52.000000Z"
    }
]
```

**Key Fields:**
- `uuid` - Account identifier (use for domain queries)
- `name` - Account name
- `email` - Contact email
- `package_name` - Subscription tier
- `package_status` - Account status (active/inactive)
- `level` - User permission level

**Used in Code:** Line 183

---

## 2. Get Account Domains (GET)
**Endpoint:** `/accounts/{accountUuid}/domains`

**Full URL Example:** `GET https://portal.sendmarc.com/api/v1/accounts/9590e4da-a649-453d-808d-a1869d2bb000/domains`

**Method:** GET

**Body Required:** No

**URL Parameters:**
- `{accountUuid}` - UUID of the account (e.g., `9590e4da-a649-453d-808d-a1869d2bb000`)

**Purpose:** Get all domains for a specific account

**Real Response Example (Territory Technology - 9 domains):**
```json
[
    {
        "uuid": "7437efb0-3b2c-4c5b-a00d-e68395bca145",
        "suid": "4gwwg6o6",
        "account_uuid": "9590e4da-a649-453d-808d-a1869d2bb000",
        "token": "z3gcE9d-%jH6oxUSYo%kogll5RvFAXx*xcEQ8cZR^xgLbqCy",
        "domain_name": "brisbanetechsolutions.com.au",
        "enable_dkim_public_keys": true,
        "dkim_public_key_name_servers": [
            "ns1.sendmarc.net.",
            "ns2.sendmarc.net."
        ],
        "enable_bimi": false,
        "type": "proactive",
        "is_favourite": false,
        "dkim_policy_settings": {
            "o": "~",
            "t": "y"
        },
        "bimi_name_servers": [],
        "mx_servers": [],
        "score": 5,
        "created_at": "2025-06-16T00:12:15.000000Z",
        "updated_at": "2025-07-24T12:29:39.000000Z"
    },
    {
        "uuid": "8293799f-4b58-47e7-9e9e-722430d78283",
        "suid": "r2yskvec",
        "account_uuid": "9590e4da-a649-453d-808d-a1869d2bb000",
        "token": "6N4D66iRUyhFj6ARvaa%amH9kckpJJy2$6WlyN8_&2S6dgJF",
        "domain_name": "techsolutions.com.au",
        "enable_dkim_public_keys": true,
        "dkim_public_key_name_servers": [
            "ns1.sendmarc.net.",
            "ns2.sendmarc.net."
        ],
        "enable_bimi": false,
        "type": "proactive",
        "is_favourite": false,
        "dkim_policy_settings": {
            "o": "~",
            "t": "y"
        },
        "bimi_name_servers": [],
        "mx_servers": [],
        "score": 5,
        "created_at": "2025-04-23T04:18:00.000000Z",
        "updated_at": "2025-09-01T04:20:00.000000Z"
    }
]
```

**Note:** Response shows 2 of 9 total domains. Full list includes:
- brisbanetechsolutions.com.au
- arafura.com.au
- broometechsolutions.com.au
- arafuraconnect.com.au
- arafuracloud.com.au
- atsg.com.au
- arafura.net.au
- techsolutions.com.au
- itblog.com.au

**Key Fields:**
- `uuid` - Domain identifier (use for statistics queries)
- `domain_name` - Domain name
- `account_uuid` - Parent account UUID
- `suid` - Short unique identifier
- `score` - Domain health score (0-10)
- `type` - Domain type (proactive/reactive)
- `enable_dkim_public_keys` - DKIM configuration status

**Used in Code:** Line 208

---

## 3. Get Domain List (Legacy) (GET)
**Endpoint:** `/domains`

**Method:** GET

**Body Required:** No

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

**Full URL Example:** `GET https://portal.sendmarc.com/api/v1/accounts/9590e4da-a649-453d-808d-a1869d2bb000/domains/8293799f-4b58-47e7-9e9e-722430d78283/statistics/dmarc/senders`

**Method:** GET

**Body Required:** No

**URL Parameters:**
- `{accountUuid}` - UUID of the account
- `{domainUuid}` - UUID of the domain

**Purpose:** Get DMARC sender statistics for a domain

**Real Response Example (techsolutions.com.au):**
```json
[]
```

**Note:** Empty array indicates no sender data available for this domain in the current period. This is common for:
- Newly configured domains
- Domains with no recent email activity
- Domains outside the current reporting window

**Expected Format (when data exists):**
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

**Full URL Example:** `GET https://portal.sendmarc.com/api/v1/accounts/9590e4da-a649-453d-808d-a1869d2bb000/domains/8293799f-4b58-47e7-9e9e-722430d78283/statistics/dmarc/volume/totals`

**Method:** GET

**Body Required:** No

**URL Parameters:**
- `{accountUuid}` - UUID of the account
- `{domainUuid}` - UUID of the domain

**Purpose:** Get total volume statistics for DMARC

**Real Response Example (techsolutions.com.au):**
```json
{
    "total_incidents": 0,
    "total_complete_passes": 0,
    "total_dkim_fails_spf_passes": 0,
    "total_complete_failures": 0,
    "total_overrides_none": 0,
    "total_overrides_quarantine": 0,
    "total_overrides_reject": 0,
    "total_spf_fails_dkim_passes": 0,
    "total_sources": 0
}
```

**Note:** All zeros indicate no email activity recorded for this domain in the current period.

**Field Descriptions:**
- `total_incidents` - Total email messages processed
- `total_complete_passes` - Messages passing both SPF and DKIM
- `total_dkim_fails_spf_passes` - Messages passing SPF only
- `total_spf_fails_dkim_passes` - Messages passing DKIM only
- `total_complete_failures` - Messages failing both SPF and DKIM
- `total_overrides_none` - Policy override: none
- `total_overrides_quarantine` - Policy override: quarantine
- `total_overrides_reject` - Policy override: reject
- `total_sources` - Number of unique sending sources

**Used in Code:** Line 551-552

---

## 6. Get Volume Timeline (GET)
**Endpoint:** `/accounts/{accountUuid}/domains/{domainUuid}/statistics/dmarc/volume/timeline`

**Full URL Example:** `GET https://portal.sendmarc.com/api/v1/accounts/9590e4da-a649-453d-808d-a1869d2bb000/domains/8293799f-4b58-47e7-9e9e-722430d78283/statistics/dmarc/volume/timeline`

**Method:** GET

**Body Required:** No

**URL Parameters:**
- `{accountUuid}` - UUID of the account
- `{domainUuid}` - UUID of the domain

**Purpose:** Get time-series volume statistics for DMARC

**Real Response Example (techsolutions.com.au):**
```json
[]
```

**Note:** Empty array indicates no timeline data available. This is expected when there's no email activity.

**Expected Format (when data exists):**
```json
[
    {
        "date": "2025-12-01",
        "total_incidents": 100,
        "total_complete_passes": 90,
        "total_dkim_fails_spf_passes": 5,
        "total_complete_failures": 5,
        "total_overrides_none": 0,
        "total_overrides_quarantine": 0,
        "total_overrides_reject": 0,
        "total_spf_fails_dkim_passes": 0,
        "total_sources": 5
    }
]
```

**Used in Code:** Line 638-639

---

## 7. Get Sender Details (GET)
**Endpoint:** `/accounts/{accountUuid}/domains/{domainUuid}/statistics/dmarc/senders/{senderUuid}`

**Full URL Example:** `GET https://portal.sendmarc.com/api/v1/accounts/9590e4da-a649-453d-808d-a1869d2bb000/domains/8293799f-4b58-47e7-9e9e-722430d78283/statistics/dmarc/senders/{sender_uuid}`

**Method:** GET

**Body Required:** No

**URL Parameters:**
- `{accountUuid}` - UUID of the account
- `{domainUuid}` - UUID of the domain
- `{senderUuid}` - UUID of the sender (obtained from endpoint #4)

**Purpose:** Get detailed statistics for a specific sender including timeline

**Real Response:** Not available (no senders exist for test domain)

**Expected Format (when sender exists):**
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
    "timeline": [
        {
            "date": "2025-12-01",
            "total_incidents": 50,
            "total_complete_passes": 48,
            "total_complete_failures": 2
        }
    ]
}
```

**Used in Code:** Line 730-731

---

## 8. Get IP Address Statistics (GET)
**Endpoint:** `/accounts/{accountUuid}/domains/{domainUuid}/statistics/dmarc/ip-addresses`

**Full URL Example:** `GET https://portal.sendmarc.com/api/v1/accounts/9590e4da-a649-453d-808d-a1869d2bb000/domains/8293799f-4b58-47e7-9e9e-722430d78283/statistics/dmarc/ip-addresses`

**Method:** GET

**Body Required:** No

**URL Parameters:**
- `{accountUuid}` - UUID of the account
- `{domainUuid}` - UUID of the domain

**Purpose:** Get statistics for all IP addresses (sources) sending for the domain

**Real Response Example (techsolutions.com.au):**
```json
[]
```

**Note:** Empty array indicates no IP address data available for this domain.

**Expected Format (when data exists):**
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
        "total_dkim_fails_spf_passes": 30,
        "total_complete_failures": 20,
        "total_spf_fails_dkim_passes": 0
    }
]
```

**Used in Code:** Line 1357-1358

---

## 9. Get IP Address Details (GET)
**Endpoint:** `/accounts/{accountUuid}/domains/{domainUuid}/statistics/dmarc/ip-addresses/{sourceUuid}`

**Full URL Example:** `GET https://portal.sendmarc.com/api/v1/accounts/9590e4da-a649-453d-808d-a1869d2bb000/domains/8293799f-4b58-47e7-9e9e-722430d78283/statistics/dmarc/ip-addresses/{source_uuid}`

**Method:** GET

**Body Required:** No

**URL Parameters:**
- `{accountUuid}` - UUID of the account
- `{domainUuid}` - UUID of the domain
- `{sourceUuid}` - UUID of the IP address/source (obtained from endpoint #8)

**Purpose:** Get detailed statistics for a specific IP address including timeline

**Real Response:** Not available (no IP addresses exist for test domain)

**Expected Format (when source exists):**
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
    "total_dkim_fails_spf_passes": 30,
    "total_complete_failures": 20,
    "timeline": [
        {
            "date": "2025-12-01",
            "total_incidents": 25,
            "total_complete_passes": 23,
            "total_complete_failures": 2
        }
    ]
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
| 1 | `/accounts` | GET | No | Get all accounts | 183 |
| 2 | `/accounts/{accountUuid}/domains` | GET | No | Get domains for account | 208 |
| 3 | `/domains` | GET | No | Get all domains (legacy) | 298 |
| 4 | `/accounts/{accountUuid}/domains/{domainUuid}/statistics/dmarc/senders` | GET | No | Get sender statistics | 403 |
| 5 | `/accounts/{accountUuid}/domains/{domainUuid}/statistics/dmarc/volume/totals` | GET | No | Get volume totals | 551 |
| 6 | `/accounts/{accountUuid}/domains/{domainUuid}/statistics/dmarc/volume/timeline` | GET | No | Get volume timeline | 638 |
| 7 | `/accounts/{accountUuid}/domains/{domainUuid}/statistics/dmarc/senders/{senderUuid}` | GET | No | Get sender details | 730 |
| 8 | `/accounts/{accountUuid}/domains/{domainUuid}/statistics/dmarc/ip-addresses` | GET | No | Get IP address stats | 1357 |
| 9 | `/accounts/{accountUuid}/domains/{domainUuid}/statistics/dmarc/ip-addresses/{sourceUuid}` | GET | No | Get IP address details | 882 |

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
