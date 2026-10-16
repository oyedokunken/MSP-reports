# Configurable Summary/Detail Reports - Requirements & Implementation Plan

## Overview

**Requirement from TTS Meeting:**
- First few pages of reports should be "summary" pages with traffic-light status indicators (red, orange, green)
- Later pages break down into detailed information
- Make reports configurable: generate summary-only OR include full details
- Give customers flexibility to choose report depth

**Status:** Not implemented - needs to be built

---

## Report Structure Concept

### Current Report Structure
```
┌─────────────────────────────────┐
│  Header / Branding              │
├─────────────────────────────────┤
│  All Data Mixed Together        │
│  - Tables with all details      │
│  - No clear summary/detail split│
│  - Hard to scan quickly         │
└─────────────────────────────────┘
```

### New Report Structure
```
┌─────────────────────────────────┐
│  Page 1: Executive Summary      │
│  ┌───────────────────────────┐  │
│  │ Licenses: 15/20 (75%)  │  │
│  │ SSL Certs: 2 expiring  │  │
│  │ Domains: 1 expired     │  │
│  │ Backups: 98% success   │  │
│  └───────────────────────────┘  │
├─────────────────────────────────┤
│  Page 2: Critical Items Only    │
│  - Items requiring attention    │
│  - Red/Orange status items      │
├─────────────────────────────────┤
│  Page 3+: Detailed Breakdown    │
│  (Optional - if enabled)         │
│  - Full license tables          │
│  - Complete SSL cert list       │
│  - All domain details           │
│  - Backup job details           │
└─────────────────────────────────┘
```

---

## Traffic-Light Status System

### Status Levels

**Green - Healthy**
- Everything operating normally
- No action required
- Utilization in optimal range (70-89%)
- No expiries within 30 days

**Orange - Warning**
- Attention recommended
- Not critical but should be monitored
- Utilization outside optimal (50-69% or 90-94%)
- Expiring within 30 days

**Red - Critical**
- Immediate action required
- Service at risk
- Utilization critical (<50% or ≥95%)
- Expired or expiring within 7 days

**Grey - Unknown/N/A**
- No data available
- Not applicable
- Monitoring disabled

### Status Calculation Examples

#### Microsoft Licenses
```
Green:  70-89% utilization
Orange: 50-69% or 90-94% utilization
Red:    <50% or ≥95% utilization
Grey:   No data
```

#### SSL Certificates
```
Green:  >30 days until expiry
Orange: 8-30 days until expiry
Red:    ≤7 days or expired
Grey:   No expiry date
```

#### Backups
```
Green:  ≥95% success rate
Orange: 85-94% success rate
Red:    <85% success rate
Grey:   No backup data
```

#### Device Uptime
```
Green:  ≥99% uptime
Orange: 95-98.9% uptime
Red:    <95% uptime
Grey:   No monitoring data
```

---

## Configuration Options

### Report Detail Level

**Option 1: Summary Only**
- Pages: 1-2 only
- Content: Executive summary + critical items
- Use case: Quick monthly check-ins, board reports
- File size: Small (~100-200KB)
- Generation time: Fast (~5-10 seconds)

**Option 2: Summary + Details**
- Pages: 1-2 + detailed sections
- Content: Everything
- Use case: Comprehensive monthly reports, audits
- File size: Larger (~500KB-2MB)
- Generation time: Slower (~15-30 seconds)

**Option 3: Details Only** (optional)
- Pages: Detailed sections only
- Content: Skip summary, go straight to details
- Use case: Technical deep-dives
- File size: Medium (~400KB-1MB)
- Generation time: Medium (~10-20 seconds)

### Configuration Storage

**Per Report Schedule:**
```json
{
  "report_detail_level": "summary_only" | "summary_and_details" | "details_only",
  "include_sections": {
    "executive_summary": true,
    "critical_items": true,
    "licenses": false,
    "ssl_certificates": false,
    "domains": false,
    "backups": false,
    "uptime": false
  }
}
```

**Per Report Template:**
```json
{
  "template_name": "Executive Summary Report",
  "report_detail_level": "summary_only",
  "include_sections": {
    "executive_summary": true,
    "critical_items": true,
    "licenses": false,
    "ssl_certificates": false,
    "domains": false
  }
}
```

---

## NEW COMPONENTS TO CREATE

