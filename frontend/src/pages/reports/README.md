# Reports & Analytics Module

## Overview
Complete implementation of the Reports & Analytics system with scheduled reports, report history, customer configuration, and on-demand generation.

## Features Implemented

### 1. Reports List Page (`/reports`)
- **Scheduled Reports Tab**
  - View all scheduled reports across customers
  - Shows frequency, time, last generated, next scheduled
  - Active/Inactive status badges
  
- **Report History Tab**
  - View all generated reports
  - Download completed reports
  - See delivery status and errors
  - File size and generation timestamps

### 2. Customer Reports Configuration (`/customers/:id/reports`)
- View enabled reports for a customer
- Configure delivery email destination
- Add CC recipients
- View report settings (timezone, retention, format)
- Generate reports on-demand

## API Endpoints Used

### Report Configuration
- `GET /api/reports` - Get available report types
- `GET /api/customers/{id}/reports` - Get customer report configuration
- `PATCH /api/customers/{id}/reports` - Update customer reports
- `PATCH /api/customers/{id}/report-destination` - Update delivery destination
- `POST /api/reports/generate/{customer_id}` - Generate report on-demand

### Report Analytics
- `GET /api/reports/scheduled` - Get all scheduled reports
- `GET /api/reports/history` - Get report generation history
- `GET /api/reports/{id}/download` - Download generated report

## Components

### ReportsList.tsx
Main reports page with tabs for scheduled and history views.

**Features:**
- Tab navigation (Scheduled/History)
- Card-based layout for reports
- Status badges (Completed, Pending, Failed)
- Download functionality
- Real-time loading states

### CustomerReports.tsx
Customer-specific report configuration page.

**Features:**
- View enabled reports
- Configure email delivery
- Add CC recipients
- Generate reports on-demand
- Save configuration

## Usage

### Navigate to Reports
```tsx
import { Link } from 'react-router-dom'

<Link to="/reports">View Reports</Link>
```

### Navigate to Customer Reports
```tsx
<Link to={`/customers/${customerId}/reports`}>
  Configure Reports
</Link>
```

### Generate Report Programmatically
```tsx
import { api } from '@/lib/api'

const generateReport = async (customerId: number) => {
  await api.post(`/reports/generate/${customerId}`)
}
```

### Download Report
```tsx
const downloadReport = async (reportId: number) => {
  const response = await api.get(`/reports/${reportId}/download`, {
    responseType: 'blob'
  })
  
  const url = window.URL.createObjectURL(new Blob([response.data]))
  const link = document.createElement('a')
  link.href = url
  link.setAttribute('download', `report_${reportId}.txt`)
  document.body.appendChild(link)
  link.click()
  link.remove()
}
```

## Routes

```tsx
// App.tsx
<Route path="/reports" element={<ReportsList />} />
<Route path="/customers/:id/reports" element={<CustomerReports />} />
```

## Data Models

### ReportSchedule
```typescript
interface ReportSchedule {
  report_schedule_id: number
  report_type: string
  frequency: string
  time_of_day: string | null
  active: boolean
  last_generated_at: string | null
  next_scheduled_at: string | null
  timezone: string
  customer: Customer
}
```

### GeneratedReport
```typescript
interface GeneratedReport {
  generated_report_id: number
  customer_id: number
  generated_at: string
  status: string
  delivery_method: string
  file_path: string
  file_size: number
  delivered: boolean
  delivered_at: string | null
  error_message: string | null
  customer: Customer
}
```

### ReportConfig
```typescript
interface ReportConfig {
  enabled_reports: Array<{
    report_type: string
    schedule_id?: number
  }>
  delivery: {
    method: string
    destination: string
    cc: string[]
  }
  timezone: string | null
  retention_days: number | null
}
```

## Next Steps

1. **Add Report Type Selection** - Allow users to enable/disable specific report types
2. **Schedule Configuration** - Add UI to configure report schedules (frequency, time)
3. **Report Preview** - Preview reports before downloading
4. **Bulk Operations** - Generate multiple reports at once
5. **Report Templates** - Customize report templates per customer
6. **Email Preview** - Preview email before sending

## Testing

Test the implementation:
1. Navigate to `/reports`
2. Check scheduled reports tab
3. Check report history tab
4. Click download on a completed report
5. Navigate to `/customers/1/reports`
6. Update delivery destination
7. Generate a report on-demand
8. Verify report appears in history

## Notes

- All API calls are authenticated with Sanctum
- CSRF protection is disabled for API routes
- File downloads use blob response type
- Email validation on delivery destinations
- CC emails are comma-separated
