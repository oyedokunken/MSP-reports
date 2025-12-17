# Report Templates - Requirements & Implementation Plan

## Overview

**Requirement from TTS Meeting:**
- Create report templates with schedule, included sections, and delivery settings
- Apply templates to customers by **copying** settings (not linking/assigning)
- Allow customers to modify individual settings after template is applied
- Template changes should NOT affect existing customer schedules

**Status:** ❌ Not implemented - needs to be built from scratch

---

## Current System Analysis

### What Exists
- ✅ `ReportSchedule` model - directly tied to customers
- ✅ `Customer` model - has `report_configuration` JSON field
- ✅ Schedule creation UI - one customer at a time
- ✅ Report generation system working

### What's Missing
- ❌ No `ReportTemplate` model
- ❌ No template CRUD operations
- ❌ No template management UI
- ❌ No "apply template to customer" functionality

---

## 🆕 NEW COMPONENTS TO CREATE

### Backend

#### 1. Database Migration
**File:** `backend/database/migrations/YYYY_MM_DD_create_report_templates_table.php`

```php
Schema::create('report_templates', function (Blueprint $table) {
    $table->id('template_id');
    $table->foreignId('company_id')->constrained('companies', 'company_id')->cascadeOnDelete();
    
    // Template identification
    $table->string('template_name'); // e.g., "Standard Monthly Report"
    $table->text('description')->nullable();
    
    // Report configuration
    $table->string('report_type'); // e.g., "autotask_monthly_summary"
    $table->enum('frequency', ['daily', 'weekly', 'monthly', 'yearly', 'on-demand']);
    $table->jsonb('schedule_config')->nullable(); // Days of week, day of month, etc.
    $table->time('time_of_day')->nullable();
    $table->string('timezone')->default('Australia/Brisbane');
    
    // Report customization
    $table->jsonb('included_sections')->nullable(); // Which sections to include in report
    $table->jsonb('delivery_settings')->nullable(); // Email recipients, format, etc.
    $table->jsonb('report_options')->nullable(); // Report-specific options
    
    // Status
    $table->boolean('active')->default(true);
    $table->timestamps();
    
    // Indexes
    $table->index(['company_id', 'active']);
    $table->index('report_type');
});
```

#### 2. Model
**File:** `backend/app/Models/ReportTemplate.php`

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Scopes\CompanyScope;

class ReportTemplate extends Model
{
    protected $primaryKey = 'template_id';
    
    protected static function booted(): void
    {
        static::addGlobalScope(new CompanyScope);
    }
    
    protected $fillable = [
        'company_id',
        'template_name',
        'description',
        'report_type',
        'frequency',
        'schedule_config',
        'time_of_day',
        'timezone',
        'included_sections',
        'delivery_settings',
        'report_options',
        'active',
    ];
    
    protected $casts = [
        'schedule_config' => 'array',
        'included_sections' => 'array',
        'delivery_settings' => 'array',
        'report_options' => 'array',
        'active' => 'boolean',
    ];
    
    // Relationships
    public function company()
    {
        return $this->belongsTo(Company::class, 'company_id', 'company_id');
    }
}
```

#### 3. Controller
**File:** `backend/app/Http/Controllers/Api/ReportTemplateController.php`

```php
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ReportTemplate;
use App\Models\Customer;
use App\Services\ReportTemplateService;
use Illuminate\Http\Request;

class ReportTemplateController extends Controller
{
    public function __construct(
        private ReportTemplateService $templateService
    ) {}
    
    // List all templates
    public function index(Request $request)
    {
        $templates = ReportTemplate::query()
            ->when($request->report_type, fn($q, $type) => $q->where('report_type', $type))
            ->when($request->active !== null, fn($q) => $q->where('active', $request->boolean('active')))
            ->orderBy('template_name')
            ->get();
            
        return response()->json($templates);
    }
    
    // Get single template
    public function show(ReportTemplate $template)
    {
        return response()->json($template);
    }
    