### Backend

#### 1. Status Calculator Service
**File:** `backend/app/Services/ReportStatusCalculator.php`

```php
<?php

namespace App\Services;

class ReportStatusCalculator
{
    /**
     * Calculate traffic-light status for a metric
     * 
     * @return string 'green'|'orange'|'red'|'grey'
     */
    public function calculateLicenseStatus(float $utilization): string
    {
        if ($utilization >= 70 && $utilization < 90) return 'green';
        if (($utilization >= 50 && $utilization < 70) || ($utilization >= 90 && $utilization < 95)) return 'orange';
        if ($utilization < 50 || $utilization >= 95) return 'red';
        return 'grey';
    }
    
    public function calculateExpiryStatus(?int $daysUntilExpiry): string
    {
        if ($daysUntilExpiry === null) return 'grey';
        if ($daysUntilExpiry > 30) return 'green';
        if ($daysUntilExpiry >= 8 && $daysUntilExpiry <= 30) return 'orange';
        return 'red'; // ≤7 days or expired
    }
    
    public function calculateBackupStatus(float $successRate): string
    {
        if ($successRate >= 95) return 'green';
        if ($successRate >= 85) return 'orange';
        return 'red';
    }
    
    public function calculateUptimeStatus(float $uptimePercentage): string
    {
        if ($uptimePercentage >= 99) return 'green';
        if ($uptimePercentage >= 95) return 'orange';
        return 'red';
    }
    
    /**
     * Get status icon/emoji for display
     */
    public function getStatusIcon(string $status): string
    {
        return match($status) {
            'green' => '',
            'orange' => '',
            'red' => '',
            'grey' => '',
            default => '',
        };
    }
    
    /**
     * Get status label
     */
    public function getStatusLabel(string $status): string
    {
        return match($status) {
            'green' => 'Healthy',
            'orange' => 'Warning',
            'red' => 'Critical',
            'grey' => 'Unknown',
            default => 'Unknown',
        };
    }
}
```

#### 2. Summary Data Aggregator
**File:** `backend/app/Services/ReportSummaryService.php`

