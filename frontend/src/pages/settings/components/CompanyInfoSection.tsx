import { Button } from '@/components/ui/button'
import { LogoUpload } from '@/components/ui/LogoUpload'

interface CompanyInfoSectionProps {
  companyName: string
  primaryHex: string
  secondaryHex: string
  contactEmail: string
  contactPhone: string
  address: string
  logoUrl: string | null
  faviconUrl: string | null
  supportEmail: string
  supportPhone: string
  footerText: string
  reportHeaderText: string
  isValidHex: boolean
  isValidSecondaryHex: boolean
  saving?: boolean
  onCompanyNameChange: (name: string) => void
  onPrimaryHexChange: (hex: string) => void
  onSecondaryHexChange: (hex: string) => void
  onContactEmailChange: (email: string) => void
  onContactPhoneChange: (phone: string) => void
  onAddressChange: (address: string) => void
  onLogoUrlChange: (url: string | null) => void
  onFaviconUrlChange: (url: string | null) => void
  onSupportEmailChange: (email: string) => void
  onSupportPhoneChange: (phone: string) => void
  onFooterTextChange: (text: string) => void
  onReportHeaderTextChange: (text: string) => void
  onSave: () => void
}

export function CompanyInfoSection({
  companyName,
  primaryHex,
  secondaryHex,
  contactEmail,
  contactPhone,
  address,
  logoUrl,
  faviconUrl,
  supportEmail,
  supportPhone,
  footerText,
  reportHeaderText,
  isValidHex,
  isValidSecondaryHex,
  saving = false,
  onCompanyNameChange,
  onPrimaryHexChange,
  onSecondaryHexChange,
  onContactEmailChange,
  onContactPhoneChange,
  onAddressChange,
  onLogoUrlChange,
  onFaviconUrlChange,
  onSupportEmailChange,
  onSupportPhoneChange,
  onFooterTextChange,
  onReportHeaderTextChange,
  onSave,
}: CompanyInfoSectionProps) {
  return (
    <section className="rounded-lg border border-gray-200 dark:border-0 bg-white dark:bg-[hsl(var(--auth-card))] p-5 shadow-sm">
      <h2 className="text-base font-semibold text-foreground">Company Information</h2>
      <p className="mt-1 text-sm text-muted-foreground">Update your company details and branding</p>

      <div className="mt-4 grid gap-4 sm:grid-cols-2">
        <div className="sm:col-span-2">
          <label className="block text-sm font-medium text-foreground">Company Name</label>
          <input
            type="text"
            placeholder="Your Company Name"
            value={companyName}
            onChange={(e) => onCompanyNameChange(e.target.value)}
            className="mt-1 w-full rounded-md border border-input bg-background px-3 py-2 text-sm text-foreground placeholder-muted-foreground focus:border-ring focus:outline-none focus:ring-2 focus:ring-ring"
          />
        </div>

        <div>
          <label className="block text-sm font-medium text-foreground">Contact Email</label>
          <input
            type="email"
            placeholder="contact@company.com"
            value={contactEmail}
            onChange={(e) => onContactEmailChange(e.target.value)}
            className="mt-1 w-full rounded-md border border-input bg-background px-3 py-2 text-sm text-foreground placeholder-muted-foreground focus:border-ring focus:outline-none focus:ring-2 focus:ring-ring"
          />
        </div>

        <div>
          <label className="block text-sm font-medium text-foreground">Contact Phone</label>
          <input
            type="tel"
            placeholder="+61 2 1234 5678"
            value={contactPhone}
            onChange={(e) => onContactPhoneChange(e.target.value)}
            className="mt-1 w-full rounded-md border border-input bg-background px-3 py-2 text-sm text-foreground placeholder-muted-foreground focus:border-ring focus:outline-none focus:ring-2 focus:ring-ring"
          />
        </div>

        <div className="sm:col-span-2">
          <label className="block text-sm font-medium text-foreground">Address</label>
          <textarea
            placeholder="Company address"
            value={address}
            onChange={(e) => onAddressChange(e.target.value)}
            rows={3}
            className="mt-1 w-full rounded-md border border-input bg-background px-3 py-2 text-sm text-foreground placeholder-muted-foreground focus:border-ring focus:outline-none focus:ring-2 focus:ring-ring resize-none"
          />
        </div>

        {/* Brand Colors */}
        <div className="sm:col-span-2">
          <h3 className="text-sm font-semibold text-foreground mb-3">Brand Colors</h3>
          <div className="grid gap-4 sm:grid-cols-2">
            <div>
              <label className="block text-sm font-medium text-foreground mb-2">Primary Color</label>
              <div className="flex items-center gap-3">
                <input
                  aria-label="Primary color"
                  type="color"
                  value={isValidHex ? primaryHex : '#3b82f6'}
                  onChange={(e) => onPrimaryHexChange(e.target.value)}
                  className="h-9 w-12 rounded-md border border-border bg-background"
                />
                <input
                  aria-label="Primary color hex"
                  type="text"
                  value={primaryHex}
                  onChange={(e) => onPrimaryHexChange(e.target.value.startsWith('#') ? e.target.value : '#' + e.target.value)}
                  className="flex-1 rounded-md border border-input bg-background px-3 py-2 text-sm text-foreground placeholder-muted-foreground focus:border-ring focus:outline-none focus:ring-2 focus:ring-ring"
                  placeholder="#3b82f6"
                />
              </div>
            </div>

            <div>
              <label className="block text-sm font-medium text-foreground mb-2">Secondary Color</label>
              <div className="flex items-center gap-3">
                <input
                  aria-label="Secondary color"
                  type="color"
                  value={isValidSecondaryHex ? secondaryHex : '#7c3aed'}
                  onChange={(e) => onSecondaryHexChange(e.target.value)}
                  className="h-9 w-12 rounded-md border border-border bg-background"
                />
                <input
                  aria-label="Secondary color hex"
                  type="text"
                  value={secondaryHex}
                  onChange={(e) => onSecondaryHexChange(e.target.value.startsWith('#') ? e.target.value : '#' + e.target.value)}
                  className="flex-1 rounded-md border border-input bg-background px-3 py-2 text-sm text-foreground placeholder-muted-foreground focus:border-ring focus:outline-none focus:ring-2 focus:ring-ring"
                  placeholder="#7c3aed"
                />
              </div>
            </div>
          </div>
        </div>

        {/* Logo & Favicon */}
        <div className="sm:col-span-2">
          <h3 className="text-sm font-semibold text-foreground mb-3">Branding Assets</h3>
          <div className="space-y-6">
            {/* Logo Upload Component */}
            <LogoUpload
              type="company"
              currentLogoUrl={logoUrl}
              onUploadSuccess={(url) => onLogoUrlChange(url)}
              onDeleteSuccess={() => onLogoUrlChange(null)}
            />

            {/* Logo URL Input (Alternative) */}
            <div>
              <label className="block text-sm font-medium text-foreground">Or enter Logo URL</label>
              <input
                type="url"
                placeholder="https://example.com/logo.png"
                value={logoUrl || ''}
                onChange={(e) => onLogoUrlChange(e.target.value || null)}
                className="mt-1 w-full rounded-md border border-input bg-background px-3 py-2 text-sm text-foreground placeholder-muted-foreground focus:border-ring focus:outline-none focus:ring-2 focus:ring-ring"
              />
              <p className="mt-1 text-xs text-muted-foreground">Used in sidebar, reports, and emails</p>
            </div>

            {/* Favicon Upload Component */}
            <LogoUpload
              type="profile"
              currentLogoUrl={faviconUrl}
              onUploadSuccess={(url) => onFaviconUrlChange(url)}
              onDeleteSuccess={() => onFaviconUrlChange(null)}
            />

            {/* Favicon URL Input (Alternative) */}
            <div>
              <label className="block text-sm font-medium text-foreground">Or enter Favicon URL</label>
              <input
                type="url"
                placeholder="https://example.com/favicon.ico"
                value={faviconUrl || ''}
                onChange={(e) => onFaviconUrlChange(e.target.value || null)}
                className="mt-1 w-full rounded-md border border-input bg-background px-3 py-2 text-sm text-foreground placeholder-muted-foreground focus:border-ring focus:outline-none focus:ring-2 focus:ring-ring"
              />
              <p className="mt-1 text-xs text-muted-foreground">Browser tab icon</p>
            </div>
          </div>
        </div>

        {/* Support Contact */}
        <div className="sm:col-span-2">
          <h3 className="text-sm font-semibold text-foreground mb-3">Support Contact</h3>
          <div className="grid gap-4 sm:grid-cols-2">
            <div>
              <label className="block text-sm font-medium text-foreground">Support Email</label>
              <input
                type="email"
                placeholder="support@company.com"
                value={supportEmail}
                onChange={(e) => onSupportEmailChange(e.target.value)}
                className="mt-1 w-full rounded-md border border-input bg-background px-3 py-2 text-sm text-foreground placeholder-muted-foreground focus:border-ring focus:outline-none focus:ring-2 focus:ring-ring"
              />
            </div>

            <div>
              <label className="block text-sm font-medium text-foreground">Support Phone</label>
              <input
                type="tel"
                placeholder="1300 XXX XXX"
                value={supportPhone}
                onChange={(e) => onSupportPhoneChange(e.target.value)}
                className="mt-1 w-full rounded-md border border-input bg-background px-3 py-2 text-sm text-foreground placeholder-muted-foreground focus:border-ring focus:outline-none focus:ring-2 focus:ring-ring"
              />
            </div>
          </div>
        </div>

        {/* Report Branding */}
        <div className="sm:col-span-2">
          <h3 className="text-sm font-semibold text-foreground mb-3">Report Branding</h3>
          <div className="grid gap-4">
            <div>
              <label className="block text-sm font-medium text-foreground">Report Header Text</label>
              <input
                type="text"
                placeholder="Monthly Service Report"
                value={reportHeaderText}
                onChange={(e) => onReportHeaderTextChange(e.target.value)}
                className="mt-1 w-full rounded-md border border-input bg-background px-3 py-2 text-sm text-foreground placeholder-muted-foreground focus:border-ring focus:outline-none focus:ring-2 focus:ring-ring"
              />
            </div>

            <div>
              <label className="block text-sm font-medium text-foreground">Footer Text</label>
              <input
                type="text"
                placeholder="© 2025 Your Company"
                value={footerText}
                onChange={(e) => onFooterTextChange(e.target.value)}
                className="mt-1 w-full rounded-md border border-input bg-background px-3 py-2 text-sm text-foreground placeholder-muted-foreground focus:border-ring focus:outline-none focus:ring-2 focus:ring-ring"
              />
            </div>
          </div>
        </div>
      </div>

      <div className="mt-6">
        <Button
          onClick={onSave}
          disabled={!isValidHex || !isValidSecondaryHex || saving}
        >
          {saving ? 'Saving...' : 'Save Changes'}
        </Button>
      </div>
    </section>
  )
}
