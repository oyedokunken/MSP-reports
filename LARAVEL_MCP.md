# Laravel MCP Integration Guide

## What is Laravel MCP?

Laravel MCP (Model Context Protocol) is a server implementation that exposes your Laravel application's context to AI assistants like Claude. It allows AI tools to understand your application's structure, interact with your database, run Artisan commands, and assist with development tasks - all while maintaining security and respecting your application's business logic.

**In simpler terms:** It's a bridge that lets AI assistants "see into" your Laravel application and help you build, debug, and maintain it more efficiently.

---

## Why Integrate Laravel MCP?

### For Development

**AI-Assisted Coding:**
- AI can see your models, relationships, and database schema
- Generate migrations based on your existing structure
- Suggest proper Eloquent queries that match your relationships
- Help write tests that understand your application context

**Debugging Help:**
- AI can inspect database state when debugging
- Understand why queries aren't working
- Suggest fixes based on actual data structure
- Trace through relationships to find issues

**Code Generation:**
- Generate controllers that follow your patterns
- Create form requests with validation rules matching your database
- Build factories and seeders that respect constraints
- Generate API resources that match your data structure

### For Operations

**Database Insights:**
- Query your production database safely (read-only mode)
- Understand data patterns and anomalies
- Help write complex reports or analytics queries
- Assist with data migrations and transformations

**Troubleshooting:**
- AI can help diagnose customer issues by inspecting their data
- Suggest configuration changes based on actual settings
- Help understand why integrations aren't syncing
- Trace through customer mapping logic with real data

**Documentation:**
- Generate documentation that reflects actual code
- Create API documentation from routes and controllers
- Build customer guides based on actual features
- Keep docs updated as code changes

### For Your Multi-Tenant Platform Specifically

**Tenant-Specific Debugging:**
- "Show me Territory Technology Solutions' integration settings"
- "Why isn't customer ACME Corp mapping correctly?"
- "List all failed report generations for Company X today"

**Integration Management:**
- "Show me all companies using Datto integration"
- "Which customers have unmapped IntegrationCustomers?"
- "Find all historical import jobs that are stuck"

**Report Troubleshooting:**
- "Show me the data being used for this customer's report"
- "Why didn't this report generate yesterday?"
- "List all customers with report schedules but no recent reports"

**Performance Analysis:**
- "Which companies have the most historical data?"
- "Find slow-running queries from yesterday"
- "Show me companies approaching retention limits"

---

## How Laravel MCP Works

### Architecture

**MCP Server (Laravel Package):**
- Runs alongside your Laravel application
- Exposes specific capabilities via MCP protocol
- Maintains security boundaries
- Scopes access based on configuration

**AI Assistant (Claude via Desktop/IDE):**
- Connects to MCP server
- Requests information or actions
- Receives structured responses
- Uses context to provide better assistance

**Communication Flow:**
```
Claude Desktop/IDE
    ↓ (MCP Protocol)
Laravel MCP Server
    ↓ (Application Context)
Your Laravel Application
    ↓ (Database Queries)
PostgreSQL/TimescaleDB
```

### Security Model

**Read vs Write Operations:**
- Read operations: Generally safe, expose information
- Write operations: Require explicit permission, can be disabled
- Configuration controls what's allowed

**Tenant Isolation:**
- MCP server respects your multi-tenant architecture
- Can scope to specific company
- Enforces same permission checks as your application

**Production Safety:**
- Can run in read-only mode (no database writes)
- Dangerous commands (drop tables, etc.) blocked by default
- Audit logging of all MCP operations

---

## Integration Steps

### Phase 1: Installation & Basic Setup

**When:** During Phase 1 (Foundation), after basic Laravel setup complete

**Time Investment:** 2-3 hours

**Steps:**

1. **Install Laravel MCP Package**
   ```bash
   composer require laravel-mcp/server --dev
   ```

2. **Publish Configuration**
   ```bash
   php artisan vendor:publish --tag=mcp-config
   ```