```php
<?php

namespace App\Services;

use Illuminate\Support\Collection;

class ReportSummaryService
{
    public function __construct(
        private ReportStatusCalculator $statusCalculator
    ) {}
    
    /**
     * Generate executive summary from report data
     */
    public function generateExecutiveSummary(array $reportData): array
    {
        $summary = [];
        
        // License summary
        if (isset($reportData['licenses'])) {
            $summary['licenses'] = $this->summarizeLicenses($reportData['licenses']);
        }
        
        // SSL certificate summary
        if (isset($reportData['ssl_certificates'])) {
            $summary['ssl_certificates'] = $this->summarizeCertificates($reportData['ssl_certificates']);
        }
        
        // Domain summary
        if (isset($reportData['domains'])) {
            $summary['domains'] = $this->summarizeDomains($reportData['domains']);
        }
        
        // Backup summary
        if (isset($reportData['backups'])) {
            $summary['backups'] = $this->summarizeBackups($reportData['backups']);
        }
        
        // Overall health score
        $summary['overall_health'] = $this->calculateOverallHealth($summary);
        
        return $summary;
    }
    
    private function summarizeLicenses(array $licenses): array
    {
        $data = $licenses['data'] ?? [];
        $total = count($data);
        $critical = collect($data)->filter(fn($l) => ($l['status'] ?? '') === 'under_provisioned')->count();
        $warning = collect($data)->filter(fn($l) => ($l['status'] ?? '') === 'over_provisioned')->count();
        
        $status = 'green';
        if ($critical > 0) $status = 'red';
        elseif ($warning > 0) $status = 'orange';
        
        return [
            'status' => $status,
            'icon' => $this->statusCalculator->getStatusIcon($status),
            'label' => 'Microsoft Licenses',
            'value' => "$total licenses",
            'detail' => $critical > 0 ? "$critical under-provisioned" : ($warning > 0 ? "$warning over-provisioned" : "All optimal"),
        ];
    }
    
    private function summarizeCertificates(array $certificates): array
    {
        $data = $certificates['data'] ?? [];
        $total = count($data);
        $critical = collect($data)->filter(fn($c) => in_array($c['severity'] ?? '', ['expired', 'critical']))->count();
        $warning = collect($data)->filter(fn($c) => ($c['severity'] ?? '') === 'warning')->count();
        
        $status = 'green';
        if ($critical > 0) $status = 'red';
        elseif ($warning > 0) $status = 'orange';
        
        return [
            'status' => $status,
            'icon' => $this->statusCalculator->getStatusIcon($status),
            'label' => 'SSL Certificates',
            'value' => "$total certificates",
            'detail' => $critical > 0 ? "$critical critical" : ($warning > 0 ? "$warning expiring soon" : "All valid"),
        ];
    }
    
    private function summarizeDomains(array $domains): array
    {
        $data = $domains['data'] ?? [];
        $total = count($data);
        $critical = collect($data)->filter(fn($d) => in_array($d['severity'] ?? '', ['expired', 'critical']))->count();
        $warning = collect($data)->filter(fn($d) => ($d['severity'] ?? '') === 'warning')->count();
        
        $status = 'green';
        if ($critical > 0) $status = 'red';
        elseif ($warning > 0) $status = 'orange';
        
        return [
            'status' => $status,
            'icon' => $this->statusCalculator->getStatusIcon($status),
            'label' => 'Domain Registrations',
            'value' => "$total domains",
            'detail' => $critical > 0 ? "$critical expired/critical" : ($warning > 0 ? "$warning expiring soon" : "All valid"),
        ];
    }
    
    private function summarizeBackups(array $backups): array
    {
        $data = $backups['data'] ?? [];
        $total = count($data);
        $successful = collect($data)->filter(fn($b) => ($b['status'] ?? '') === 'success')->count();
        $successRate = $total > 0 ? ($successful / $total) * 100 : 0;
        
        $status = $this->statusCalculator->calculateBackupStatus($successRate);
        
        return [
            'status' => $status,
            'icon' => $this->statusCalculator->getStatusIcon($status),
            'label' => 'Backups',
            'value' => round($successRate, 1) . '% success',
            'detail' => "$successful of $total successful",
        ];
    }
    
    private function calculateOverallHealth(array $summary): array
    {
        $statuses = collect($summary)->pluck('status')->filter();
        
        $status = 'green';
        if ($statuses->contains('red')) $status = 'red';
        elseif ($statuses->contains('orange')) $status = 'orange';
        
        return [
            'status' => $status,
            'icon' => $this->statusCalculator->getStatusIcon($status),
            'label' => $this->statusCalculator->getStatusLabel($status),
        ];
    }
    
    /**
     * Extract critical items that need attention
     */
    public function extractCriticalItems(array $reportData): array
    {
        $critical = [];
        
        // Critical licenses
        if (isset($reportData['licenses']['data'])) {
            foreach ($reportData['licenses']['data'] as $license) {
                if (($license['status'] ?? '') === 'under_provisioned') {
                    $critical[] = [
                        'type' => 'license',
                        'severity' => 'red',
                        'title' => $license['name'] . ' - Under-provisioned',
                        'description' => "License utilization at {$license['utilization_percentage']}%. Consider purchasing additional licenses.",
                        'action' => 'Purchase more licenses',
                    ];
                }
            }
        }
        
        // Critical SSL certificates
        if (isset($reportData['ssl_certificates']['data'])) {
            foreach ($reportData['ssl_certificates']['data'] as $cert) {
                if (in_array($cert['severity'] ?? '', ['expired', 'critical'])) {
                    $critical[] = [
                        'type' => 'ssl_certificate',
                        'severity' => 'red',
                        'title' => $cert['domain'] . ' - SSL Certificate Issue',
                        'description' => $cert['days_until_expiry'] <= 0 ? 'Certificate expired' : "Expires in {$cert['days_until_expiry']} days",
                        'action' => 'Renew SSL certificate',
                    ];
                }
            }
        }
        
        // Critical domains
        if (isset($reportData['domains']['data'])) {
            foreach ($reportData['domains']['data'] as $domain) {
                if (in_array($domain['severity'] ?? '', ['expired', 'critical'])) {
                    $critical[] = [
                        'type' => 'domain',
                        'severity' => 'red',
                        'title' => $domain['domain_name'] . ' - Domain Issue',
                        'description' => $domain['days_until_expiry'] <= 0 ? 'Domain expired' : "Expires in {$domain['days_until_expiry']} days",
                        'action' => 'Renew domain registration',
                    ];
                }
            }
        }
        
        return $critical;
    }
}
```

