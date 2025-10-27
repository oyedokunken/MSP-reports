import { Button } from '@/components/ui/button'

interface CompanyInfoSectionProps {
  companyName: string
  primaryHex: string
  isValidHex: boolean
  onCompanyNameChange: (name: string) => void
  onPrimaryHexChange: (hex: string) => void
  onSave: () => void
}

export function CompanyInfoSection({
  companyName,
  primaryHex,
  isValidHex,
  onCompanyNameChange,
  onPrimaryHexChange,
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
            className="mt-1 w-full rounded-md border border-input bg-background dark:bg-muted/20 px-3 py-2 text-sm text-foreground placeholder-muted-foreground focus:border-ring focus:outline-none focus:ring-2 focus:ring-ring"
          />
        </div>

        <div className="flex items-center gap-3">
          <label className="block text-sm font-medium text-foreground">Primary Brand Color</label>
        </div>
        <div className="flex items-center gap-3">
          <input
            aria-label="Primary color"
            type="color"
            value={isValidHex ? primaryHex : '#3b82f6'}
            onChange={(e) => onPrimaryHexChange(e.target.value)}
            className="h-9 w-12 rounded-md border border-border bg-background dark:bg-muted/20"
          />
          <input
            aria-label="Primary color hex"
            type="text"
            value={primaryHex}
            onChange={(e) => onPrimaryHexChange(e.target.value.startsWith('#') ? e.target.value : '#' + e.target.value)}
            className="w-36 rounded-md border border-input bg-background dark:bg-muted/20 px-3 py-2 text-sm text-foreground placeholder-muted-foreground focus:border-ring focus:outline-none focus:ring-2 focus:ring-ring"
            placeholder="#3b82f6"
          />
        </div>
      </div>

      <div className="mt-4">
        <Button
          onClick={onSave}
          disabled={!isValidHex}
        >
          Save Changes
        </Button>
      </div>
    </section>
  )
}