3. **Configure MCP Settings**
   
   In `config/mcp.php`:
   ```php
   return [
       'enabled' => env('MCP_ENABLED', true),
       
       'server' => [
           'host' => env('MCP_HOST', '127.0.0.1'),
           'port' => env('MCP_PORT', 3000),
       ],
       
       'security' => [
           'read_only' => env('MCP_READ_ONLY', false),
           'allowed_commands' => [
               'migrate:status',
               'route:list',
               'model:show',
               'db:show',
               // Add safe Artisan commands
           ],
           'blocked_commands' => [
               'migrate:fresh',
               'migrate:refresh',
               'db:wipe',
               // Block dangerous commands
           ],
       ],
       
       'database' => [
           'allow_queries' => true,
           'allow_writes' => env('MCP_ALLOW_DB_WRITES', false),
           'max_query_time' => 30, // seconds
       ],
       
       'multi_tenant' => [
           'enabled' => true,
           'scope_model' => App\Models\Company::class,
           'scope_column' => 'company_id',
       ],
   ];
   ```

4. **Add Environment Variables**
   
   In `.env`:
   ```
   MCP_ENABLED=true
   MCP_HOST=127.0.0.1
   MCP_PORT=3000
   MCP_READ_ONLY=false  # true for production
   MCP_ALLOW_DB_WRITES=false  # always false for production
   ```

5. **Start MCP Server**
   ```bash
   php artisan mcp:serve
   ```
   
   Or add to `docker-compose.yml`:
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

6. **Connect Claude Desktop**
   
   In Claude Desktop settings, add MCP server:
   ```json
   {
     "mcpServers": {
       "reporting-platform": {
         "command": "docker",
         "args": ["exec", "reporting-platform-app", "php", "artisan", "mcp:serve"],
         "description": "Multi-Tenant Reporting Platform"
       }
     }
   }
   ```

### Phase 2: Custom MCP Tools for Your Application

**When:** During Phase 2-3 (Integrations & Customer Mapping)

**Time Investment:** 4-6 hours

**Purpose:** Build application-specific MCP tools that understand your business logic.

**Create Custom MCP Tools:**

1. **Create Tools Directory**
   ```
   app/MCP/
   ├── Tools/
   │   ├── TenantTools.php
   │   ├── IntegrationTools.php
   │   ├── CustomerMappingTools.php
   │   └── ReportingTools.php
   └── MCPServiceProvider.php
   ```

2. **Tenant Management Tools**
   
   `app/MCP/Tools/TenantTools.php`:
   ```php
   class TenantTools
   {
       /**
        * Switch context to specific company for debugging
        */
       public function switchTenant(string $companyName): array
       {
           $company = Company::where('name', 'like', "%{$companyName}%")->first();
           
           if (!$company) {
               return ['error' => 'Company not found'];
           }
           
           // Set tenant context for subsequent queries
           session(['mcp_company_id' => $company->id]);
           
           return [
               'success' => true,
               'company' => $company->name,
               'id' => $company->id,
               'integrations_count' => $company->integrations()->count(),
               'customers_count' => $company->customers()->count(),
               'active_users' => $company->users()->where('status', 'active')->count(),
           ];
       }
       
       /**
        * Get comprehensive tenant overview
        */
       public function getTenantOverview(?int $companyId = null): array
       {
           $companyId = $companyId ?? session('mcp_company_id');
           $company = Company::findOrFail($companyId);
           
           return [
               'company' => [
                   'name' => $company->name,
                   'status' => $company->status,
                   'created' => $company->created_at,
               ],
               'integrations' => $company->integrations()->get()->map(fn($i) => [
                   'type' => $i->integration_type,
                   'status' => $i->status,
                   'last_sync' => $i->last_sync_at,
                   'customers' => $i->integrationCustomers()->count(),
               ]),
               'customers' => [
                   'total' => $company->customers()->count(),
                   'mapped' => $company->customers()->has('integrationCustomers')->count(),
                   'unmapped' => IntegrationCustomer::whereHas('integration', function($q) use ($company) {
                       $q->where('company_id', $company->id);
                   })->doesntHave('customer')->count(),
               ],
               'reports' => [
                   'total_schedules' => $company->reportSchedules()->count(),
                   'active_schedules' => $company->reportSchedules()->where('active', true)->count(),
                   'generated_today' => $company->generatedReports()->whereDate('created_at', today())->count(),
                   'failed_today' => $company->generatedReports()
                       ->whereDate('created_at', today())
                       ->where('status', 'failed')
                       ->count(),
               ],
           ];
       }
   }
   ```

