# Artisan Commands - MSP Reports Platform

## Integration Management Commands

These interactive commands help you manage integrations and API keys without needing the frontend UI.

Note:
- Local development may use SQLite by default.
- Some database behaviors (e.g., `SET timezone`) are Postgres-specific and are only applied when the configured driver is `pgsql`.

### Create Integration

**Create a new integration interactively:**
```bash
php artisan integration:create
```

**Create with options (skip prompts):**
```bash
php artisan integration:create --company-id=1 --type=ActiveDirectory
```

**What it does:**
1. Selects a company (or uses --company-id)
2. Chooses integration type (or uses --type)
3. Checks for existing integration
4. Verifies license (warns if missing)
5. Prompts for display name
6. Collects integration-specific configuration
7. Creates the integration
8. Offers to create API key (for ActiveDirectory)

**Example Output:**
```
=== Create New Integration ===

Selected Company: Territory Technology Solutions (ID: 1)

Select integration type:
  [0] Autotask
  [1] Datto
  [2] Sendmarc
  [3] Barracuda
  [4] ITGlue
  [5] ActiveDirectory
 > 5

License verified: integration_activedirectory

Display name for this integration [ActiveDirectory - Territory Technology Solutions]:
 > TTS Active Directory

Active Directory Configuration:
Active Directory domain (e.g., customer.local):
 > tts.local

Include disabled users? (yes/no) [no]:
 > no

Exclude system groups? (yes/no) [yes]:
 > yes

Agent sync interval (minutes) [240]:
 > 240

Create this integration? (yes/no) [yes]:
 > yes

Integration created successfully!

┌──────────────────┬────────────────────────────────────────┐
│ Property         │ Value                                  │
├──────────────────┼────────────────────────────────────────┤
│ ID               │ 3                                      │
│ Type             │ ActiveDirectory                        │
│ Display Name     │ TTS Active Directory                   │
│ Company          │ Territory Technology Solutions         │
│ Status           │ active                                 │
│ Source of Truth  │ No                                     │
│ Last Sync        │ Never                                  │
│ Created          │ 2025-11-18 06:45:00                    │
└──────────────────┴────────────────────────────────────────┘

Configuration:
  domain: tts.local
  include_disabled_users: false
  exclude_system_groups: true
  sync_interval: 240

Would you like to create an API key for the AD agent? (yes/no) [yes]:
 > yes
```

---

### List Integrations

**List all integrations:**
```bash
php artisan integration:list
```

**List for specific company:**
```bash
php artisan integration:list --company-id=1
```

**Example Output:**
```
Integrations for: Territory Technology Solutions

┌────┬─────────────────┬──────────────────────┬────────────────┬────────────┬─────┬────────────┬─────────────┐
│ ID │ Type            │ Display Name         │ Company        │ Status     │ SoT │ Last Sync  │ Sync Status │
├────┼─────────────────┼──────────────────────┼────────────────┼────────────┼─────┼────────────┼─────────────┤
│ 1  │ Autotask        │ Autotask PSA         │ TTS            │ active   │     │ 2 hours ago│ success     │
│ 2  │ Datto           │ Datto RMM            │ TTS            │ active   │   │ 1 hour ago │ success     │
│ 3  │ ActiveDirectory │ TTS Active Directory │ TTS            │ active   │     │ Never      │ N/A         │
└────┴─────────────────┴──────────────────────┴────────────────┴────────────┴─────┴────────────┴─────────────┘

Total: 3
```

---

### Create API Key

**Create API key interactively:**
```bash
php artisan apikey:create
```

**Create with options:**
```bash
php artisan apikey:create --company-id=1 --name="Active Directory Sync Agent" --integrations-sync
```

**What it does:**
1. Selects a company
2. Prompts for display name
3. Configures granular permissions:
   - Integrations (view, manage, sync)
   - Customers (view, edit, map)
   - Reports (view, configure, generate, download)
   - Users (view, manage) - usually false
   - Company settings (view, edit) - usually false
4. Optional IP whitelist
5. Optional endpoint restrictions
6. Generates secure 64-character key
7. Shows the plaintext key (ONLY ONCE!)
8. Provides usage examples

