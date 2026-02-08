import { Button } from '@/components/ui/button'
import { LogoUpload } from '@/components/ui/LogoUpload'

interface CompanyInfoSectionProps {
  companyName: string
  primaryHex: string
  secondaryHex: string
  contactEmail: string
  contactPhone: string
  address: string
  websiteUrl: string
  logoUrl: string | null
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
  onWebsiteUrlChange: (url: string) => void
  onLogoUrlChange: (url: string | null) => void
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
  websiteUrl,
  logoUrl,
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
  onWebsiteUrlChange,
  onLogoUrlChange,
  onSupportEmailChange,
  onSupportPhoneChange,
  onFooterTextChange,
  onReportHeaderTextChange,
  onSave,
}: CompanyInfoSectionProps) {
  return (
    <section className="rounded-lg border border-gray-200 dark:border-0 bg-white dark:bg-[hsl(var(--auth-card))] p-5 shadow-sm">
      <div className="flex items-start border-b pb-2 justify-between">
        <div>
          <h2 className="text-base font-semibold text-foreground">Company Information</h2>
          <p className="mt-1 text-sm text-muted-foreground">Update your company details and branding</p>
        </div>
        <Button
          onClick={onSave}
          disabled={!isValidHex || !isValidSecondaryHex || saving}
          size="sm"
        >
          {saving ? 'Saving...' : 'Save Changes'}
        </Button>
      </div>

      <div className="mt-8 relative">
        {/* Continuous Vertical Divider (Desktop Only) */}
        <div className="absolute left-[33.333333%] top-0 bottom-0 w-px bg-border hidden md:block" />

        <div className="space-y-0">
          {/* Company Name */}
          <div className="grid grid-cols-1 md:grid-cols-12 items-center">
            <div className="md:col-span-4 p-4 md:py-4 md:pr-6">
              <label className="text-sm font-semibold text-foreground">Company Name</label>
              <p className="text-xs text-muted-foreground mt-1">The official name of your organization.</p>
            </div>
            <div className="md:col-span-8 p-4 md:p-4 md:pl-6">
              <input
                type="text"
                placeholder="Your Company Name"
                value={companyName}
                onChange={(e) => onCompanyNameChange(e.target.value)}
                className="w-full rounded-md border border-input bg-background px-3 py-2 text-sm text-foreground placeholder-muted-foreground focus:border-ring focus:outline-none focus:ring-2 focus:ring-ring"
              />
            </div>
          </div>

          {/* Website URL */}
          <div className="grid grid-cols-1 md:grid-cols-12 items-center">
            <div className="md:col-span-4 p-4 md:py-4 md:pr-6">
              <label className="text-sm font-semibold text-foreground">Website URL</label>
              <p className="text-xs text-muted-foreground mt-1">Your organization's public website.</p>
            </div>
            <div className="md:col-span-8 p-4 md:p-4 md:pl-6">
              <input
                type="url"
                placeholder="https://www.yourcompany.com"
                value={websiteUrl}
                onChange={(e) => onWebsiteUrlChange(e.target.value)}
                className="w-full rounded-md border border-input bg-background px-3 py-2 text-sm text-foreground placeholder-muted-foreground focus:border-ring focus:outline-none focus:ring-2 focus:ring-ring"
              />
            </div>
          </div>

          {/* Contact Email & Phone */}
          <div className="grid grid-cols-1 md:grid-cols-12 items-center">
            <div className="md:col-span-4 p-4 md:py-4 md:pr-6">
              <label className="text-sm font-semibold text-foreground">Contact Details</label>
              <p className="text-xs text-muted-foreground mt-1">Public email and phone for contact.</p>
            </div>
            <div className="md:col-span-8 p-4 md:p-4 md:pl-6">
              <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <input
                  type="email"
                  placeholder="contact@company.com"
                  value={contactEmail}
                  onChange={(e) => onContactEmailChange(e.target.value)}
                  className="w-full rounded-md border border-input bg-background px-3 py-2 text-sm text-foreground placeholder-muted-foreground focus:border-ring focus:outline-none focus:ring-2 focus:ring-ring"
                />
                <input
                  type="tel"
                  placeholder="+61 2 1234 5678"
                  value={contactPhone}
                  onChange={(e) => onContactPhoneChange(e.target.value)}
                  className="w-full rounded-md border border-input bg-background px-3 py-2 text-sm text-foreground placeholder-muted-foreground focus:border-ring focus:outline-none focus:ring-2 focus:ring-ring"
                />
              </div>
            </div>
          </div>

          {/* Address */}
          <div className="grid grid-cols-1 md:grid-cols-12 items-center">
            <div className="md:col-span-4 p-4 md:py-4 md:pr-6">
              <label className="text-sm font-semibold text-foreground">Address</label>
              <p className="text-xs text-muted-foreground mt-1">Your registered business address.</p>
            </div>
            <div className="md:col-span-8 p-4 md:p-4 md:pl-6">
              <textarea
                placeholder="Company address"
                value={address}
                onChange={(e) => onAddressChange(e.target.value)}
                rows={2}
                className="w-full rounded-md border border-input bg-background px-3 py-2 text-sm text-foreground placeholder-muted-foreground focus:border-ring focus:outline-none focus:ring-2 focus:ring-ring resize-none"
              />
            </div>
          </div>

          {/* Brand Colors */}
          <div className="grid grid-cols-1 md:grid-cols-12 items-center">
            <div className="md:col-span-4 p-4 md:py-4 md:pr-6">
              <label className="text-sm font-semibold text-foreground">Brand Colors</label>
              <p className="text-xs text-muted-foreground mt-1">Primary and secondary colors for your workspace.</p>
            </div>
            <div className="md:col-span-8 p-4 md:p-4 md:pl-6">
              <div className="grid grid-cols-1 sm:grid-cols-2 gap-6">
                <div className="space-y-2">
                  <span className="text-xs font-medium text-muted-foreground">Primary Color</span>
                  <div className="flex items-center gap-2">
                    <input
                      aria-label="Primary color"
                      type="color"
                      value={isValidHex ? primaryHex : '#3b82f6'}
                      onChange={(e) => onPrimaryHexChange(e.target.value)}
                      className="h-9 w-12 rounded-md border border-border bg-background p-0 cursor-pointer"
                    />
                    <input
                      aria-label="Primary color hex"
                      type="text"
                      value={primaryHex}
                      onChange={(e) => onPrimaryHexChange(e.target.value.startsWith('#') ? e.target.value : '#' + e.target.value)}
                      className="flex-1 rounded-md border border-input bg-background px-3 py-2 text-sm text-foreground font-mono"
                      placeholder="#3b82f6"
                    />
                  </div>
                </div>
                <div className="space-y-2">
                  <span className="text-xs font-medium text-muted-foreground">Secondary Color</span>
                  <div className="flex items-center gap-2">
                    <input
                      aria-label="Secondary color"
                      type="color"
                      value={isValidSecondaryHex ? secondaryHex : '#7c3aed'}
                      onChange={(e) => onSecondaryHexChange(e.target.value)}
                      className="h-9 w-12 rounded-md border border-border bg-background p-0 cursor-pointer"
                    />
                    <input
                      aria-label="Secondary color hex"
                      type="text"
                      value={secondaryHex}
                      onChange={(e) => onSecondaryHexChange(e.target.value.startsWith('#') ? e.target.value : '#' + e.target.value)}
                      className="flex-1 rounded-md border border-input bg-background px-3 py-2 text-sm text-foreground font-mono"
                      placeholder="#7c3aed"
                    />
                  </div>
                </div>
              </div>
            </div>
          </div>

          {/* Logo Configuration */}
          <div className="grid grid-cols-1 md:grid-cols-12 items-center">
            <div className="md:col-span-4 p-4 md:py-4 md:pr-6">
              <label className="text-sm font-semibold text-foreground">Workspace Logo</label>
              <p className="text-xs text-muted-foreground mt-1">Upload or link your organization's logo.</p>
            </div>
            <div className="md:col-span-8 p-4 md:p-8 md:pl-10 space-y-4">
              <input
                type="url"
                placeholder="https://link-to-your-logo.com/logo.png"
                value={logoUrl || ''}
                onChange={(e) => onLogoUrlChange(e.target.value || null)}
                className="w-full rounded-md border border-input bg-background px-3 py-2 text-sm text-foreground"
              />
              <LogoUpload
                type="company"
                currentLogoUrl={logoUrl}
                onUploadSuccess={(url) => onLogoUrlChange(url)}
                onDeleteSuccess={() => onLogoUrlChange(null)}
              />
            </div>
          </div>

          {/* Support & Reporting */}
          <div className="grid grid-cols-1 md:grid-cols-12 items-center">
            <div className="md:col-span-4 p-4 md:py-4 md:pr-6">
              <label className="text-sm font-semibold text-foreground">Support & Reporting</label>
              <p className="text-xs text-muted-foreground mt-1">Configure support contact and report branding.</p>
            </div>
            <div className="md:col-span-8 p-4 md:p-8 md:pl-10 space-y-6">
              <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div className="space-y-2">
                  <span className="text-xs font-medium text-muted-foreground">Support Email</span>
                  <input
                    type="email"
                    placeholder="support@company.com"
                    value={supportEmail}
                    onChange={(e) => onSupportEmailChange(e.target.value)}
                    className="w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                  />
                </div>
                <div className="space-y-2">
                  <span className="text-xs font-medium text-muted-foreground">Support Phone</span>
                  <input
                    type="tel"
                    placeholder="1300 ..."
                    value={supportPhone}
                    onChange={(e) => onSupportPhoneChange(e.target.value)}
                    className="w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                  />
                </div>
              </div>

              <div className="space-y-2">
                <span className="text-xs font-medium text-muted-foreground">Report Header Text</span>
                <input
                  type="text"
                  placeholder="Monthly Service Report"
                  value={reportHeaderText}
                  onChange={(e) => onReportHeaderTextChange(e.target.value)}
                  className="w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                />
              </div>

              <div className="space-y-2">
                <span className="text-xs font-medium text-muted-foreground">Footer Text</span>
                <input
                  type="text"
                  placeholder="© 2025 Your Company"
                  value={footerText}
                  onChange={(e) => onFooterTextChange(e.target.value)}
                  className="w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                />
              </div>
            </div>
          </div>
        </div>
      </div>

    </section>
  )
}