3. **Integration Debugging Tools**
   
   `app/MCP/Tools/IntegrationTools.php`:
   ```php
   class IntegrationTools
   {
       /**
        * Diagnose integration sync issues
        */
       public function diagnoseIntegration(int $integrationId): array
       {
           $integration = Integration::findOrFail($integrationId);
           
           return [
               'integration' => [
                   'id' => $integration->id,
                   'type' => $integration->integration_type,
                   'status' => $integration->status,
                   'last_sync' => $integration->last_sync_at,
                   'last_error' => $integration->error_message,
               ],
               'sync_history' => SyncLog::where('integration_id', $integrationId)
                   ->latest()
                   ->limit(10)
                   ->get()
                   ->map(fn($log) => [
                       'started' => $log->started_at,
                       'status' => $log->status,
                       'records_synced' => $log->records_synced,
                       'errors' => $log->error_messages,
                   ]),
               'rate_limiting' => $this->getRateLimitStatus($integration),
               'historical_import' => HistoricalImportJob::where('integration_id', $integrationId)
                   ->latest()
                   ->first()
                   ?->only(['status', 'progress_percentage', 'records_imported']),
               'health_check' => $this->performHealthCheck($integration),
           ];
       }
       
       /**
        * Find stuck historical imports
        */
       public function findStuckImports(): array
       {
           $stuck = HistoricalImportJob::where('status', 'running')
               ->where('updated_at', '<', now()->subHours(2))
               ->with('integration.company')
               ->get();
               
           return $stuck->map(fn($job) => [
               'company' => $job->integration->company->name,
               'integration' => $job->integration->integration_type,
               'started' => $job->started_at,
               'progress' => $job->progress_percentage . '%',
               'last_update' => $job->updated_at->diffForHumans(),
               'recommendation' => 'Consider restarting this import job',
           ])->toArray();
       }
   }
   ```

4. **Customer Mapping Tools**
   
   `app/MCP/Tools/CustomerMappingTools.php`:
   ```php
   class CustomerMappingTools
   {
       /**
        * Show unmapped customers with suggestions
        */
       public function getUnmappedCustomers(int $companyId, int $limit = 20): array
       {
           $unmapped = IntegrationCustomer::whereHas('integration', function($q) use ($companyId) {
                   $q->where('company_id', $companyId);
               })
               ->doesntHave('customer')
               ->limit($limit)
               ->get();
               
           return $unmapped->map(function($ic) use ($companyId) {
               $suggestions = $this->findSimilarCustomers($ic->customer_name, $companyId);
               
               return [
                   'integration_customer' => $ic->customer_name,
                   'integration_type' => $ic->integration->integration_type,
                   'external_id' => $ic->external_id,
                   'suggestions' => $suggestions,
               ];
           })->toArray();
       }
       
       /**
        * Analyze mapping confidence for a customer
        */
       public function analyzeMappingConfidence(int $customerMappingId): array
       {
           $mapping = CustomerMapping::with(['customer', 'integrationCustomer'])
               ->findOrFail($customerMappingId);
               
           return [
               'mapping' => [
                   'type' => $mapping->mapping_type,
                   'confidence' => $mapping->confidence_score,
                   'mapped_by' => $mapping->mappedByUser?->name ?? 'System',
                   'created' => $mapping->created_at,
               ],
               'customer_name' => $mapping->customer->canonical_name,
               'integration_name' => $mapping->integrationCustomer->customer_name,
               'similarity_analysis' => [
                   'exact_match' => $mapping->customer->canonical_name === $mapping->integrationCustomer->customer_name,
                   'case_insensitive_match' => strcasecmp($mapping->customer->canonical_name, $mapping->integrationCustomer->customer_name) === 0,
                   'levenshtein_distance' => levenshtein($mapping->customer->canonical_name, $mapping->integrationCustomer->customer_name),
               ],
               'recommendation' => $this->getMappingRecommendation($mapping),
           ];
       }
   }
   ```

