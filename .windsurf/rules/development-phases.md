---
trigger: always_on
---

## Development Phases

### Phase 1: Foundation

**Objectives:**

- Multi-tenant architecture established
- Authentication and authorization working
- Database migrations complete
- White-labeling system functional

**Deliverables:**

- Company model with settings JSON
- User model with granular permissions
- API key system functional
- License checking (always returns true for first customer)
- Login/logout with local authentication
- UI loads white-label branding from API
- PostgreSQL/TimescaleDB running with encryption
- Laravel backend with tenant-scoped queries
- React frontend with Tailwind styling

**Not Included Yet:**

- Integrations (comes next phase)
- Reports (comes after integrations)
- Historical data (comes with integrations)

---

### Phase 2: Integration Framework & Autotask

**Objectives:**

- Integration framework contracts defined
- Autotask integration adapted (already built for another project, just needs to be adapted) and functional
- Current-state syncs working
- Historical data architecture built
- Rate limiting implemented

**Deliverables:**

- Integration contract interface
- Integration registration system
- IntegrationCustomer model and storage
- Autotask integration namespace with all Autotask products
- Sync engine scheduling and executing syncs
- SyncLog tracking
- IntegrationData hypertable (TimescaleDB)
- Historical import job system with chunking
- Rate limiter service with per-integration limits
- Progress tracking for historical imports
- UI for integration management (add/configure/sync)

**Success Criteria:**

- Can connect to first customer's Autotask instance
- Current sync pulls customer list successfully
- Historical import begins and makes progress
- No API rate limit violations
- Data visible in UI

---

### Phase 3: Customer Mapping

**Objectives:**

- Auto-matching algorithm working
- Manual mapping UI functional
- Source of truth integration designated

**Deliverables:**

- Customer model
- CustomerMapping model
- Auto-match algorithm with fuzzy matching
- Manual mapping UI showing unmapped customers
- Customer search and suggestion interface
- Bulk mapping operations
- Source of truth integration setting
- Customer merge/split tools

**Success Criteria:**

- Datto customers auto-map with >80% accuracy
- Remaining customers easy to map manually
- No duplicate customer records
- Mappings auditable in logs

---

### Phase 4: Report Framework & First Report

**Objectives:**

- Report framework contracts defined
- First report type implemented (customer's priority report)
- Report generation working with white-label branding
- Scheduling system functional

**Deliverables:**

- Report contract interface
- Report registration system
- ReportSchedule model with flexible scheduling
- GeneratedReport model and storage
- First report type (e.g., Monthly Uptime Report)
- PDF generation from template
- White-label branding in reports
- Customer-specific branding overrides
- Report generation engine
- Schedule evaluation engine (hourly check)
- TimescaleDB continuous aggregates for report queries

**Success Criteria:**

- Can generate first report type for mapped customer
- Report contains real data from Autotask
- Report branded correctly with customer's white-label settings
- PDF quality professional and readable
- Schedule creates and calculates next run correctly

---

### Phase 5: Report Delivery & Email

**Objectives:**

- Reports automatically deliver on schedule
- Email templates branded
- Delivery tracking working

**Deliverables:**

- Email delivery system with SMTP
- Email templates with white-label branding
- Delivery job queue and processing
- Delivery status tracking
- Failed delivery retry logic
- Delivery notifications to customer
- Email configuration in company settings

**Success Criteria:**

- Reports generate on schedule automatically
- Emails deliver successfully with branding
- Failed deliveries retry and notify
- Customers receive professional-looking report emails

---

### Phase 6: Data Retention & Compliance

**Objectives:**

- Retention policies enforced
- Cleanup automation working
- Compliance reporting available

**Deliverables:**

- TimescaleDB retention policies configured
- Nightly cleanup job (soft delete, hard delete, orphan cleanup)
- DataRetentionLog model and tracking
- Compliance dashboard showing retention status
- Purge notifications to admins

**Success Criteria:**

- Old data automatically purged per company settings
- Audit logs retained for 7 years
- No manual cleanup needed
- Compliance reports available for audit

---

### Phase 7: Polish & Production Prep

**Objectives:**

- UI/UX refined based on feedback
- Performance optimized
- Security hardened
- Documentation complete

**Deliverables:**

- UI polish and user feedback incorporation
- Query optimization (indexes, caching)
- Security audit and fixes
- Load testing (100+ customers, concurrent syncs)
- User documentation (admin guide, user manual)
- Deployment documentation
- Backup and restore procedures tested
- Monitoring and alerting configured
- Training materials for first customer

**Success Criteria:**

- UI intuitive and polished
- Performance acceptable under load
- No critical security issues
- First customer trained and ready to go live
- Deployment repeatable with documentation

---

### Phase 8: First Customer Go-Live

**Objectives:**

- Deploy to first customer's infrastructure
- Complete historical import
- Generate first production reports
- Validate success

**Deliverables:**

- On-premise deployment to customer servers
- Company record created with unlimited license
- Datto integration connected and synced
- Historical data import completed (or in progress)
- Customer mapping completed
- First reports generated and delivered
- Customer staff trained
- Support procedures established

**Success Criteria:**

- Platform running stable in production
- Reports generating and delivering successfully
- Customer satisfied with initial results
- No critical bugs or issues
- Support tickets minimal