#### 3. Report Template Partial Views
**File:** `backend/resources/views/Reports/partials/executive-summary.blade.php`

```blade
{{-- Executive Summary Page --}}
<div class="page executive-summary">
    <h1 class="page-title">Executive Summary</h1>
    
    <div class="overall-health">
        <div class="health-indicator {{ $executive_summary['overall_health']['status'] }}">
            <span class="icon">{{ $executive_summary['overall_health']['icon'] }}</span>
            <span class="label">Overall Status: {{ $executive_summary['overall_health']['label'] }}</span>
        </div>
    </div>
    
    <div class="summary-grid">
        @foreach($executive_summary as $key => $item)
            @if($key !== 'overall_health' && is_array($item))
                <div class="summary-card {{ $item['status'] }}">
                    <div class="card-header">
                        <span class="icon">{{ $item['icon'] }}</span>
                        <span class="label">{{ $item['label'] }}</span>
                    </div>
                    <div class="card-value">{{ $item['value'] }}</div>
                    <div class="card-detail">{{ $item['detail'] }}</div>
                </div>
            @endif
        @endforeach
    </div>
</div>

<style>
.executive-summary {
    padding: 40px;
}

.overall-health {
    text-align: center;
    margin-bottom: 40px;
}

.health-indicator {
    display: inline-flex;
    align-items: center;
    gap: 12px;
    padding: 20px 40px;
    border-radius: 12px;
    font-size: 24px;
    font-weight: 600;
}

.health-indicator.green { background: #d1fae5; color: #065f46; }
.health-indicator.orange { background: #fef3c7; color: #92400e; }
.health-indicator.red { background: #fee2e2; color: #991b1b; }

.summary-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 24px;
}

.summary-card {
    padding: 24px;
    border-radius: 8px;
    border-left: 4px solid;
}

.summary-card.green { background: #f0fdf4; border-color: #22c55e; }
.summary-card.orange { background: #fffbeb; border-color: #f59e0b; }
.summary-card.red { background: #fef2f2; border-color: #ef4444; }

.card-header {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 14px;
    font-weight: 600;
    margin-bottom: 12px;
}

.card-value {
    font-size: 28px;
    font-weight: 700;
    margin-bottom: 8px;
}

.card-detail {
    font-size: 12px;
    color: #6b7280;
}
</style>
```

**File:** `backend/resources/views/Reports/partials/critical-items.blade.php`

```blade
{{-- Critical Items Page --}}
<div class="page critical-items">
    <h1 class="page-title">Items Requiring Attention</h1>
    
    @if(empty($critical_items))
        <div class="no-critical">
            <span class="icon"></span>
            <h2>All Systems Healthy</h2>
            <p>No critical items requiring immediate attention.</p>
        </div>
    @else
        <div class="critical-list">
            @foreach($critical_items as $item)
                <div class="critical-item {{ $item['severity'] }}">
                    <div class="item-header">
                        <span class="severity-badge">{{ $item['severity'] === 'red' ? 'Critical' : 'Warning' }}</span>
                        <span class="item-type">{{ ucfirst($item['type']) }}</span>
                    </div>
                    <h3 class="item-title">{{ $item['title'] }}</h3>
                    <p class="item-description">{{ $item['description'] }}</p>
                    <div class="item-action">
                        <strong>Recommended Action:</strong> {{ $item['action'] }}
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</div>

<style>
.critical-items {
    padding: 40px;
}

.no-critical {
    text-align: center;
    padding: 60px 20px;
}

.no-critical .icon {
    font-size: 64px;
    display: block;
    margin-bottom: 20px;
}

.critical-list {
    display: flex;
    flex-direction: column;
    gap: 20px;
}

.critical-item {
    padding: 20px;
    border-radius: 8px;
    border-left: 4px solid;
}

.critical-item.red {
    background: #fef2f2;
    border-color: #ef4444;
}

.critical-item.orange {
    background: #fffbeb;
    border-color: #f59e0b;
}

.item-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 12px;
}

.severity-badge {
    font-size: 12px;
    font-weight: 600;
    padding: 4px 12px;
    border-radius: 12px;
    background: rgba(0,0,0,0.05);
}

.item-type {
    font-size: 11px;
    text-transform: uppercase;
    color: #6b7280;
    font-weight: 600;
}

.item-title {
    font-size: 18px;
    font-weight: 600;
    margin-bottom: 8px;
}

.item-description {
    font-size: 14px;
    color: #374151;
    margin-bottom: 12px;
}

.item-action {
    font-size: 13px;
    padding: 12px;
    background: rgba(0,0,0,0.03);
    border-radius: 4px;
}
</style>
```

