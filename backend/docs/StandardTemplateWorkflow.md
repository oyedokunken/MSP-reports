# Template System Modifications - Workflow

## Overview
Modify the report template system to support multiple report types per template, making templates the primary way to configure customer reports instead of manual configuration.

## Boss's Requirements Summary
1. **Remove manual report type selection** from Configure Reports page
2. **Templates should allow selecting multiple report types** (checkboxes, not dropdown)
3. **Apply template to customer** creates schedules for ALL selected report types
4. **Allow per-customer overrides** after template is applied
5. **Bulk apply already exists** - can select multiple customers at once

---

## Phase 1: Database & Backend Changes

### 1.1 Database Migration
**File:** `backend/database/migrations/YYYY_MM_DD_HHMMSS_modify_report_templates_for_multiple_types.php`

```php
// Change report_type from single string to array
Schema::table('report_templates', function (Blueprint $table) {
    // Rename old column for backup
    $table->renameColumn('report_type', 'report_type_old');
    
    // Add new column for multiple report types
    $table->jsonb('report_types')->nullable();
});

// Migrate existing data: convert single report_type to array
DB::table('report_templates')->get()->each(function ($template) {
    DB::table('report_templates')
        ->where('template_id', $template->template_id)
        ->update([
            'report_types' => json_encode([$template->report_type_old])
        ]);
});

// Drop old column after migration
Schema::table('report_templates', function (Blueprint $table) {
    $table->dropColumn('report_type_old');
});
```

**Rollback:**
```php
// Reverse: convert array back to single string (take first item)
Schema::table('report_templates', function (Blueprint $table) {
    $table->string('report_type')->nullable();
});

DB::table('report_templates')->get()->each(function ($template) {
    $types = json_decode($template->report_types, true);
    DB::table('report_templates')
        ->where('template_id', $template->template_id)
        ->update([
            'report_type' => $types[0] ?? 'monthly_comprehensive'
        ]);
});

Schema::table('report_templates', function (Blueprint $table) {
    $table->dropColumn('report_types');
});
```

### 1.2 Update ReportTemplate Model
**File:** `backend/app/Models/ReportTemplate.php`

```php
protected $fillable = [
    'company_id',
    'template_name',
    'description',
    'report_types', // Changed from 'report_type'
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
    'report_types' => 'array', // Changed from string to array
    'schedule_config' => 'array',
    'included_sections' => 'array',
    'delivery_settings' => 'array',
    'report_options' => 'array',
    'active' => 'boolean',
];
```

### 1.3 Update ReportTemplateService
**File:** `backend/app/Services/ReportTemplateService.php`

```php
/**
 * Apply template to a single customer (COPY settings, not link)
 * Creates MULTIPLE schedules - one for each report type in template
 */
public function applyTemplateToCustomer(ReportTemplate $template, Customer $customer): Collection
{
    $schedules = collect();
    
    // Create a schedule for EACH report type in the template
    foreach ($template->report_types as $reportType) {
        $schedule = ReportSchedule::create([
            'company_id' => $customer->company_id,
            'customer_id' => $customer->customer_id,
            'report_type' => $reportType, // Individual report type
            'frequency' => $template->frequency,
            'schedule_config' => $template->schedule_config,
            'time_of_day' => $template->time_of_day,
            'timezone' => $template->timezone ?? 'Australia/Brisbane',
            'active' => true,
        ]);
        
        $schedules->push($schedule);
    }
    
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
    
    return $schedules->fresh();
}

/**
 * Apply template to multiple customers
 */
public function applyTemplateToCustomers(ReportTemplate $template, array $customerIds): Collection
{
    $allSchedules = collect();
    
    foreach ($customerIds as $customerId) {
        $customer = Customer::find($customerId);
        if ($customer) {
            $schedules = $this->applyTemplateToCustomer($template, $customer);
            $allSchedules = $allSchedules->merge($schedules);
        }
    }
    
    return $allSchedules;
}
```

### 1.4 Update API Validation
**File:** `backend/app/Http/Controllers/Api/ReportTemplateController.php`

```php
// In store() and update() methods
$validated = $request->validate([
    'template_name' => 'required|string|max:255',
    'description' => 'nullable|string',
    'report_types' => 'required|array|min:1', // Changed: now array, min 1 item
    'report_types.*' => 'required|string|in:autotask_monthly_summary,datto_rmm_monthly_summary,itglue_monthly_summary,monthly_comprehensive,uptime_report,backup_status,security_summary',
    'frequency' => 'required|in:daily,weekly,monthly,yearly,on-demand',
    'schedule_config' => 'nullable|array',
    'time_of_day' => 'nullable|date_format:H:i',
    'timezone' => 'required|string',
    'included_sections' => 'nullable|array',
    'delivery_settings' => 'nullable|array',
    'report_options' => 'nullable|array',
    'active' => 'boolean',
]);
```

---

## Phase 2: Frontend Changes

