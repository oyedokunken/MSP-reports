---
trigger: always_on
---

## Customer Mapping System

### Purpose

Unify customer records across multiple integrations despite naming inconsistencies. "ACME Corp", "ACME Corporation", "Acme Corp." are all the same customer.

### Auto-Matching Logic

**Algorithm:**

1. New IntegrationCustomer arrives from sync
2. Compare against existing Customers in same company
3. Calculate similarity score (Levenshtein distance, soundex, etc.)
4. If score >= threshold (from company settings, default 85%):
   - Create tentative CustomerMapping
   - If company requires manual review, mark as "pending approval"
   - If no manual review required, auto-approve mapping
5. If score < threshold:
   - Add to "unmapped customers" queue
   - Notify admin

**Confidence Score Factors:**

- Exact match = 100%
- Case-insensitive match = 95%
- Minor punctuation differences = 90%
- Word order changes = 85%
- Abbreviations = 80%
- Below threshold = manual review required

### Manual Mapping Interface

**User Workflow:**

1. View unmapped IntegrationCustomers
2. Search existing Customers
3. See suggested matches with confidence scores
4. Options:
   - Map to existing Customer
   - Create new Customer (truly new customer)
   - Ignore (not a customer, maybe a vendor)

**Bulk Operations:**

- Select multiple unmapped customers
- Apply same mapping rule (e.g., "Corp" → "Corporation")
- Approve all suggested matches above threshold

### Source of Truth Concept

**Designated Integration:**

- Company selects one integration as "source of truth" for customer list
- This integration's customer names become canonical
- Other integrations' customers map TO these

**Behavior:**

- Source of truth integration syncs first
- New customers from source → auto-create Customer record
- Other integrations' new customers → attempt mapping to existing
- If source of truth customer deleted → can cascade or orphan based on policy

**Benefits:**

- Solves "which name is correct?" problem
- Reduces mapping conflicts
- Simplifies onboarding (start with authoritative list)

### Customer Merge/Split Tools

**Merge:**

- Two Customers accidentally created for same real customer
- Merge operation:
  - Choose primary Customer (name, metadata)
  - Move all IntegrationCustomer mappings to primary
  - Move all ReportSchedules to primary
  - Soft-delete duplicate Customer
  - Audit log records merge

**Split:**

- One Customer accidentally mapped to two real customers
- Split operation:
  - Create new Customer
  - Reassign specific IntegrationCustomers to new Customer
  - Clone or reassign ReportSchedules
  - Audit log records split