---
trigger: always_on
---

## Rate Limiting Framework

### Purpose

Respect external API rate limits to prevent bans, throttling, or service degradation.

### Per-Integration Rate Limit Configuration

Each integration declares its limits:

```
{
  "requests_per_minute": 60,
  "requests_per_hour": 1000,
  "burst_allowance": 10,
  "min_request_interval_ms": 100,
  "concurrent_requests": 3,
  "historical_import_multiplier": 0.5
}
```

Different integration types have different limits (Datto vs Autotask vs custom APIs).

### Rate Limiter Service

**Tracking (Redis-based for speed):**

- Requests per minute counter (expires after 60s)
- Requests per hour counter (expires after 3600s)
- Last request timestamp
- Active concurrent requests count

**Per Integration Instance:**

- Each Integration model has its own rate limit tracking
- Company A's Datto integration tracked separately from Company B's
- Prevents one company's heavy usage affecting others

### Enforcement Points

**Before Every API Call:**

1. Check if under rate limit
2. If yes, proceed immediately
3. If no, queue request or wait for slot
4. Track request in counters
5. Make API call
6. Release slot when complete

**Smart Throttling:**

- Calculate optimal wait time based on bucket resets
- Respect minimum interval between requests
- Handle burst allowances
- Back off if API returns rate limit errors

### Priority System

**Real-Time Syncs:** Highest priority, use full rate limit
**Backfill Jobs:** Medium priority, pause if real-time sync needed
**Historical Imports:** Lowest priority, use only percentage of limit

**Dynamic Adjustment:**

- If approaching limit, slow down low-priority jobs
- If API returns "rate limit exceeded", pause all for reset period
- If plenty of quota available, speed up imports

### Multi-Company Coordination

**Problem:** 50 companies all using Datto, sharing Athena's IP

- Datto might rate limit by IP, not API key
- One company's heavy usage affects others

**Solution (SaaS Future):**

- Global rate limiter tracks across all companies for same API
- Distributes quota fairly or by subscription tier
- Premium tiers get higher allocation

**First Customer (On-Premise):**

- No cross-company coordination needed
- Simple per-integration rate limiting sufficient

### Monitoring & Alerts

**Track:**

- Rate limit hits (approaching/exceeded)
- API errors indicating throttling
- Jobs delayed due to rate limiting
- Average wait times

**Alert:**

- Company admin if sync delayed > 2 hours due to rate limits
- Athena team if consistent rate limit issues
- Suggest integration quota upgrade if chronic issue