### 2.1 Update Template Types
**File:** `frontend/src/pages/templates/types/template.ts`

```typescript
export interface ReportTemplate {
  template_id: number
  company_id: number
  template_name: string
  description?: string
  report_types: string[] // Changed from report_type: string
  frequency: 'daily' | 'weekly' | 'monthly' | 'yearly' | 'on-demand'
  schedule_config?: Record<string, any>
  time_of_day?: string
  timezone: string
  included_sections?: string[]
  delivery_settings?: Record<string, any>
  report_options?: Record<string, any>
  active: boolean
  created_at: string
  updated_at: string
}

export interface CreateTemplateRequest {
  template_name: string
  description?: string
  report_types: string[] // Changed from report_type: string
  frequency: 'daily' | 'weekly' | 'monthly' | 'yearly' | 'on-demand'
  schedule_config?: Record<string, any>
  time_of_day?: string
  timezone: string
  included_sections?: string[]
  delivery_settings?: Record<string, any>
  report_options?: Record<string, any>
  active: boolean
}
```

### 2.2 Update CreateTemplateModal - Checkboxes
**File:** `frontend/src/pages/templates/modals/CreateTemplateModal.tsx`

**Changes:**
1. Replace `report_type: string` with `report_types: string[]` in formData
2. Replace dropdown with checkbox list
3. Add validation for at least one report type selected

```tsx
const [formData, setFormData] = useState<CreateTemplateRequest>({
  template_name: '',
  description: '',
  report_types: [], // Changed: now array
  frequency: 'monthly',
  time_of_day: '09:00',
  timezone: 'Australia/Brisbane',
  active: true,
})

// Replace the dropdown section with:
<div>
  <Label>Report Types * (Select at least one)</Label>
  <div className="mt-2 space-y-2 border rounded-lg p-4 max-h-64 overflow-y-auto">
    {REPORT_TYPES.map((type) => (
      <div key={type.value} className="flex items-center space-x-2">
        <Checkbox
          id={`report-type-${type.value}`}
          checked={formData.report_types.includes(type.value)}
          onCheckedChange={(checked) => {
            if (checked) {
              setFormData({
                ...formData,
                report_types: [...formData.report_types, type.value]
              })
            } else {
              setFormData({
                ...formData,
                report_types: formData.report_types.filter(t => t !== type.value)
              })
            }
          }}
          disabled={isSubmitting}
        />
        <Label
          htmlFor={`report-type-${type.value}`}
          className="text-sm font-normal cursor-pointer"
        >
          {type.label}
        </Label>
      </div>
    ))}
  </div>
  <p className="text-xs text-muted-foreground mt-1">
    {formData.report_types.length} report type{formData.report_types.length !== 1 ? 's' : ''} selected
  </p>
</div>

// Update validation:
if (formData.report_types.length === 0) {
  setError('Please select at least one report type')
  return
}
```

### 2.3 Update TemplateList Display
**File:** `frontend/src/pages/templates/TemplateList.tsx`

```tsx
// In the card display section, show multiple report types:
<div className="flex items-start gap-2 text-muted-foreground">
  <FileText className="h-4 w-4 mt-0.5 flex-shrink-0" />
  <div className="flex-1">
    {template.report_types.length === 1 ? (
      <span>{template.report_types[0].replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase())}</span>
    ) : (
      <div className="space-y-1">
        <span className="font-medium">{template.report_types.length} Report Types:</span>
        <div className="text-xs space-y-0.5">
          {template.report_types.map((type, idx) => (
            <div key={idx}>• {type.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase())}</div>
          ))}
        </div>
      </div>
    )}
  </div>
</div>
```

### 2.4 Update ApplyTemplateModal Success Message
**File:** `frontend/src/pages/templates/modals/ApplyTemplateModal.tsx`

```tsx
// Update success message to reflect multiple schedules created:
<DialogDescription className="text-center">
  The template has been successfully applied to {selectedCustomers.length} customer{selectedCustomers.length > 1 ? 's' : ''}.
  {template && template.report_types.length > 1 && (
    <> {template.report_types.length} report schedules have been created per customer.</>
  )}
</DialogDescription>
```

### 2.5 Simplify ConfigureReports Page (Optional)
**File:** `frontend/src/pages/reports/ConfigureReports.tsx`

**Options:**
1. **Keep as-is** - Allow manual override/customization (recommended)
2. **Add helper text** - "Tip: Use templates to quickly configure multiple reports"
3. **Add "Apply Template" button** - Quick access to apply template from this page

**Recommended: Add helper card at top:**
```tsx
<Card className="bg-primary/5 border-primary/20">
  <CardContent className="pt-6">
    <div className="flex items-start gap-3">
      <FileText className="h-5 w-5 text-primary mt-0.5" />
      <div>
        <h3 className="font-medium text-sm mb-1"> Quick Setup with Templates</h3>
        <p className="text-sm text-muted-foreground mb-3">
          Save time by applying a report template to configure multiple reports at once.
        </p>
        <Button
          variant="outline"
          size="sm"
          onClick={() => navigate('/platform/templates')}
        >
          <FileText className="h-4 w-4 mr-2" />
          Browse Templates
        </Button>
      </div>
    </div>
  </CardContent>
</Card>
```