**Example Output:**
```
=== Create New API Key ===

Selected Company: Territory Technology Solutions (ID: 1)

Display name for this API key [API Key]:
 > Active Directory Sync Agent

Configure Permissions:

Allow integration sync? (yes/no) [yes]:
 > yes

  View integrations? (yes/no) [yes]:
   > yes

  Manage integrations? (yes/no) [no]:
   > no

  Trigger syncs? (yes/no) [yes]:
   > yes

Allow customer access? (yes/no) [no]:
 > no

Allow report access? (yes/no) [no]:
 > no

Configure IP whitelist? (yes/no) [no]:
 > no

Restrict to specific endpoints? (yes/no) [no]:
 > yes

Enter allowed endpoints (comma-separated):
 > api/integrations/active-directory/push

Summary:
┌────────────────────────┬──────────────────────────────────────────┐
│ Property               │ Value                                    │
├────────────────────────┼──────────────────────────────────────────┤
│ Company                │ Territory Technology Solutions           │
│ Display Name           │ Active Directory Sync Agent              │
│ Integrations Sync      │ Yes                                      │
│ IP Whitelist           │ None                                     │
│ Endpoint Restrictions  │ api/integrations/active-directory/push   │
└────────────────────────┴──────────────────────────────────────────┘

Create this API key? (yes/no) [yes]:
 > yes

API Key created successfully!

IMPORTANT: This key will only be shown ONCE. Store it securely!

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
API Key: msp_7d8f9a0b1c2d3e4f5g6h7i8j9k0l1m2n3o4p5q6r7s8t9u0v1w2x3y4z5a6b7c8d
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

┌──────────────┬────────────────────────────────┐
│ Property     │ Value                          │
├──────────────┼────────────────────────────────┤
│ Key ID       │ 5                              │
│ Display Name │ Active Directory Sync Agent    │
│ Company      │ Territory Technology Solutions │
│ Created      │ 2025-11-18 06:50:00            │
└──────────────┴────────────────────────────────┘

Usage Examples:

Active Directory Agent (config.json):
  "platform": {
    "apiUrl": "https://your-platform-url/api",
    "apiKey": "msp_7d8f9a0b1c2d3e4f5g6h7i8j9k0l1m2n3o4p5q6r7s8t9u0v1w2x3y4z5a6b7c8d",
    "companyId": 1
  }

cURL Request:
  curl -X POST https://your-platform-url/api/integrations/active-directory/push \
    -H "Authorization: Bearer msp_7d8f9a0b1c2d3e4f5g6h7i8j9k0l1m2n3o4p5q6r7s8t9u0v1w2x3y4z5a6b7c8d" \
    -H "Content-Type: application/json" \
    -d '{"companyId": 1, ...}'
```

---

## Usage Flow for Active Directory Setup

### 1. Create Company (if needed)
```bash
# Usually done during initial setup
# Check existing: php artisan tinker
# >>> Company::all()
```

### 2. Create License (first customer gets unlimited)
```bash
php artisan tinker
>>> License::create([
    'company_id' => 1,
    'product_sku' => 'unlimited',
    'status' => 'active',
    'expiry_date' => null,
    'quantity' => null,
]);
```

### 3. Create Active Directory Integration
```bash
php artisan integration:create --company-id=1 --type=ActiveDirectory
```

### 4. Create API Key for Agent
```bash
# Created automatically by step 3, or manually:
php artisan apikey:create --company-id=1 --name="AD Sync Agent" --integrations-sync
```

### 5. Configure Agent on Windows Server
Copy the API key and update `config.json` on the Windows Server:
```json
{
  "platform": {
    "apiUrl": "https://reports.tts.com.au/api",
    "apiKey": "msp_...",
    "companyId": 1
  }
}
```

### 6. Verify Setup
```bash
# Check integration exists
php artisan integration:list --company-id=1

# Monitor logs when agent first syncs
tail -f storage/logs/laravel.log
```

---

## Tips

**Fast Command Flags:**
```bash
# Skip all prompts for AD integration
php artisan integration:create \
  --company-id=1 \
  --type=ActiveDirectory

# Skip all prompts for API key
php artisan apikey:create \
  --company-id=1 \
  --name="AD Agent" \
  --integrations-sync
```

**Check What Was Created:**
```bash
php artisan integration:list
```

**Laravel Tinker for Advanced Queries:**
```bash
php artisan tinker

>>> Integration::with('company')->get()
>>> ApiKey::where('display_name', 'LIKE', '%Active%')->get()
>>> Company::find(1)->integrations
```

---

## Security Notes

1. **API Keys:** Shown only ONCE during creation - store securely
2. **Permissions:** Minimum required permissions only (principle of least privilege)
3. **IP Whitelist:** Recommended for production
4. **Endpoint Restrictions:** Use for agent-based integrations
5. **Key Rotation:** Rotate keys every 90 days (manual process)

---

## Available Commands Summary

| Command | Description |
|---------|-------------|
| `integration:create` | Interactively create new integration |
| `integration:list` | List all integrations |
| `apikey:create` | Create API key with granular permissions |

---

## Next Steps After Creating AD Integration

1. **Run migrations** (if not done):
   ```bash
   php artisan migrate
   ```

2. **Deploy agent** to Windows Server

3. **Monitor first sync**:
   ```bash
   tail -f storage/logs/laravel.log | grep -i "active directory"
   ```

4. **Verify data** in database:
   ```bash
   php artisan tinker
   >>> ActiveDirectoryUser::count()
   >>> ActiveDirectoryGroup::count()
   ```

5. **Build frontend** to view the data (next task!)
