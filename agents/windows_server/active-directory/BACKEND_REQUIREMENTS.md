# Backend Requirements for Active Directory Agent

## Overview

The Active Directory agent pushes data to the MSP Reports Platform backend. This document specifies the required backend implementation.

## API Endpoint

### POST /api/integrations/active-directory/push

**Authentication:** Bearer token (API key)

**Request Headers:**
```
Authorization: Bearer {api_key}
Content-Type: application/json
User-Agent: MSPReports-AD-Agent/1.0
Accept: application/json
```

**Request Payload:**
```json
{
  "companyId": 1,
  "integrationId": null,
  "syncType": "full",
  "syncTimestamp": "2025-11-18T06:00:00Z",
  "domain": "customer.local",
  "data": {
    "users": [
      {
        "userPrincipalName": "john.doe@customer.local",
        "samAccountName": "jdoe",
        "displayName": "John Doe",
        "givenName": "John",
        "surname": "Doe",
        "email": "john.doe@customer.com",
        "department": "IT",
        "title": "System Administrator",
        "manager": "Jane Smith",
        "managerDN": "CN=Jane Smith,OU=Users,DC=customer,DC=local",
        "enabled": true,
        "lastLogon": "2025-11-17T14:30:00Z",
        "passwordLastSet": "2025-10-15T09:00:00Z",
        "passwordNeverExpires": false,
        "accountExpiration": null,
        "lockedOut": false,
        "created": "2024-01-15T10:00:00Z",
        "modified": "2025-11-17T14:30:00Z",
        "distinguishedName": "CN=John Doe,OU=Users,DC=customer,DC=local",
        "description": "IT Department User",
        "office": "Sydney Office",
        "officePhone": "+61 2 1234 5678",
        "mobilePhone": "+61 4 1234 5678",
        "company": "Customer Company"
      }
    ],
    "securityGroups": [
      {
        "name": "IT Admins",
        "samAccountName": "IT_Admins",
        "groupCategory": "Security",
        "groupScope": "Global",
        "description": "IT Administrator Group",
        "created": "2024-01-10T10:00:00Z",
        "modified": "2025-11-01T12:00:00Z",
        "distinguishedName": "CN=IT Admins,OU=Groups,DC=customer,DC=local",
        "managedBy": "John Doe",
        "managedByDN": "CN=John Doe,OU=Users,DC=customer,DC=local"
      }
    ],
    "groupMemberships": [
      {
        "groupName": "IT Admins",
        "groupDN": "CN=IT Admins,OU=Groups,DC=customer,DC=local",
        "userSamAccountName": "jdoe",
        "userDN": "CN=John Doe,OU=Users,DC=customer,DC=local",
        "membershipType": "Direct"
      }
    ],
    "licenseUsage": [
      {
        "userPrincipalName": "john.doe@customer.local",
        "skuId": "6fd2c87f-b296-42f0-b197-1e91e994b900",
        "skuPartNumber": "ENTERPRISEPACK",
        "disabledPlans": [],
        "assignedDate": "2024-02-01T10:00:00Z"
      }
    ]
  },
  "statistics": {
    "userCount": 250,
    "groupCount": 45,
    "membershipCount": 780,
    "licenseCount": 200
  }
}
```

**Success Response (200 OK):**
```json
{
  "success": true,
  "message": "Active Directory data synced successfully",
  "data": {
    "integrationId": 5,
    "syncLogId": 123,
    "usersProcessed": 250,
    "groupsProcessed": 45,
    "membershipsProcessed": 780,
    "licensesProcessed": 200,
    "timestamp": "2025-11-18T06:00:15Z"
  }
}
```

**Error Response (400 Bad Request):**
```json
{
  "success": false,
  "message": "Validation failed",
  "errors": {
    "companyId": ["The company ID is required"],
    "data.users": ["At least one user is required"]
  }
}
```

**Error Response (401 Unauthorized):**
```json
{
  "success": false,
  "message": "Invalid API key"
}
```

**Error Response (500 Internal Server Error):**
```json
{
  "success": false,
  "message": "Failed to process Active Directory data",
  "error": "Database connection failed"
}
```

## Backend Implementation Requirements

### 1. Database Models

#### ActiveDirectoryUser Model
```php
namespace App\Models;

class ActiveDirectoryUser extends Model
{
    protected $fillable = [
        'company_id',
        'integration_id',
        'user_principal_name',
        'sam_account_name',
        'display_name',
        'given_name',
        'surname',
        'email',
        'department',
        'title',
        'manager',
        'manager_dn',
        'enabled',
        'last_logon',
        'password_last_set',
        'password_never_expires',
        'account_expiration',
        'locked_out',
        'created_at_ad',
        'modified_at_ad',
        'distinguished_name',
        'description',
        'office',
        'office_phone',
        'mobile_phone',
        'company'
    ];
    
    protected $casts = [
        'enabled' => 'boolean',
        'password_never_expires' => 'boolean',
        'locked_out' => 'boolean',
        'last_logon' => 'datetime',
        'password_last_set' => 'datetime',
        'account_expiration' => 'datetime',
        'created_at_ad' => 'datetime',
        'modified_at_ad' => 'datetime'
    ];
}
```