---

## COMPONENTS TO MODIFY

### Backend

#### 1. Report Interface
**File:** `backend/app/Reports/Contracts/ReportInterface.php`

**ADD method:**
```php
/**
 * Get report configuration options
 */
public function getConfigurationOptions(): array;
```

#### 2. All Report Classes
**Files:** `backend/app/Reports/*/[ReportName].php`

**MODIFY generate() method to accept options:**
```php
public function generate(Customer $customer, Carbon $startDate, Carbon $endDate, array $options = []): array
{
    // Extract configuration
    $detailLevel = $options['report_detail_level'] ?? 'summary_and_details';
    $includeSections = $options['include_sections'] ?? [];
    
    // Generate base data
    $reportData = $this->gatherData($customer, $startDate, $endDate);
    
    // Generate executive summary
    $executiveSummary = $this->summaryService->generateExecutiveSummary($reportData);
    $criticalItems = $this->summaryService->extractCriticalItems($reportData);
    
    // Filter sections based on configuration
    if ($detailLevel === 'summary_only') {
        // Only include summary and critical items
        return [
            'executive_summary' => $executiveSummary,
            'critical_items' => $criticalItems,
            'detail_level' => 'summary_only',
        ];
    }
    
    // Include full details
    return [
        'executive_summary' => $executiveSummary,
        'critical_items' => $criticalItems,
        'detail_level' => $detailLevel,
        // ... rest of report data
    ];
}
```

#### 3. Report Templates (Blade)
**Files:** `backend/resources/views/Reports/*/template.blade.php`

**MODIFY to conditionally include sections:**
```blade
{{-- Always include executive summary --}}
@include('Reports.partials.executive-summary', ['executive_summary' => $executive_summary])

{{-- Always include critical items --}}
@include('Reports.partials.critical-items', ['critical_items' => $critical_items])

{{-- Conditionally include detailed sections --}}
@if($detail_level !== 'summary_only')
    {{-- Existing detailed sections --}}
    <div class="page">
        <h2>Microsoft License Utilization</h2>
        {{-- Existing license table --}}
    </div>
    
    <div class="page">
        <h2>SSL Certificates</h2>
        {{-- Existing SSL table --}}
    </div>
    
    {{-- etc... --}}
@endif
```

#### 4. ReportSchedule Model
**File:** `backend/app/Models/ReportSchedule.php`

**ADD to fillable:**
```php
protected $fillable = [
    // ... existing fields
    'report_options', // NEW: Store detail level and section config
];

protected $casts = [
    // ... existing casts
    'report_options' => 'array', // NEW
];
```

#### 5. Database Migration
**File:** `backend/database/migrations/YYYY_MM_DD_add_report_options_to_report_schedules.php`

```php
Schema::table('report_schedules', function (Blueprint $table) {
    $table->jsonb('report_options')->nullable()->after('timezone');
});
```

### Frontend

#### 1. Schedule Creation/Edit Modal
**File:** `frontend/src/pages/schedules/modals/CreateScheduleModal.tsx` (or similar)

**ADD configuration options:**
```typescript
// Report detail level selector
<Select value={detailLevel} onValueChange={setDetailLevel}>
  <SelectItem value="summary_only">Summary Only</SelectItem>
  <SelectItem value="summary_and_details">Summary + Details</SelectItem>
  <SelectItem value="details_only">Details Only</SelectItem>
</Select>

// Section checkboxes (if summary_and_details selected)
{detailLevel === 'summary_and_details' && (
  <div className="section-checkboxes">
    <Checkbox checked={sections.licenses} onChange={...}>
      Include License Details
    </Checkbox>
    <Checkbox checked={sections.ssl_certificates} onChange={...}>
      Include SSL Certificate Details
    </Checkbox>
    {/* etc... */}
  </div>
)}
```

#### 2. Report Preview
**File:** `frontend/src/pages/reports/ReportPreview.tsx` (if exists)

