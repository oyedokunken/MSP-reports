import { Button } from '@/components/ui/button'

interface EmailDeliverySectionProps {
  fromEmail: string
  fromName: string
  smtpHost: string
  smtpPort: number
  smtpEncryption: string
  smtpUsername: string
  smtpPassword?: string
  onFromEmailChange: (email: string) => void
  onFromNameChange: (name: string) => void
  onSmtpHostChange: (host: string) => void
  onSmtpPortChange: (port: number) => void
  onSmtpEncryptionChange: (enc: string) => void
  onSmtpUsernameChange: (user: string) => void
  onSmtpPasswordChange: (pass: string) => void
  onSave: () => void
}

export function EmailDeliverySection({
  fromEmail,
  fromName,
  smtpHost,
  smtpPort,
  smtpEncryption,
  smtpUsername,
  smtpPassword,
  onFromEmailChange,
  onFromNameChange,
  onSmtpHostChange,
  onSmtpPortChange,
  onSmtpEncryptionChange,
  onSmtpUsernameChange,
  onSmtpPasswordChange,
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
            className="mt-1 w-full rounded-md border border-input bg-background px-3 py-2 text-sm text-foreground placeholder-muted-foreground focus:border-ring focus:outline-none focus:ring-2 focus:ring-ring"
          />
        </div>
        <div>
          <label className="block text-sm font-medium text-foreground">From Name</label>
          <input
            type="text"
            placeholder="Your Company Reports"
            value={fromName}
            onChange={(e) => onFromNameChange(e.target.value)}
            className="mt-1 w-full rounded-md border border-input bg-background px-3 py-2 text-sm text-foreground placeholder-muted-foreground focus:border-ring focus:outline-none focus:ring-2 focus:ring-ring"
          />
        </div>

        <div className="sm:col-span-2 pt-2 border-t dark:border-gray-800">
          <h3 className="text-sm font-medium text-foreground">SMTP Configuration (Optional)</h3>
          <p className="text-xs text-muted-foreground">Override system defaults with your own mail server</p>
        </div>

        <div>
          <label className="block text-sm font-medium text-foreground">SMTP Host</label>
          <input
            type="text"
            placeholder="smtp.example.com"
            value={smtpHost}
            onChange={(e) => onSmtpHostChange(e.target.value)}
            className="mt-1 w-full rounded-md border border-input bg-background px-3 py-2 text-sm text-foreground placeholder-muted-foreground focus:border-ring focus:outline-none focus:ring-2 focus:ring-ring"
          />
        </div>

        <div className="grid grid-cols-2 gap-4">
          <div>
            <label className="block text-sm font-medium text-foreground">Port</label>
            <input
              type="number"
              placeholder="587"
              value={smtpPort}
              onChange={(e) => onSmtpPortChange(parseInt(e.target.value) || 0)}
              className="mt-1 w-full rounded-md border border-input bg-background px-3 py-2 text-sm text-foreground placeholder-muted-foreground focus:border-ring focus:outline-none focus:ring-2 focus:ring-ring"
            />
          </div>
          <div>
            <label className="block text-sm font-medium text-foreground">Encryption</label>
            <select
              value={smtpEncryption}
              onChange={(e) => onSmtpEncryptionChange(e.target.value)}
              className="mt-1 w-full rounded-md border border-input bg-background px-3 py-2 text-sm text-foreground focus:border-ring focus:outline-none focus:ring-2 focus:ring-ring"
            >
              <option value="tls">TLS</option>
              <option value="ssl">SSL</option>
              <option value="none">None</option>
            </select>
          </div>
        </div>

        <div>
          <label className="block text-sm font-medium text-foreground">SMTP Username</label>
          <input
            type="text"
            placeholder="username@example.com"
            value={smtpUsername}
            onChange={(e) => onSmtpUsernameChange(e.target.value)}
            className="mt-1 w-full rounded-md border border-input bg-background px-3 py-2 text-sm text-foreground placeholder-muted-foreground focus:border-ring focus:outline-none focus:ring-2 focus:ring-ring"
          />
        </div>

        <div>
          <label className="block text-sm font-medium text-foreground">SMTP Password</label>
          <input
            type="password"
            placeholder="••••••••"
            value={smtpPassword}
            onChange={(e) => onSmtpPasswordChange(e.target.value)}
            className="mt-1 w-full rounded-md border border-input bg-background px-3 py-2 text-sm text-foreground placeholder-muted-foreground focus:border-ring focus:outline-none focus:ring-2 focus:ring-ring"
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