5. **Reporting Tools**
   
   `app/MCP/Tools/ReportingTools.php`:
   ```php
   class ReportingTools
   {
       /**
        * Debug why a report didn't generate
        */
       public function debugMissingReport(int $scheduleId, string $expectedDate): array
       {
           $schedule = ReportSchedule::with(['customer', 'company'])->findOrFail($scheduleId);
           
           // Check if report was attempted
           $report = GeneratedReport::where('report_schedule_id', $scheduleId)
               ->whereDate('created_at', $expectedDate)
               ->first();
               
           if ($report) {
               return [
                   'status' => 'Report was generated',
                   'result' => $report->status,
                   'error' => $report->error_message,
                   'file_path' => $report->file_path,
                   'delivered' => $report->delivered_status,
               ];
           }
           
           // Check if schedule was due
           $nextScheduled = $schedule->next_scheduled_timestamp;
           $wasScheduled = Carbon::parse($nextScheduled)->isSameDay($expectedDate);
           
           // Check data availability
           $dataAvailability = $this->checkDataAvailability($schedule);
           
           return [
               'status' => 'Report was not generated',
               'schedule' => [
                   'active' => $schedule->active,
                   'frequency' => $schedule->schedule_config['frequency'],
                   'next_scheduled' => $nextScheduled,
                   'was_due_on_date' => $wasScheduled,
               ],
               'data_availability' => $dataAvailability,
               'possible_reasons' => $this->identifyReasons($schedule, $dataAvailability),
           ];
       }
       
       /**
        * Get report generation statistics
        */
       public function getReportStats(int $companyId, int $days = 7): array
       {
           $reports = GeneratedReport::whereHas('company', function($q) use ($companyId) {
                   $q->where('id', $companyId);
               })
               ->where('created_at', '>=', now()->subDays($days))
               ->get();
               
           return [
               'total' => $reports->count(),
               'by_status' => $reports->countBy('status'),
               'by_report_type' => $reports->countBy('report_schedule.report_type'),
               'delivery_success_rate' => $this->calculateDeliveryRate($reports),
               'average_generation_time' => $this->calculateAverageTime($reports),
               'failed_reports' => $reports->where('status', 'failed')->map(fn($r) => [
                   'customer' => $r->customer->canonical_name,
                   'report_type' => $r->reportSchedule->report_type,
                   'error' => $r->error_message,
                   'timestamp' => $r->created_at,
               ]),
           ];
       }
   }
   ```