**ADD indicator showing detail level:**
```typescript
<Badge variant={detailLevel === 'summary_only' ? 'secondary' : 'default'}>
  {detailLevel === 'summary_only' ? 'Summary Only' : 'Full Report'}
</Badge>
```

---

## COMPONENTS TO REMOVE

**None** - This is purely additive functionality.

---

## Implementation Phases

### Phase 1: Status Calculation System (4-6 hours)
- [ ] Create `ReportStatusCalculator` service
- [ ] Create `ReportSummaryService` service
- [ ] Add unit tests for status calculations
- [ ] Test with existing report data

### Phase 2: Summary Page Templates (6-8 hours)
- [ ] Create `executive-summary.blade.php` partial
- [ ] Create `critical-items.blade.php` partial
- [ ] Style traffic-light indicators
- [ ] Test rendering with sample data

### Phase 3: Report Configuration (4-6 hours)
- [ ] Add `report_options` field to `report_schedules` table
- [ ] Modify report `generate()` methods to accept options
- [ ] Implement conditional section rendering
- [ ] Test summary-only vs full reports

### Phase 4: Frontend UI (6-8 hours)
- [ ] Add detail level selector to schedule modal
- [ ] Add section checkboxes
- [ ] Update report preview to show detail level
- [ ] Add configuration to report templates (if implemented)

### Phase 5: Integration & Testing (4-6 hours)
- [ ] Integrate with all existing report types
- [ ] Test each report type with different configurations
- [ ] Verify PDF generation works for all detail levels
- [ ] Performance testing (summary vs full reports)

**Total Estimate: 24-34 hours**

---

## Configuration Examples

### Summary-Only Report
```json
{
  "report_detail_level": "summary_only",
  "include_sections": {
    "executive_summary": true,
    "critical_items": true
  }
}
```

**Output:**
- Page 1: Executive summary with traffic lights
- Page 2: Critical items requiring attention
- Total: 2 pages, ~100-200KB

### Full Report
```json
{
  "report_detail_level": "summary_and_details",
  "include_sections": {
    "executive_summary": true,
    "critical_items": true,
    "licenses": true,
    "ssl_certificates": true,
    "domains": true,
    "backups": true,
    "uptime": true
  }
}
```

**Output:**
- Page 1: Executive summary
- Page 2: Critical items
- Page 3+: All detailed sections
- Total: 5-10 pages, ~500KB-2MB

### Custom Report
```json
{
  "report_detail_level": "summary_and_details",
  "include_sections": {
    "executive_summary": true,
    "critical_items": true,
    "licenses": true,
    "ssl_certificates": true,
    "domains": false,
    "backups": false,
    "uptime": false
  }
}
```

**Output:**
- Page 1: Executive summary
- Page 2: Critical items
- Page 3-4: Only licenses and SSL certificates
- Total: 4 pages, ~300KB

---

## Benefits

### For Executives/Management
- Quick at-a-glance health status
- Traffic-light system easy to understand
- Focus on what needs attention
- Shorter reports = faster review

### For Technical Teams
- Full details available when needed
- Comprehensive data for troubleshooting
- Flexible report depth per customer
- Faster generation for summary-only

### For Customers
- Choose report depth based on needs
- Board reports can be summary-only
- Technical reviews can be full detail
- Reduced email attachment sizes

---

## Testing Checklist

### Status Calculations
- [ ] License status correct for all utilization levels
- [ ] SSL certificate status correct for all expiry ranges
- [ ] Domain status correct for all expiry ranges
- [ ] Backup status correct for all success rates
- [ ] Overall health aggregates correctly

### Report Generation
- [ ] Summary-only reports generate correctly
- [ ] Full reports generate correctly
- [ ] Critical items extracted correctly
- [ ] Traffic-light colors display correctly
- [ ] Page breaks work properly

### Configuration
- [ ] Detail level saves correctly
- [ ] Section checkboxes work
- [ ] Configuration persists across report generations
- [ ] Template configuration applies correctly

---

## Notes

- Traffic-light system uses universally understood colors
- Summary pages should fit on 1-2 pages maximum
- Critical items should be actionable (not just informational)
- Status thresholds can be adjusted per customer if needed
- This feature works independently of report templates feature
- Can be implemented before or after template system