#### ActiveDirectoryGroup Model
```php
namespace App\Models;

class ActiveDirectoryGroup extends Model
{
    protected $fillable = [
        'company_id',
        'integration_id',
        'name',
        'sam_account_name',
        'group_category',
        'group_scope',
        'description',
        'created_at_ad',
        'modified_at_ad',
        'distinguished_name',
        'managed_by',
        'managed_by_dn'
    ];
    
    protected $casts = [
        'created_at_ad' => 'datetime',
        'modified_at_ad' => 'datetime'
    ];
}
```

#### ActiveDirectoryGroupMembership Model
```php
namespace App\Models;

class ActiveDirectoryGroupMembership extends Model
{
    protected $fillable = [
        'company_id',
        'integration_id',
        'group_name',
        'group_dn',
        'user_sam_account_name',
        'user_dn',
        'membership_type'
    ];
}
```

#### Microsoft365License Model
```php
namespace App\Models;

class Microsoft365License extends Model
{
    protected $fillable = [
        'company_id',
        'integration_id',
        'user_principal_name',
        'sku_id',
        'sku_part_number',
        'disabled_plans',
        'assigned_date'
    ];
    
    protected $casts = [
        'disabled_plans' => 'array',
        'assigned_date' => 'datetime'
    ];
}
```

### 2. Migration Files

#### active_directory_users table
```php
Schema::create('active_directory_users', function (Blueprint $table) {
    $table->id();
    $table->foreignId('company_id')->constrained()->cascadeOnDelete();
    $table->foreignId('integration_id')->nullable()->constrained()->nullOnDelete();
    $table->string('user_principal_name')->index();
    $table->string('sam_account_name')->index();
    $table->string('display_name')->nullable();
    $table->string('given_name')->nullable();
    $table->string('surname')->nullable();
    $table->string('email')->nullable()->index();
    $table->string('department')->nullable();
    $table->string('title')->nullable();
    $table->string('manager')->nullable();
    $table->text('manager_dn')->nullable();
    $table->boolean('enabled')->default(true);
    $table->timestamp('last_logon')->nullable();
    $table->timestamp('password_last_set')->nullable();
    $table->boolean('password_never_expires')->default(false);
    $table->timestamp('account_expiration')->nullable();
    $table->boolean('locked_out')->default(false);
    $table->timestamp('created_at_ad')->nullable();
    $table->timestamp('modified_at_ad')->nullable();
    $table->text('distinguished_name')->nullable();
    $table->text('description')->nullable();
    $table->string('office')->nullable();
    $table->string('office_phone')->nullable();
    $table->string('mobile_phone')->nullable();
    $table->string('company')->nullable();
    $table->timestamps();
    
    $table->unique(['company_id', 'user_principal_name']);
    $table->index(['company_id', 'enabled']);
});
```

#### active_directory_groups table
```php
Schema::create('active_directory_groups', function (Blueprint $table) {
    $table->id();
    $table->foreignId('company_id')->constrained()->cascadeOnDelete();
    $table->foreignId('integration_id')->nullable()->constrained()->nullOnDelete();
    $table->string('name')->index();
    $table->string('sam_account_name')->index();
    $table->string('group_category');
    $table->string('group_scope');
    $table->text('description')->nullable();
    $table->timestamp('created_at_ad')->nullable();
    $table->timestamp('modified_at_ad')->nullable();
    $table->text('distinguished_name')->nullable();
    $table->string('managed_by')->nullable();
    $table->text('managed_by_dn')->nullable();
    $table->timestamps();
    
    $table->unique(['company_id', 'distinguished_name']);
});
```

#### active_directory_group_memberships table
```php
Schema::create('active_directory_group_memberships', function (Blueprint $table) {
    $table->id();
    $table->foreignId('company_id')->constrained()->cascadeOnDelete();
    $table->foreignId('integration_id')->nullable()->constrained()->nullOnDelete();
    $table->string('group_name');
    $table->text('group_dn');
    $table->string('user_sam_account_name');
    $table->text('user_dn');
    $table->string('membership_type')->default('Direct');
    $table->timestamps();
    
    $table->index(['company_id', 'group_name']);
    $table->index(['company_id', 'user_sam_account_name']);
    $table->unique(['company_id', 'group_dn', 'user_dn']);
});
```