---

## Phase 3: Testing Checklist

### 3.1 Backend Tests
- [ ] Migration runs successfully (up and down)
- [ ] Existing templates migrate correctly (single type → array)
- [ ] Create template with multiple report types
- [ ] Apply template creates multiple schedules
- [ ] Apply template to multiple customers (bulk)
- [ ] Validation rejects empty report_types array
- [ ] Validation rejects invalid report type values

### 3.2 Frontend Tests
- [ ] Template creation modal shows checkboxes
- [ ] Can select/deselect multiple report types
- [ ] Validation prevents saving with no types selected
- [ ] Template list displays multiple types correctly
- [ ] Apply template modal works with new structure
- [ ] Success message shows correct schedule count
- [ ] Edit existing template loads report_types correctly

### 3.3 Integration Tests
- [ ] Create template → Apply to customer → Verify schedules created
- [ ] Apply template with 3 report types → Verify 3 schedules exist
- [ ] Apply to 5 customers → Verify 5 × N schedules created
- [ ] Customer can still manually add/remove schedules after template applied
- [ ] Customer can modify individual schedule settings

### 3.4 Edge Cases
- [ ] Template with 1 report type (should work like before)
- [ ] Template with 7+ report types (UI handles long list)
- [ ] Apply template to customer who already has some schedules (no duplicates)
- [ ] Edit template report_types after already applied (doesn't affect existing customers)
- [ ] Delete template (doesn't affect customer schedules - they're copies)

---

## Phase 4: Deployment Steps

### 4.1 Pre-Deployment
1. Backup database
2. Test migration on staging environment
3. Verify existing templates still work
4. Test rollback procedure

### 4.2 Deployment
1. Put application in maintenance mode (if needed)
2. Run database migration
3. Deploy backend code
4. Deploy frontend code
5. Verify migration success
6. Test template creation and application
7. Remove maintenance mode

### 4.3 Post-Deployment
1. Monitor error logs for issues
2. Check existing templates display correctly
3. Test creating new multi-type template
4. Verify bulk apply functionality
5. Communicate changes to users

---

## Phase 5: User Communication

### 5.1 Release Notes
**New Feature: Multi-Report Templates**

Templates can now include multiple report types! This makes it even easier to set up comprehensive reporting for your customers.

**What's New:**
- Select multiple report types when creating a template
- Apply one template to create multiple report schedules at once
- Bulk apply templates to multiple customers simultaneously
- Full flexibility to customize individual schedules after template application

**How to Use:**
1. Go to Templates → Create Template
2. Select multiple report types using checkboxes
3. Configure frequency, time, and other settings
4. Apply template to one or more customers
5. Each selected report type creates a separate schedule

**Example:**
Create a "Standard Monthly Package" template with:
- Autotask Monthly Summary
- Datto RMM Monthly Summary
- Monthly Comprehensive Report

Apply to 10 customers → Creates 30 schedules (3 per customer) in one click!

---

## Timeline Estimate

| Phase | Task | Estimated Time |
|-------|------|----------------|
| 1 | Database migration | 1 hour |
| 1 | Update models & service | 1-2 hours |
| 1 | Update API validation | 30 mins |
| 2 | Update TypeScript types | 15 mins |
| 2 | Update CreateTemplateModal | 1-2 hours |
| 2 | Update TemplateList display | 30 mins |
| 2 | Update ApplyTemplateModal | 30 mins |
| 2 | Add helper to ConfigureReports | 30 mins |
| 3 | Testing (all phases) | 2-3 hours |
| 4 | Deployment | 1 hour |
| **Total** | | **8-12 hours** |

---

## Notes & Considerations

### Design Decisions
1. **Copy vs. Link:** Templates copy settings to schedules (not linked) - allows per-customer customization
2. **Bulk Apply:** Already implemented - can select multiple customers at once
3. **Backward Compatible:** Migration converts existing single type to array
4. **Manual Override:** ConfigureReports page still allows manual schedule management

### Future Enhancements
1. **Default Template:** Auto-apply template to new customers
2. **Customer Groups:** Apply template to tagged customer groups
3. **Template Preview:** Show what schedules will be created before applying
4. **Template Versioning:** Track template changes over time
5. **Apply to All:** One-click apply to all active customers

### Potential Issues
1. **Duplicate Schedules:** Need to check if customer already has schedule for that report type
2. **Performance:** Applying template with many report types to many customers could be slow
3. **UI Overflow:** Long list of report types in template card - need scrolling/truncation
4. **Validation:** Ensure selected report types are actually available for customer's integrations
