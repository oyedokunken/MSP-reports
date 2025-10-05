---
trigger: always_on
---

## First Customer Deployment

### Pre-Deployment

**Infrastructure Requirements:**

- Docker and Docker Compose installed
- Adequate storage (estimate 10GB + 1GB/month growth)
- PostgreSQL volume encryption configured
- TLS certificate for domain
- SMTP credentials for email delivery
- Backup storage configured

**Configuration Preparation:**

- White-label settings gathered (logo, colors, contact info)
- Autotask API credentials obtained
- Customer list exported for mapping validation
- Report requirements documented
- Schedule requirements documented

### Deployment Process

**Step 1: Server Setup**

- Provision server (on-premise at customer location)
- Install Docker and dependencies
- Configure firewall rules
- Set up TLS certificate

**Step 2: Application Deployment**

- Clone repository or deploy release package
- Configure environment variables
- Start Docker Compose stack
- Verify services healthy

**Step 3: Database Initialization**

- Run migrations (creates all tables and hypertables)
- Seed first company record with unlimited license
- Create first admin user
- Configure white-label settings

**Step 4: Integration Setup**

- Add Autotask integration via UI
- Enter Autotask API credentials
- Test connection (health check)
- Initiate first current-state sync
- Verify customer data importing

**Step 5: Historical Import**

- Configure historical import range (90 days recommended initially)
- Start historical import job
- Monitor progress (will run async over 24-48 hours)
- Verify data appearing in database

**Step 6: Customer Mapping**

- Review auto-matched customers (approve or adjust)
- Manually map unmapped customers
- Designate Autotask as source of truth if appropriate
- Verify all customers mapped

**Step 7: Report Configuration**

- Add first report type configuration
- Create report schedules for customers
- Configure delivery destinations
- Test generate one report manually
- Verify PDF quality and branding

**Step 8: Schedule Testing**

- Set test schedule (near-term)
- Wait for schedule trigger
- Verify report generates automatically
- Verify email delivers
- Confirm customer receives professional report

**Step 9: Training**

- Train customer admins on platform
- Demonstrate integration management
- Show customer mapping process
- Explain report configuration
- Document common tasks

**Step 10: Go-Live**

- Enable production schedules
- Monitor first week closely
- Respond to feedback quickly
- Document lessons learned

### Post-Deployment

**Week 1-2 Monitoring:**

- Daily check of sync status
- Daily check of report generation success
- Review error logs
- Check historical import progress
- Respond to customer questions

**Week 3-4 Optimization:**

- Tune sync schedules based on usage patterns
- Optimize slow queries if needed
- Adjust rate limiting if issues
- Refine customer mappings
- Adjust report templates based on feedback

**Ongoing Support:**

- Weekly health checks
- Monthly data retention review
- Quarterly platform updates
- Continuous feature requests and improvements