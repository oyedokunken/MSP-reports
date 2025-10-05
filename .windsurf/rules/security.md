---
trigger: always_on
---

## Security & Compliance

### ISO 27001 Requirements

**Access Control:**

- Role-based permissions at user and API key level
- Principle of least privilege enforced
- Multi-factor authentication support
- Session timeout based on company policy
- IP whitelisting optional per company

**Audit Logging:**

- Immutable audit trail for all actions
- User logins, permission changes, data access, configuration changes
- API calls logged with actor (user or API key)
- Retention: 7 years minimum for compliance
- Cannot be deleted even by company admins

**Data Protection:**

- Encryption at rest and in transit
- Secure credential storage with per-company keys
- Data retention policies enforced automatically
- Secure deletion when retention expires
- Backup encryption

**Incident Response:**

- Automated alerts for security events
- Failed login tracking and lockout
- Rate limit violations logged
- Suspicious activity detection
- Breach notification procedures

### Compliance Monitoring

**Automated Checks:**

- Daily validation of encryption status
- Retention policy enforcement verification
- Orphaned data detection and cleanup
- Permission audit (users with excessive permissions)
- Integration credential rotation reminders

**Reporting:**

- Compliance dashboard per company
- Audit log export for external audits
- Data retention reports
- Access reports (who accessed what)