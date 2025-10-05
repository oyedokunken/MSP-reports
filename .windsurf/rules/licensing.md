---
trigger: always_on
---

## Licensing System

### License Types

**Integration Licenses:**

- Per integration type (Datto, Autotask, Kaseya, etc.)
- Controls which integrations can be activated
- SKU example: "integration_datto", "integration_autotask"

**Retention Licenses:**

- Controls historical data retention period
- SKU example: "retention_12_months", "retention_36_months", "retention_unlimited"

**User Licenses (Future):**

- Per-seat licensing if business model changes
- Currently unlimited users per company

**Feature Licenses (Future):**

- Advanced report types
- API access levels
- Custom integrations

### License Enforcement

**Check Points:**

1. **Integration Activation:** Verify license exists for integration type
2. **Historical Import:** Verify retention license allows import range
3. **Report Generation:** Verify license for report type (if premium reports added)
4. **User Creation:** Verify user license quota (if per-seat model)

**Enforcement Behavior:**

- If license valid: Allow action
- If license expired: Grace period (7 days?) then block
- If license missing: Show "Contact sales" message in UI
- If license insufficient: Offer upgrade option

**First Customer Configuration:**

- License SKU: "unlimited"
- Expiry date: null (perpetual)
- Quantity: unlimited
- All checks return "allowed"

**SaaS Configuration:**

- Specific licenses per company
- Expiry dates enforced
- Integration with billing system
- Automatic license expiry notifications

### License Model

See License Model in Data Models section for attributes and relationships.

### License Management UI

**For Company Admins:**

- View current licenses
- See expiry dates
- Request license additions/upgrades
- View license usage (e.g., 3 of 5 integrations activated)

**For Athena (SaaS):**

- Admin panel to manage all company licenses
- Bulk license operations
- License renewal workflows
- Usage analytics (which integrations most popular)