---
trigger: always_on
---

## Monitoring & Maintenance

### Application Monitoring

**Health Checks:**

- API endpoint health (200 OK)
- Database connectivity
- Redis connectivity
- Queue worker status
- Disk space available

**Integration Monitoring:**

- Sync success rates per integration
- API rate limit usage
- Historical import progress
- Error rates and types
- Response times

**Report Monitoring:**

- Generation success rates
- Delivery success rates
- Generation time (performance)
- Failed reports and reasons
- Schedule adherence

**System Monitoring:**

- CPU/memory usage
- Disk I/O
- Network bandwidth
- PostgreSQL query performance
- Queue depth and processing rate

### Alerting

**Critical Alerts (Immediate):**

- Database connectivity lost
- Disk space <10%
- All integrations failing
- Report generation failing for >6 hours
- Security events (unauthorized access attempts)

**Warning Alerts (Review within 4 hours):**

- Integration sync failures (single integration)
- Rate limit repeatedly hit
- Queue depth growing
- Report delivery failures
- Slow query performance

**Info Alerts (Review daily):**

- Historical import completed
- Retention cleanup executed
- New customer signup (SaaS)
- License expiring soon

### Maintenance Tasks

**Daily:**

- Review error logs
- Check sync statuses
- Verify report deliveries
- Monitor disk usage

**Weekly:**

- Review performance metrics
- Check for slow queries
- Review security logs
- Customer feedback review

**Monthly:**

- Database maintenance (VACUUM, REINDEX)
- Backup restoration test
- Security updates
- Dependency updates
- Capacity planning review

**Quarterly:**

- Comprehensive security audit
- Performance optimization
- Feature prioritization
- Customer satisfaction survey