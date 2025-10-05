---
trigger: always_on
---

## Scheduling System

### Flexible Schedule Configuration

**Supported Frequencies:**

- Daily (every day or specific days of week)
- Weekly (specific day of week)
- Monthly (specific day of month, with fallback for months without that day)
- Yearly (specific month and day)
- On-demand (manual trigger only)

**Time of Day:**

- Specified in company's timezone
- HH:MM format (24-hour)
- Schedule engine converts to UTC for processing

**Configuration Examples:**
See ReportSchedule model in Data Models section for JSON structure.

### Schedule Evaluation Engine

**Runs Hourly (or more frequently):**

1. Query all active ReportSchedules
2. Convert schedule times to UTC
3. Compare next_scheduled_timestamp to current time
4. Queue due reports for generation
5. Calculate and update next_scheduled_timestamp
6. Handle timezone changes (DST transitions)

**Next Schedule Calculation:**

- Daily: Add 24 hours, skip if not in days_of_week
- Weekly: Add 7 days
- Monthly: Same day next month, fallback to last day if needed
- Yearly: Same date next year

### Missed Schedule Handling

**Scenarios:**

- Server down during scheduled time
- Queue backed up
- Report generation failed

**Policies (Company Configurable):**

- Generate all missed reports (catch up)
- Generate only most recent missed (skip old)
- Skip all missed (just resume schedule)

**Implementation:**

- Track missed generations in audit log
- Notify admin of missed schedules
- Allow manual trigger to generate missed report

### Schedule Preview

**UI Feature:**
Users can preview next N scheduled generation times:

- "Next 5 Reports: Dec 1 9am, Jan 1 9am, Feb 1 9am..."
- Validates schedule configuration makes sense
- Shows impact of timezone settings

### Multiple Schedules Per Customer

A customer can have:

- Daily backup summary (Monday-Friday 9am)
- Weekly security report (Monday 9am)
- Monthly comprehensive report (1st of month 9am)

Each is separate ReportSchedule record.