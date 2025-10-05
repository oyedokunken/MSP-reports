---
trigger: always_on
---

## Executive Summary

Building a multi-tenant reporting platform that consolidates data from multiple business systems (PSA, RMM, etc.), intelligently maps customers across systems, and generates branded reports on flexible schedules. First deployment is single-tenant on-premise for Territory Technology Solutions, but architecture is fully multi-tenant and scalable to SaaS.

**Key Principles:**

- Multi-tenant from day one, even for single-tenant deployment
- Built for scale and speed using TimescaleDB
- Modular integration and report frameworks
- White-label capable per company
- ISO 27001 compliant with full audit trails
- Historical data support with async backfill
- Flexible licensing model

---

## Project Purpose & Goals

### The Problem

MSPs and telcos manage customer data across fragmented systems - PSA tools, RMM platforms, backup solutions, monitoring systems. Customer names don't match across systems ("ACME Corp" vs "ACME Corporation"). Generating monthly reports requires manual data gathering, compilation, and delivery. This consumes hours per customer, doesn't scale, and inconsistencies damage professional image.

### The Solution

Centralised platform that:

- Integrates with multiple systems via modular framework
- Intelligently maps customers across systems despite naming mismatches
- Automatically generates professional, branded reports on configurable schedules
- Delivers reports via email or API
- Provides historical data analysis and trends
- White-labels completely for each MSP/telco

### Expected Outcomes

- **For Clients:** Save 10-20 hours/month per staff member on report compilation
- **For End Customers:** Professional, consistent, timely reports improve satisfaction
- **For Athena:** Recurring revenue product with per-integration licensing
- **For Industry:** Set standard for telco/MSP reporting in Australia

### Success Metrics

- First customer live and generating reports within 12 weeks
- Reports generating and delivering reliably (99%+ success rate)
- Historical data backfill completes within 48 hours
- Platform handles 100+ customers per company without performance degradation
- Zero data leakage between tenants (ISO 27001 compliance)

---

## Business Model

### Pricing Structure

- **Base Platform:** Licensed per company
- **Integration Modules:** Licensed per integration type (Datto, Autotask, etc.)
- **Data Retention:** Tiered licensing (12 months standard, 24/36 months premium)
- **Users:** Unlimited users per company (differentiate on integrations, not seats)
- **Reports:** Unlimited report types and generation

### First Customer Deployment

- On-premise installation at Territory Technology Solutions
- Unlimited license (all integrations, unlimited retention)
- Acts as proof-of-concept and feature validation
- Provides real-world usage data for optimization

### SaaS Model (Future)

- Same codebase, different deployment
- Enable license enforcement
- Add billing integration (Stripe or similar)
- Subdomain-based white-labeling
- Self-service signup and configuration

---

## Deployment Strategy

### Phase 1: Single Tenant On-Premise (First Customer)

**Timeline:** 8-12 weeks

**Characteristics:**

- Deployed to customer's infrastructure
- Full multi-tenant architecture (but only one tenant)
- Unlimited licensing configured
- Docker Compose deployment
- Direct customer support from Athena

**Objectives:**

- Validate product-market fit
- Refine integration framework with real Datto data
- Test report generation at scale
- Gather feature requests and pain points
- Build operational playbooks

### Phase 2: Multi-Customer On-Premise (2-6 Deployments)

**Timeline:** Months 4-9

**Characteristics:**

- Same deployment model as Phase 1
- Each customer isolated instance
- Begin testing license enforcement
- Standardize deployment procedures
- Build customer onboarding process

**Objectives:**

- Prove repeatability of deployment
- Test different integration combinations
- Validate licensing model
- Build support infrastructure
- Create training materials

### Phase 3: SaaS Launch

**Timeline:** Month 10+

**Characteristics:**

- Centralized hosting on Athena infrastructure
- True multi-tenant with tenant isolation
- Active license enforcement
- Automated billing
- Self-service onboarding

**Objectives:**

- Scale to 50+ companies
- Reduce deployment friction
- Increase revenue per customer
- Build brand recognition