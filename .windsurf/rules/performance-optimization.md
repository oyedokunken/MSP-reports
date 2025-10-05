---
trigger: always_on
---

## Performance Optimization

### Database Optimization

**Indexes:**

- Compound indexes on common query patterns
- Partial indexes for frequently filtered subsets
- Regular index maintenance (REINDEX)

**Query Optimization:**

- TimescaleDB chunk pruning (automatic)
- Continuous aggregates for common report queries
- Query caching for expensive operations (5-15 min TTL)
- Explain analyze on slow queries
- Connection pooling

**Partitioning:**

- IntegrationData partitioned by time (automatic via TimescaleDB)
- Partition pruning on queries
- Compression on old partitions

### Application Optimization

**Caching Strategy:**

- Redis for session storage
- Cache report data for 5-15 minutes
- Cache company white-label settings
- Cache integration metadata
- Aggressive cache invalidation on updates

**Queue Management:**

- Separate queues by priority
- Scale workers based on queue depth
- Job timeout policies
- Failed job retry policies

**Asset Optimization:**

- Minified JavaScript/CSS
- Image optimization and CDN (SaaS)
- Lazy loading for large datasets
- Pagination on all list views

### Scaling Strategy

**Vertical Scaling (First):**

- Increase server resources as needed
- Sufficient for 10-20 companies

**Horizontal Scaling (SaaS):**

- Application servers behind load balancer
- Database read replicas for reporting
- Separate queue workers on dedicated instances
- Object storage for files (S3)