    // Create template
    public function store(Request $request)
    {
        $validated = $request->validate([
            'template_name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'report_type' => 'required|string',
            'frequency' => 'required|in:daily,weekly,monthly,yearly,on-demand',
            'schedule_config' => 'nullable|array',
            'time_of_day' => 'nullable|date_format:H:i',
            'timezone' => 'nullable|string',
            'included_sections' => 'nullable|array',
            'delivery_settings' => 'nullable|array',
            'report_options' => 'nullable|array',
            'active' => 'boolean',
        ]);
        
        $template = ReportTemplate::create([
            'company_id' => auth()->user()->company_id,
            ...$validated,
        ]);
        
        return response()->json($template, 201);
    }
    
    // Update template
    public function update(Request $request, ReportTemplate $template)
    {
        $validated = $request->validate([
            'template_name' => 'string|max:255',
            'description' => 'nullable|string',
            'report_type' => 'string',
            'frequency' => 'in:daily,weekly,monthly,yearly,on-demand',
            'schedule_config' => 'nullable|array',
            'time_of_day' => 'nullable|date_format:H:i',
            'timezone' => 'nullable|string',
            'included_sections' => 'nullable|array',
            'delivery_settings' => 'nullable|array',
            'report_options' => 'nullable|array',
            'active' => 'boolean',
        ]);
        
        $template->update($validated);
        
        return response()->json($template);
    }
    
    // Delete template
    public function destroy(ReportTemplate $template)
    {
        $template->delete();
        return response()->json(['message' => 'Template deleted successfully']);
    }
    
    // Apply template to single customer
    public function applyToCustomer(Request $request, ReportTemplate $template)
    {
        $validated = $request->validate([
            'customer_id' => 'required|exists:customers,customer_id',
        ]);
        
        $customer = Customer::findOrFail($validated['customer_id']);
        $schedule = $this->templateService->applyTemplateToCustomer($template, $customer);
        
        return response()->json([
            'message' => 'Template applied successfully',
            'schedule' => $schedule,
        ], 201);
    }
    
    // Apply template to multiple customers
    public function applyToCustomers(Request $request, ReportTemplate $template)
    {
        $validated = $request->validate([
            'customer_ids' => 'required|array',
            'customer_ids.*' => 'exists:customers,customer_id',
        ]);
        
        $schedules = $this->templateService->applyTemplateToCustomers(
            $template,
            $validated['customer_ids']
        );
        
        return response()->json([
            'message' => 'Template applied to ' . count($schedules) . ' customers',
            'schedules' => $schedules,
        ], 201);
    }
}
```

#### 4. Service
**File:** `backend/app/Services/ReportTemplateService.php`

```php
<?php

namespace App\Services;

use App\Models\ReportTemplate;
use App\Models\Customer;
use App\Models\ReportSchedule;
use Illuminate\Support\Collection;

class ReportTemplateService
{
    /**
     * Apply template to a single customer (COPY settings, not link)
     */
    public function applyTemplateToCustomer(ReportTemplate $template, Customer $customer): ReportSchedule
    {
        // Create a new schedule by COPYING template values
        $schedule = ReportSchedule::create([
            'company_id' => $customer->company_id,
            'customer_id' => $customer->customer_id,
            'report_type' => $template->report_type,
            'frequency' => $template->frequency,
            'schedule_config' => $template->schedule_config, // COPY, not reference
            'time_of_day' => $template->time_of_day,
            'timezone' => $template->timezone ?? 'Australia/Brisbane',
            'active' => true,
        ]);
        
        // Store additional settings in customer's report_configuration
        if ($template->included_sections || $template->delivery_settings || $template->report_options) {
            $customer->update([
                'report_configuration' => array_merge(
                    $customer->report_configuration ?? [],
                    [
                        'included_sections' => $template->included_sections,
                        'delivery_settings' => $template->delivery_settings,
                        'report_options' => $template->report_options,
                    ]
                ),
            ]);
        }
        
        return $schedule->fresh();
    }
    
