---
trigger: always_on
---

# Laravel MCP Integration - Windsurf Rules

## Overview
We use Laravel MCP (Model Context Protocol) to allow AI assistants to understand our application context. This enables AI-assisted debugging, development, and operations support for our multi-tenant reporting platform.

## Purpose
- AI can inspect models, relationships, database schema, and data
- Debug customer-specific issues: "Why isn't ACME Corp mapping?"
- Troubleshoot integrations: "Show me TTS's Datto sync status"
- Investigate report failures with real data context
- Generate code that matches our patterns and relationships
- Support customer issues by inspecting their actual data

## Installation & Configuration

### Phase 1 Setup (2-3 hours)
```bash
composer require laravel-mcp/server --dev
php artisan vendor:publish --tag=mcp-config
```

### Environment Configuration
```env
MCP_ENABLED=true
MCP_HOST=127.0.0.1
MCP_PORT=3000
MCP_READ_ONLY=false  # true in production
MCP_ALLOW_DB_WRITES=false  # ALWAYS false in production
```

### Docker Integration
Add to docker-compose.yml:
```yaml
mcp:
  build: .
  command: php artisan mcp:serve
  ports:
    - "3000:3000"
  depends_on:
    - db
    - redis
```

### Core Config (config/mcp.php)
```php
return [
    'enabled' => env('MCP_ENABLED', true),
    'security' => [
        'read_only' => env('MCP_READ_ONLY', false),
        'allowed_commands' => ['migrate:status', 'route:list', 'model:show', 'db:show'],
        'blocked_commands' => ['migrate:fresh', 'migrate:refresh', 'db:wipe'],
    ],
    'database' => [
        'allow_queries' => true,
        'allow_writes' => env('MCP_ALLOW_DB_WRITES', false),
        'max_query_time' => 30,
    ],
    'multi_tenant' => [
        'enabled' => true,
        'scope_model' => App\Models\Company::class,
        'scope_column' => 'company_id',
    ],
];
```

## Custom MCP Tools Structure

### Directory Layout
```
app/MCP/
├── Tools/
│   ├── TenantTools.php
│   ├── IntegrationTools.php
│   ├── CustomerMappingTools.php
│   └── ReportingTools.php
└── MCPServiceProvider.php
```

### TenantTools - Company Context Management
**Purpose:** Switch between companies, get tenant overviews

**Key Methods:**
- `switchTenant(string $companyName)` - Set company context for debugging
- `getTenantOverview(?int $companyId)` - Comprehensive company status including integrations, customers, reports

**Returns:** Integration counts, customer mapping stats, report generation stats, active users

### IntegrationTools - Sync & Import Debugging
**Purpose:** Diagnose integration issues, find stuck imports

**Key Methods:**
- `diagnoseIntegration(int $integrationId)` - Full sync history, rate limit status, health check
- `findStuckImports()` - List historical imports running >2 hours with recommendations
- `getRateLimitStatus(Integration $integration)` - Current rate limit usage
- `performHealthCheck(Integration $integration)` - Test API connectivity

**Returns:** Sync logs, error messages, rate limiting metrics, import progress

### CustomerMappingTools - Mapping Analysis
**Purpose:** Debug mapping issues, analyze confidence scores

**Key Methods:**
- `getUnmappedCustomers(int $companyId, int $limit)` - Show unmapped with similarity suggestions
- `analyzeMappingConfidence(int $customerMappingId)` - Detailed mapping analysis with Levenshtein distance
- `findSimilarCustomers(string $name, int $companyId)` - Get auto-match suggestions

**Returns:** Similarity scores, mapping recommendations, confidence analysis

### ReportingTools - Report Generation Debugging
**Purpose:** Diagnose missing reports, analyze generation stats

**Key Methods:**
- `debugMissingReport(int $scheduleId, string $expectedDate)` - Why didn't report generate?
- `getReportStats(int $companyId, int $days)` - Success rates, failures, performance
- `checkDataAvailability(ReportSchedule $schedule)` - Verify required data exists
- `calculateDeliveryRate(Collection $reports)` - Email delivery success metrics

**Returns:** Schedule status, data availability, failure reasons, statistics

## Service Provider Registration

```php
// app/MCP/MCPServiceProvider.php
namespace App\MCP;

use Illuminate\Support\ServiceProvider;
use LaravelMCP\MCPServer;

class MCPServiceProvider extends ServiceProvider
{
    public function boot()
    {
        MCPServer::registerTool('tenant', TenantTools::class);
        MCPServer::registerTool('integration', IntegrationTools::class);
        MCPServer::registerTool('mapping', CustomerMappingTools::class);
        MCPServer::registerTool('reporting', ReportingTools::class);
    }
}
```

Register in config/app.php providers array.

## Security Requirements

### Production Configuration
**CRITICAL:** Always read-only in production
```env
MCP_READ_ONLY=true
MCP_ALLOW_DB_WRITES=false  # Never true in production
```