#### microsoft_365_licenses table
```php
Schema::create('microsoft_365_licenses', function (Blueprint $table) {
    $table->id();
    $table->foreignId('company_id')->constrained()->cascadeOnDelete();
    $table->foreignId('integration_id')->nullable()->constrained()->nullOnDelete();
    $table->string('user_principal_name')->index();
    $table->string('sku_id');
    $table->string('sku_part_number');
    $table->json('disabled_plans')->nullable();
    $table->timestamp('assigned_date')->nullable();
    $table->timestamps();
    
    $table->index(['company_id', 'sku_part_number']);
    $table->unique(['company_id', 'user_principal_name', 'sku_id']);
});
```

### 3. Controller

#### ActiveDirectoryController
```php
namespace App\Http\Controllers\Api;

class ActiveDirectoryController extends Controller
{
    public function push(Request $request)
    {
        // Validate request
        $validated = $request->validate([
            'companyId' => 'required|integer|exists:companies,id',
            'integrationId' => 'nullable|integer|exists:integrations,id',
            'syncType' => 'required|in:full,delta',
            'syncTimestamp' => 'required|date',
            'domain' => 'required|string',
            'data' => 'required|array',
            'data.users' => 'required|array',
            'data.securityGroups' => 'required|array',
            'data.groupMemberships' => 'required|array',
            'data.licenseUsage' => 'array',
            'statistics' => 'required|array'
        ]);
        
        // Start transaction
        DB::beginTransaction();
        
        try {
            // Get or create integration
            $integration = $this->getOrCreateIntegration($validated['companyId'], $validated['domain']);
            
            // Process users
            $usersProcessed = $this->processUsers($integration, $validated['data']['users']);
            
            // Process groups
            $groupsProcessed = $this->processGroups($integration, $validated['data']['securityGroups']);
            
            // Process memberships
            $membershipsProcessed = $this->processMemberships($integration, $validated['data']['groupMemberships']);
            
            // Process licenses
            $licensesProcessed = $this->processLicenses($integration, $validated['data']['licenseUsage'] ?? []);
            
            // Create sync log
            $syncLog = SyncLog::create([
                'integration_id' => $integration->id,
                'sync_type' => $validated['syncType'],
                'started_at' => now(),
                'completed_at' => now(),
                'status' => 'success',
                'records_synced' => $usersProcessed + $groupsProcessed + $membershipsProcessed,
                'records_failed' => 0
            ]);
            
            DB::commit();
            
            return response()->json([
                'success' => true,
                'message' => 'Active Directory data synced successfully',
                'data' => [
                    'integrationId' => $integration->id,
                    'syncLogId' => $syncLog->id,
                    'usersProcessed' => $usersProcessed,
                    'groupsProcessed' => $groupsProcessed,
                    'membershipsProcessed' => $membershipsProcessed,
                    'licensesProcessed' => $licensesProcessed,
                    'timestamp' => now()->toIso8601String()
                ]
            ]);
            
        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('AD sync failed: ' . $e->getMessage(), [
                'company_id' => $validated['companyId'],
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to process Active Directory data',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
```

### 4. Route Registration

```php
// In routes/api.php
Route::middleware(['auth:sanctum', 'throttle:60,1'])->group(function () {
    Route::post('/integrations/active-directory/push', [ActiveDirectoryController::class, 'push']);
});
```

### 5. Service Class

Create `app/Services/ActiveDirectorySyncService.php` to handle:
- User upsert logic (insert or update based on UPN)
- Group upsert logic
- Membership mapping
- License tracking
- Delta sync (only update changed records)
- Orphan removal (users/groups deleted from AD)

## Testing Requirements

1. **Unit Tests:**
   - Test payload validation
   - Test user upsert logic
   - Test group upsert logic
   - Test membership linking

2. **Integration Tests:**
   - Test full sync from agent
   - Test delta sync
   - Test error handling
   - Test transaction rollback

3. **Performance Tests:**
   - Test with 1000+ users
   - Test with 500+ groups
   - Test concurrent sync attempts

## Monitoring & Alerting

1. **Metrics to track:**
   - Sync success/failure rate
   - Sync duration
   - Records processed per sync
   - Last successful sync timestamp

2. **Alerts to configure:**
   - Sync failed (critical)
   - Sync not run in >6 hours (warning)
   - Large data volume (>10,000 users)

## Security Considerations

1. **Authentication:**
   - Require valid API key
   - Scope to company
   - Rate limit to 60 requests/hour

2. **Authorization:**
   - Verify API key belongs to companyId
   - Ensure integration_id belongs to company

3. **Data Validation:**
   - Validate all email addresses
   - Sanitize all text fields
   - Validate date formats

4. **Audit Logging:**
   - Log all sync attempts
   - Log changes to users/groups
   - Track who/what made changes
