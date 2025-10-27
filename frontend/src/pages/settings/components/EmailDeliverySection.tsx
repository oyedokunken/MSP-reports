import { Button } from '@/components/ui/button'

interface EmailDeliverySectionProps {
  fromEmail: string
  fromName: string
  onFromEmailChange: (email: string) => void
  onFromNameChange: (name: string) => void
  onSave: () => void
}

export function EmailDeliverySection({
  fromEmail,
  fromName,
  onFromEmailChange,
  onFromNameChange,
  onSave,
}: EmailDeliverySectionProps) {
  return (
    <section className="rounded-lg border border-gray-200 dark:border-0 bg-white dark:bg-[hsl(var(--auth-card))] p-5 shadow-sm">
      <h2 className="text-base font-semibold text-foreground">Email Delivery</h2>
      <p className="mt-1 text-sm text-muted-foreground">Configure email settings for report delivery</p>

      <div className="mt-4 grid gap-4 sm:grid-cols-2">
        <div>
          <label className="block text-sm font-medium text-foreground">From Email Address</label>
          <input
            type="email"
            placeholder="reports@yourcompany.com"
            value={fromEmail}
            onChange={(e) => onFromEmailChange(e.target.value)}
            className="mt-1 w-full rounded-md border border-input bg-background dark:bg-muted/20 px-3 py-2 text-sm text-foreground placeholder-muted-foreground focus:border-ring focus:outline-none focus:ring-2 focus:ring-ring"
          />
        </div>
        <div>
          <label className="block text-sm font-medium text-foreground">From Name</label>
          <input
            type="text"
            placeholder="Your Company Reports"
            value={fromName}
            onChange={(e) => onFromNameChange(e.target.value)}
            className="mt-1 w-full rounded-md border border-input bg-background dark:bg-muted/20 px-3 py-2 text-sm text-foreground placeholder-muted-foreground focus:border-ring focus:outline-none focus:ring-2 focus:ring-ring"
          />
        </div>
      </div>

      <div className="mt-4">
        <Button onClick={onSave}>
          Save Email Settings
        </Button>
      </div>
    </section>
  )
}
