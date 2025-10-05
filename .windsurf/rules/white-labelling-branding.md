---
trigger: always_on
---

## White Labeling & Branding

### Multi-Level Branding System

**Level 1: Company (MSP/Telco) Branding**

- Applied to all UI, emails, reports for that company's users
- Stored in Company settings JSON
- Includes: logos, colors, contact info, email templates

**Level 2: Customer-Specific Overrides**

- Applied to reports delivered to specific customer
- Stored in Customer metadata JSON
- Includes: customer logo, custom report title, footer notes
- Optional - defaults to company branding

### UI White-Labeling

**Frontend:**

- React app loads branding from API on initialization
- CSS variables set dynamically (primary color, secondary color)
- Logo loaded into header
- Favicon set dynamically
- Page titles use company name

**Authentication Pages:**

- Login page fully branded
- Password reset emails use company branding
- MFA setup screens branded

### Report Branding

**PDF/HTML Templates:**

- Header: Company logo + customer logo (if provided)
- Colors: Company's primary/secondary colors for charts/accents
- Footer: Company contact info + customer-specific notes
- Watermark: Subtle company branding

**Email Delivery:**

- Email template uses company branding
- From address: Company's email
- From name: Company's name
- Email body text uses company colors
- Footer includes company support contact

### Configuration Fields

See Company settings JSON in Data Models section for complete white_label structure.

### On-Premise Considerations

**First Customer:**

- Set white-label config during deployment
- Can update via UI if needed
- All assets (logos, favicons) stored locally or in their storage

**SaaS Future:**

- Subdomain routing: customermsp.reportingplatform.com.au
- Subdomain determines which company branding to load
- Asset storage in object storage with company-namespaced paths