6. **Register MCP Tools**
   
   `app/MCP/MCPServiceProvider.php`:
   ```php
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

7. **Register Service Provider**
   
   In `config/app.php`:
   ```php
   'providers' => [
       // ...
       App\MCP\MCPServiceProvider::class,
   ],
   ```

### Phase 3: Production Configuration

**When:** Before deploying to first customer

**Time Investment:** 1-2 hours

**Production Safety:**

1. **Read-Only Mode**
   
   In production `.env`:
   ```
   MCP_ENABLED=true
   MCP_READ_ONLY=true
   MCP_ALLOW_DB_WRITES=false
   ```

2. **Audit Logging**
   
   Create middleware to log all MCP operations:
   ```php
   class LogMCPOperations
   {
       public function handle($request, $next)
       {
           $response = $next($request);
           
           AuditLog::create([
               'company_id' => session('mcp_company_id'),
               'event_type' => 'mcp_operation',
               'actor_type' => 'System',
               'actor_id' => null,
               'target_type' => 'MCP',
               'target_id' => null,
               'event_data' => [
                   'tool' => $request->input('tool'),
                   'method' => $request->input('method'),
                   'parameters' => $request->input('parameters'),
               ],
               'ip_address' => $request->ip(),
           ]);
           
           return $response;
       }
   }
   ```

3. **Access Control**
   
   Only enable MCP for authorized developers:
   ```php
   // config/mcp.php
   'authorization' => [
       'enabled' => true,
       'allowed_ips' => [
           '127.0.0.1',
           // Add developer IPs
       ],
       'require_auth_token' => env('MCP_REQUIRE_TOKEN', true),
   ],
   ```

---

## Usage Examples

### Development Scenarios

**Scenario 1: Understanding Customer Mapping Issue**

*You:* "Why isn't 'ACME Corporation' mapping to 'ACME Corp'?"

*Claude (via MCP):*
1. Switches tenant context to the company
2. Queries unmapped customers
3. Runs similarity analysis
4. Checks auto-match threshold settings
5. Provides diagnosis and fix suggestion

**Scenario 2: Debugging Integration Sync**

*You:* "Datto integration for TTS hasn't synced in 6 hours, what's wrong?"

*Claude (via MCP):*
1. Finds TTS company
2. Queries integration status
3. Checks sync logs for errors
4. Examines rate limit status
5. Identifies API credential expiry
6. Suggests re-authentication steps

**Scenario 3: Report Not Generated**

*You:* "Customer says they didn't receive yesterday's report"

*Claude (via MCP):*
1. Finds customer and report schedule
2. Checks if generation was attempted
3. Examines data availability
4. Identifies missing historical data
5. Suggests triggering historical backfill
6. Can manually trigger report generation

### Operations Scenarios

**Scenario 1: Customer Support**

*Customer Support:* "Customer X says their integrations aren't working"

*You ask Claude:* "Show me integration status for Customer X"

*Claude (via MCP):*
- Provides comprehensive integration overview
- Shows sync success rates
- Identifies failing integration
- Suggests troubleshooting steps

**Scenario 2: Performance Investigation**

*You:* "The platform seems slow today"

*Claude (via MCP):*
- Queries slow query log
- Identifies companies with large historical datasets
- Shows current queue depths
- Recommends optimization targets

**Scenario 3: Data Quality Check**

*You:* "Are there any customers with unmapped integration data?"

*Claude (via MCP):*
- Scans all companies
- Lists unmapped customers per company
- Provides mapping suggestions
- Can generate bulk mapping tasks

---

## Best Practices

### Security

1. **Never Enable Write Mode in Production**
   - Read operations only
   - All modifications through normal application flow
   - MCP for inspection and diagnosis only

2. **Audit Everything**
   - Log all MCP operations
   - Track which tools are used
   - Monitor for unusual access patterns

3. **IP Whitelist**
   - Restrict to known developer IPs
   - VPN-only access for remote developers
   - Disable MCP entirely if not needed

4. **Tenant Isolation**
   - Always scope queries to specific company
   - Never expose cross-tenant data
   - Validate company context before operations

### Development Workflow

1. **Use MCP for Understanding**
   - Explore data structures
   - Understand relationships
   - Debug complex queries
   - Not for routine coding

2. **Document MCP Tools**
   - Each tool has clear purpose
   - Examples of usage
   - Expected parameters
   - Return value structure

3. **Test MCP Tools**
   - Unit tests for custom tools
   - Ensure multi-tenant scoping works
   - Validate security restrictions
   - Test error handling

### Team Collaboration

1. **Shared Tool Library**
   - Team adds useful MCP tools as they find needs
   - Code review for new tools
   - Documentation for each tool

2. **Common Debugging Patterns**
   - Document frequent MCP use cases
   - Share Claude conversations that solved issues
   - Build team knowledge base

---

## Development Timeline Integration

### Phase 1 (Weeks 1-3): Basic MCP Setup
- Install Laravel MCP package
- Configure basic settings
- Connect Claude Desktop
- Test basic model inspection
- **Time: 2-3 hours**

### Phase 2 (Weeks 4-7): Integration-Specific Tools
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

### Pre-Production (Week 16): Security Hardening
- Enable read-only mode
- Configure audit logging
- Set up IP whitelist
- Test all security restrictions
- **Time: 1-2 hours**

**Total Investment: 12-18 hours over project lifecycle**

**Value Return:**
- Faster debugging (save 20+ hours)
- Better code understanding (save 15+ hours)
- Quicker customer support (save 10+ hours)
- AI-assisted development (save 30+ hours)

**Net Benefit: +50-60 hours saved**

---

## Monitoring MCP Usage

### Metrics to Track

1. **Usage Statistics**
   - Which tools are used most?
   - Which developers use MCP?
   - What problems does it solve?

2. **Performance Impact**
   - MCP query execution time
   - Impact on application performance
   - Resource usage

3. **Security Events**
   - Failed authorization attempts
   - Unusual access patterns
   - Tools being used inappropriately

### Alerts

**Set up alerts for:**
- MCP access from unauthorized IPs
- Attempts to use write operations in production
- Excessive query volume from MCP
- MCP errors or failures

---

## Future Enhancements

### Phase 2+ Features

**AI-Assisted Customer Onboarding:**
- MCP tool to guide new customer setup
- Generate optimal settings based on customer type
- Suggest integration configurations

**Automated Testing:**
- MCP tool to generate test data
- Create realistic multi-tenant scenarios
- Validate business logic

**Performance Optimization:**
- MCP tool to identify N+1 queries
- Suggest index additions
- Analyze query patterns

**Documentation Generation:**
- MCP tool to generate API docs from routes
- Create customer guides from features
- Build changelog from git history

---

## Summary

Laravel MCP integration provides:

✅ **Development Acceleration:** AI understands your application context
✅ **Debugging Efficiency:** Quickly diagnose issues with real data
✅ **Customer Support:** Rapidly troubleshoot customer issues
✅ **Operations Intelligence:** Understand system behavior and performance
✅ **Team Productivity:** Shared tooling for common tasks

**Investment:** 12-18 hours over project
**Return:** 50-60+ hours saved, better code quality, faster customer support

**Recommendation:** Integrate MCP from Phase 1, build custom tools as you discover needs, lock down security before production deployment.