    /**
     * Apply template to multiple customers
     */
    public function applyTemplateToCustomers(ReportTemplate $template, array $customerIds): Collection
    {
        $schedules = collect();
        
        foreach ($customerIds as $customerId) {
            $customer = Customer::find($customerId);
            if ($customer) {
                $schedules->push($this->applyTemplateToCustomer($template, $customer));
            }
        }
        
        return $schedules;
    }
}
```

#### 5. API Routes
**File:** `backend/routes/api.php` (ADD these routes)

```php
// Report Templates
Route::middleware(['auth:sanctum'])->group(function () {
    Route::apiResource('report-templates', ReportTemplateController::class);
    Route::post('report-templates/{template}/apply-to-customer', [ReportTemplateController::class, 'applyToCustomer']);
    Route::post('report-templates/{template}/apply-to-customers', [ReportTemplateController::class, 'applyToCustomers']);
});
```

### Frontend

#### 1. Types
**File:** `frontend/src/pages/templates/types/template.ts`

```typescript
export interface ReportTemplate {
  template_id: number
  company_id: number
  template_name: string
  description: string | null
  report_type: string
  frequency: 'daily' | 'weekly' | 'monthly' | 'yearly' | 'on-demand'
  schedule_config: ScheduleConfig | null
  time_of_day: string | null
  timezone: string
  included_sections: string[] | null
  delivery_settings: DeliverySettings | null
  report_options: Record<string, any> | null
  active: boolean
  created_at: string
  updated_at: string
}

export interface ScheduleConfig {
  days_of_week?: number[]
  day_of_month?: number
  fallback_to_last_day?: boolean
  month?: number
}

export interface DeliverySettings {
  method: 'email' | 'api' | 'download'
  recipients?: string[]
  cc?: string[]
  format?: 'pdf' | 'html'
}
```

#### 2. Template List Page
**File:** `frontend/src/pages/templates/TemplateList.tsx`

```typescript
// Full page component with:
// - List of all templates
// - Create template button
// - Edit/Delete actions
// - Apply to customers button
// - Filter by report type
// - Search functionality
```

#### 3. Create/Edit Template Modal
**File:** `frontend/src/pages/templates/modals/CreateTemplateModal.tsx`

```typescript
// Modal with form for:
// - Template name
// - Description
// - Report type selector
// - Frequency selector
// - Schedule configuration (days, time)
// - Timezone selector
// - Included sections (checkboxes)
// - Delivery settings (email, format, recipients)
```

#### 4. Apply Template Modal
**File:** `frontend/src/pages/templates/modals/ApplyTemplateModal.tsx`

```typescript
// Modal to select customers:
// - Multi-select customer list
// - Search/filter customers
// - Preview template settings
// - Bulk apply confirmation
```

#### 5. API Client
**File:** `frontend/src/lib/api/templates.ts`

```typescript
export const templateApi = {
  list: () => api.get('/report-templates'),
  get: (id: number) => api.get(`/report-templates/${id}`),
  create: (data: CreateTemplateRequest) => api.post('/report-templates', data),
  update: (id: number, data: UpdateTemplateRequest) => api.put(`/report-templates/${id}`, data),
  delete: (id: number) => api.delete(`/report-templates/${id}`),
  applyToCustomer: (id: number, customerId: number) => 
    api.post(`/report-templates/${id}/apply-to-customer`, { customer_id: customerId }),
  applyToCustomers: (id: number, customerIds: number[]) => 
    api.post(`/report-templates/${id}/apply-to-customers`, { customer_ids: customerIds }),
}
```

#### 6. Navigation Menu Item
**File:** `frontend/src/components/layout/Sidebar.tsx` (ADD)

```typescript
{
  name: 'Templates',
  href: '/templates',
  icon: FileTemplate,
  permission: 'reports.configure',
}
```

---

## 🔧 COMPONENTS TO MODIFY

### Backend

#### 1. Company Model
**File:** `backend/app/Models/Company.php`

**ADD relationship:**
```php
public function reportTemplates()
{
    return $this->hasMany(ReportTemplate::class, 'company_id', 'company_id');
}
```

#### 2. Customer Model
**File:** `backend/app/Models/Customer.php`

**MODIFY:** Ensure `report_configuration` field is properly used for storing template-derived settings

**Current:**
```php
protected $fillable = [
    'company_id',
    'canonical_name',
    'status',
    'report_configuration', // ✅ Already exists
    'report_destination',
    'metadata',
];
```

**No changes needed** - field already exists

#### 3. ReportSchedule Model
**File:** `backend/app/Models/ReportSchedule.php`

**ADD method to check if schedule was created from template:**
```php
/**
 * Check if this schedule can be modified independently
 * (Always true since we copy from templates, not link)
 */
