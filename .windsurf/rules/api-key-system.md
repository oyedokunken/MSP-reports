---
trigger: always_on
---

## API Key System

### Purpose

Allow programmatic access to platform with same granular permissions as human users.

### API Key Model

See ApiKey Model in Data Models section for full structure.

### Permission System

**Same Structure as User Permissions:**

- Integrations: view/manage/sync
- Customers: view/edit/map
- Reports: view/configure/generate/download
- Users: view/manage (typically false for API keys)
- Company settings: view/edit (typically false for API keys)

**Use Cases:**

- **Report Generation Key:** Can trigger report generation and download results, can't modify configurations
- **Sync-Only Key:** Can trigger integration syncs, can't view customer data
- **Read-Only Key:** Can query reports and customer data, can't modify anything
- **Webhook Key:** Can receive webhooks and update specific data, restricted scope

### API Key Generation

**Process:**

1. User with "manage users" permission creates API key
2. Sets display name: "Production Reporting API"
3. Configures permissions (checkboxes like user permissions)
4. Optional: IP whitelist, endpoint restrictions
5. System generates cryptographically secure key (64+ chars)
6. Displays key ONCE (user must copy immediately)
7. Stores bcrypt hash only
8. Key lost? Must revoke and create new one

### Authentication Flow

**API Request with Key:**

```
Authorization: Bearer {api_key}
```

**Middleware:**

1. Extract API key from header
2. Hash and lookup in database
3. Load associated Company
4. Check if key revoked
5. Check if IP allowed (if whitelist configured)
6. Load key's permissions
7. Scope all queries to company
8. Validate requested action against permissions
9. Log request to AuditLog with ApiKey as actor

### Rate Limiting

**Separate from User Sessions:**

- API keys have own rate limit pool
- Typically lower than UI users (prevent abuse)
- Per-key tracking
- Per-endpoint limits (more restrictive on write operations)

**Rate Limit Headers:**

```
X-RateLimit-Limit: 100
X-RateLimit-Remaining: 47
X-RateLimit-Reset: 1640000000
```

### API Key Management

**Revocation:**

- User can revoke key anytime
- Immediate effect (Redis cache cleared)
- Audit log records who revoked when
- Can include revocation reason

**Rotation:**

- No automatic rotation (user controls)
- UI shows "Created 6 months ago - consider rotating"
- Best practice: rotate every 6-12 months

**Monitoring:**

- Last used timestamp visible in UI
- Unused keys flagged (security risk)
- Usage statistics (requests per day/week)