### Audit Logging
Log all MCP operations to AuditLog:
```php
AuditLog::create([
    'company_id' => session('mcp_company_id'),
    'event_type' => 'mcp_operation',
    'actor_type' => 'System',
    'event_data' => [
        'tool' => $request->input('tool'),
        'method' => $request->input('method'),
        'parameters' => $request->input('parameters'),
    ],
    'ip_address' => $request->ip(),
]);
```

### Access Control
- IP whitelist for authorized developers only
- VPN-required for remote access
- Auth token validation
- Disable MCP entirely on production if not needed for support

### Tenant Isolation
- Always scope queries to specific company
- Never expose cross-tenant data
- Validate company_id context before operations
- Use session('mcp_company_id') for context

## Development Workflow

### Using MCP During Development
1. **Understanding Data:** "Show me all customers for TTS"
2. **Debugging Mappings:** "Why isn't ACME Corp mapping?"
3. **Checking Syncs:** "When did Datto last sync for company X?"
4. **Report Issues:** "Why didn't customer Y get their report yesterday?"
5. **Performance:** "Which companies have the most historical data?"

### Tool Development Pattern
1. Identify common debugging need
2. Create method in appropriate tool class
3. Ensure tenant scoping
4. Return structured, actionable data
5. Document expected parameters
6. Write unit test validating multi-tenant isolation

### Best Practices
- MCP for inspection and diagnosis only, not routine coding
- Document each tool with purpose and examples
- Test all custom tools for tenant isolation
- Never use MCP for data modification (read-only)
- Log all operations for audit trail

## Common Use Cases

### Customer Support Scenario
**Issue:** "Integration not syncing for customer"

**MCP Workflow:**
1. `switchTenant("Customer Name")` - Set context
2. `getTenantOverview()` - See all integrations
3. `diagnoseIntegration(integrationId)` - Check specific integration
4. Review sync logs, rate limits, errors
5. Provide diagnosis and fix to customer

### Development Scenario
**Issue:** "Need to understand customer mapping logic"

**MCP Workflow:**
1. `switchTenant("Test Company")` - Set context
2. `getUnmappedCustomers()` - See unmapped list
3. `analyzeMappingConfidence(mappingId)` - Check specific mapping
4. Understand similarity algorithms
5. Improve auto-match logic

### Operations Scenario
**Issue:** "Reports not generating for some customers"

**MCP Workflow:**
1. `getReportStats(companyId, 7)` - Last 7 days stats
2. Identify failed reports
3. `debugMissingReport(scheduleId, date)` - For each failure
4. Check data availability, schedule status
5. Fix underlying issues (missing data, schedule misconfigured)

## Timeline Integration

### Phase 1 (Weeks 1-3): Basic Setup
- Install package
- Configure settings
- Connect Claude Desktop
- Test basic model inspection
- **Time: 2-3 hours**

### Phase 2 (Weeks 4-7): Integration Tools
- Build TenantTools
- Build IntegrationTools
- Test with Datto integration
- **Time: 3-4 hours**

### Phase 3 (Weeks 8-9): Mapping Tools
- Build CustomerMappingTools
- Test mapping diagnostics
- **Time: 2-3 hours**

### Phase 4 (Weeks 10-13): Reporting Tools
- Build ReportingTools
- Add data availability checks
- **Time: 2-3 hours**

### Pre-Production (Week 16): Security
- Enable read-only mode
- Configure audit logging
- Set up IP whitelist
- Test security restrictions
- **Time: 1-2 hours**

**Total: 12-18 hours investment, 50-60+ hours saved**

## Monitoring & Alerts

### Track
- Tool usage statistics (which tools used most?)
- Performance impact (query execution times)
- Security events (unauthorized access attempts)
- Error rates

### Alert On
- MCP access from unauthorized IPs
- Write operation attempts in production
- Excessive query volume
- Failed authentication

## Code Standards for MCP Tools

### Method Signature
```php
public function methodName(RequiredType $param, ?OptionalType $optional = null): array
```

### Return Structure
Always return structured arrays with clear keys:
```php
return [
    'success' => true,
    'data' => [...],
    'metadata' => [...],
    'recommendations' => [...],
];
```

### Error Handling
```php
try {
    // Tool logic
} catch (Exception $e) {
    return [
        'error' => true,
        'message' => $e->getMessage(),
        'context' => [...],
    ];
}
```

### Tenant Scoping
Always scope to company:
```php
$companyId = $companyId ?? session('mcp_company_id');
if (!$companyId) {
    return ['error' => 'No tenant context set'];
}
```

## Integration with Existing Code

### Models
MCP automatically exposes:
- All model attributes
- Relationships (can traverse)
- Scopes and methods
- Cast definitions

### Queries
MCP can run Eloquent queries respecting:
- Multi-tenant scoping
- Global scopes
- Soft deletes
- Eager loading

### Artisan Commands
MCP can execute allowed commands:
- `model:show` to inspect models
- `route:list` to see routes
- `migrate:status` to check migrations
- Custom commands if whitelisted

## Remember
- **Security First:** Read-only in production, always
- **Tenant Isolation:** Every query scoped to company
- **Audit Everything:** Log all MCP operations
- **Purpose:** Inspection and diagnosis, not routine coding
- **Value:** Faster debugging, better support, AI-assisted development