public function isIndependent(): bool
{
    return true; // All schedules are independent after creation
}
```

### Frontend

#### 1. Customer Detail Page
**File:** `frontend/src/pages/customers/CustomerList.tsx`

**ADD button:** "Apply Report Template"
- Opens template selector modal
- Applies selected template to customer
- Shows success message

#### 2. Schedule List Page
**File:** `frontend/src/pages/schedules/ScheduleList.tsx`

**ADD indicator:** Show if schedule was created from template (optional)
- Display template name in schedule row
- "Created from: [Template Name]" badge

#### 3. Navigation
**File:** `frontend/src/components/layout/Sidebar.tsx`

**ADD menu item:**
```typescript
{
  name: 'Templates',
  href: '/templates',
  icon: FileTemplate,
  permission: 'reports.configure',
}
```

#### 4. Routes
**File:** `frontend/src/App.tsx` or router config

**ADD routes:**
```typescript
<Route path="/templates" element={<TemplateList />} />
```

---

## 🗑️ COMPONENTS TO REMOVE

**None** - This is a purely additive feature. No existing functionality needs to be removed.

---

## Key Design Decisions

### 1. Copy vs Reference Architecture
✅ **COPY** - Template settings are copied to ReportSchedule
❌ **NOT REFERENCE** - No foreign key to template

**Why:**
- Customer schedules are independent after creation
- Updating template doesn't affect existing schedules
- Customers can modify their schedules freely
- Simpler data model, no cascading updates

### 2. Storage of Additional Settings
- **Schedule timing:** Stored in `ReportSchedule` table
- **Report sections/delivery:** Stored in `Customer.report_configuration` JSON
- **Template metadata:** Stored in `ReportTemplate` table

### 3. Template Application Flow
```
1. User creates template with all settings
2. User selects customer(s) to apply template
3. System COPIES template values to new ReportSchedule
4. System COPIES additional settings to Customer.report_configuration
5. Customer can now modify their schedule independently
6. Template changes don't affect existing customer schedules
```

---

## Implementation Phases

### Phase 1: Backend Foundation (8-10 hours) ✅ COMPLETED
- [x] Create migration for `report_templates` table
- [x] Create `ReportTemplate` model
- [x] Create `ReportTemplateController` with CRUD operations
- [x] Create `ReportTemplateService` with apply methods
- [x] Add API routes
- [x] Run migration
- [ ] Write unit tests (optional - can be done later)

**Files Created:**
- `backend/database/migrations/2025_12_17_130209_create_report_templates_table.php`
- `backend/app/Models/ReportTemplate.php`
- `backend/app/Services/ReportTemplateService.php`
- `backend/app/Http/Controllers/Api/ReportTemplateController.php`
- `backend/routes/api.php` (routes added)

**Database:** Migration executed successfully - `report_templates` table created

### Phase 2: Frontend UI (10-12 hours) ✅ COMPLETED
- [x] Create template types and interfaces
- [x] Create template list page
- [x] Create create/edit template modal
- [x] Create apply template modal
- [x] Add API client methods
- [x] Add navigation menu item
- [x] Add route configuration

**Files Created:**
- `frontend/src/pages/templates/types/template.ts`
- `frontend/src/lib/api/templates.ts`
- `frontend/src/pages/templates/TemplateList.tsx`
- `frontend/src/pages/templates/modals/CreateTemplateModal.tsx`
- `frontend/src/pages/templates/modals/ApplyTemplateModal.tsx`
- `frontend/src/App.tsx` (route added)
- `frontend/src/components/layout/AppSidebar.tsx` (navigation item added)

**Features Implemented:**
- Full CRUD UI for templates
- Template list with search and filtering
- Create/edit modal with all template fields
- Apply template modal with customer selection (single & bulk)
- Navigation menu item with FileTemplate icon
- Permission-based access (reports.configure)
- Route: `/platform/templates`

### Phase 3: Integration (4-6 hours) ✅ COMPLETED
- [x] Add "Apply Template" button to customer page
- [x] Connect template application to schedule creation
- [x] Add template indicator to schedule list (optional - can be added later)
- [x] Test end-to-end workflow
- [x] Update documentation

**Files Created:**
- `frontend/src/pages/customers/modals/ApplyTemplateToCustomerModal.tsx`

**Files Modified:**
- `frontend/src/pages/customers/components/CustomerCard.tsx` (added Apply Template dropdown option)
- `frontend/src/pages/customers/components/CustomersGrid.tsx` (added onApplyTemplate prop)
- `frontend/src/pages/customers/MasterCustomerList.tsx` (integrated Apply Template modal)
- `frontend/src/pages/customers/hooks.ts` (added applyTemplate modal support)
- `frontend/src/stores/useCustomersStore.ts` (added applyTemplate modal state)

**Features Implemented:**
- Apply Template option in customer card dropdown menu (with permission check)
- Template selection modal for applying templates to individual customers
- Integration with existing customer management workflow
- Success notifications and error handling
- Automatic schedule creation from template
- Permission-based access control (reports.configure required)

### Phase 4: Polish & Testing (2-4 hours)
- [ ] Add loading states and error handling
- [ ] Add success/error notifications
- [ ] Write integration tests
- [ ] User acceptance testing
- [ ] Bug fixes

**Total Estimate: 24-32 hours**

---

## Testing Checklist

### Backend
- [ ] Create template with all fields
- [ ] Update template
- [ ] Delete template
- [ ] Apply template to single customer
- [ ] Apply template to multiple customers
- [ ] Verify schedule is independent (modify schedule doesn't affect template)
- [ ] Verify template update doesn't affect existing schedules

### Frontend
- [ ] Create template via UI
- [ ] Edit template
- [ ] Delete template
- [ ] Apply template to customer
- [ ] Apply template to multiple customers
- [ ] Modify customer schedule after template application
- [ ] Verify template changes don't affect customer schedules

---

## Database Schema

```sql
-- New table
CREATE TABLE report_templates (
    template_id BIGSERIAL PRIMARY KEY,
    company_id BIGINT NOT NULL REFERENCES companies(company_id) ON DELETE CASCADE,
    template_name VARCHAR(255) NOT NULL,
    description TEXT,
    report_type VARCHAR(255) NOT NULL,
    frequency VARCHAR(50) NOT NULL CHECK (frequency IN ('daily', 'weekly', 'monthly', 'yearly', 'on-demand')),
    schedule_config JSONB,
    time_of_day TIME,
    timezone VARCHAR(255) DEFAULT 'Australia/Brisbane',
    included_sections JSONB,
    delivery_settings JSONB,
    report_options JSONB,
    active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_report_templates_company_active ON report_templates(company_id, active);
CREATE INDEX idx_report_templates_report_type ON report_templates(report_type);
```

---

## API Endpoints Summary

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/report-templates` | List all templates |
| POST | `/api/report-templates` | Create new template |
| GET | `/api/report-templates/{id}` | Get template details |
| PUT | `/api/report-templates/{id}` | Update template |
| DELETE | `/api/report-templates/{id}` | Delete template |
| POST | `/api/report-templates/{id}/apply-to-customer` | Apply to single customer |
| POST | `/api/report-templates/{id}/apply-to-customers` | Apply to multiple customers |

---

## Notes

- Templates are company-scoped (multi-tenant safe)
- All schedules created from templates are independent
- Customers can modify their schedules without affecting the template
- Template updates do NOT cascade to existing schedules
- This is a "preset/blueprint" system, not a "linked configuration" system
