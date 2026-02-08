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
      <div className="flex items-start border-b pb-2 justify-between">
        <div>
          <h2 className="text-base font-semibold text-foreground">Email Delivery</h2>
          <p className="mt-1 text-sm text-muted-foreground">Configure email settings for report delivery</p>
        </div>
        <Button onClick={onSave} size="sm">
          Save Settings
        </Button>
      </div>

      <div className="mt-8 relative">
        {/* Continuous Vertical Divider (Desktop Only) */}
        <div className="absolute left-[33.333333%] top-0 bottom-0 w-px bg-border hidden md:block" />

        <div className="space-y-0">
          {/* From Address Details */}
          <div className="grid grid-cols-1 md:grid-cols-12 items-center">
            <div className="md:col-span-4 p-4 md:py-4 md:pr-6">
              <label className="text-sm font-semibold text-foreground">Sender Details</label>
              <p className="text-xs text-muted-foreground mt-1">The name and address that will appear on outgoing reports.</p>
            </div>
            <div className="md:col-span-8 p-4 md:p-4 md:pl-6">
              <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div className="space-y-1">
                  <span className="text-[10px] uppercase font-bold text-muted-foreground/60 tracking-wider">From Email</span>
                  <input
                    type="email"
                    placeholder="reports@yourcompany.com"
                    value={fromEmail}
                    onChange={(e) => onFromEmailChange(e.target.value)}
                    className="w-full rounded-md border border-input bg-background px-3 py-2 text-sm text-foreground"
                  />
                </div>
                <div className="space-y-1">
                  <span className="text-[10px] uppercase font-bold text-muted-foreground/60 tracking-wider">From Name</span>
                  <input
                    type="text"
                    placeholder="Your Company Reports"
                    value={fromName}
                    onChange={(e) => onFromNameChange(e.target.value)}
                    className="w-full rounded-md border border-input bg-background px-3 py-2 text-sm text-foreground"
                  />
                </div>
              </div>
            </div>
          </div>

          {/* SMTP Host */}
          <div className="grid grid-cols-1 md:grid-cols-12 items-center">
            <div className="md:col-span-4 p-4 md:py-4 md:pr-6">
              <label className="text-sm font-semibold text-foreground">SMTP Server</label>
              <p className="text-xs text-muted-foreground mt-1">The hostname or IP address of your mail server.</p>
            </div>
            <div className="md:col-span-8 p-4 md:p-4 md:pl-6">
              <input
                type="text"
                placeholder="smtp.example.com"
                value={smtpHost}
                onChange={(e) => onSmtpHostChange(e.target.value)}
                className="w-full rounded-md border border-input bg-background px-3 py-2 text-sm text-foreground"
              />
            </div>
          </div>

          {/* Connection Settings */}
          <div className="grid grid-cols-1 md:grid-cols-12 items-center">
            <div className="md:col-span-4 p-4 md:py-4 md:pr-6">
              <label className="text-sm font-semibold text-foreground">Connection</label>
              <p className="text-xs text-muted-foreground mt-1">Port and encryption type for the mail server.</p>
            </div>
            <div className="md:col-span-8 p-4 md:p-4 md:pl-6">
              <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div className="space-y-1">
                  <span className="text-[10px] uppercase font-bold text-muted-foreground/60 tracking-wider">Port</span>
                  <input
                    type="number"
                    placeholder="587"
                    value={smtpPort}
                    onChange={(e) => onSmtpPortChange(parseInt(e.target.value) || 0)}
                    className="w-full rounded-md border border-input bg-background px-3 py-2 text-sm text-foreground"
                  />
                </div>
                <div className="space-y-1">
                  <span className="text-[10px] uppercase font-bold text-muted-foreground/60 tracking-wider">Encryption</span>
                  <select
                    value={smtpEncryption}
                    onChange={(e) => onSmtpEncryptionChange(e.target.value)}
                    className="w-full rounded-md border border-input bg-background px-3 py-2 text-sm text-foreground focus:border-ring focus:outline-none focus:ring-2 focus:ring-ring"
                  >
                    <option value="tls">TLS</option>
                    <option value="ssl">SSL</option>
                    <option value="none">None</option>
                  </select>
                </div>
              </div>
            </div>
          </div>

          {/* Authentication */}
          <div className="grid grid-cols-1 md:grid-cols-12 items-center">
            <div className="md:col-span-4 p-4 md:py-4 md:pr-6">
              <label className="text-sm font-semibold text-foreground">Authentication</label>
              <p className="text-xs text-muted-foreground mt-1">The credentials used to authenticate with the server.</p>
            </div>
            <div className="md:col-span-8 p-4 md:p-4 md:pl-6">
              <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div className="space-y-1">
                  <span className="text-[10px] uppercase font-bold text-muted-foreground/60 tracking-wider">Username</span>
                  <input
                    type="text"
                    placeholder="username@example.com"
                    value={smtpUsername}
                    onChange={(e) => onSmtpUsernameChange(e.target.value)}
                    className="w-full rounded-md border border-input bg-background px-3 py-2 text-sm text-foreground"
                  />
                </div>
                <div className="space-y-1">
                  <span className="text-[10px] uppercase font-bold text-muted-foreground/60 tracking-wider">Password</span>
                  <input
                    type="password"
                    placeholder="••••••••"
                    value={smtpPassword}
                    onChange={(e) => onSmtpPasswordChange(e.target.value)}
                    className="w-full rounded-md border border-input bg-background px-3 py-2 text-sm text-foreground"
                  />
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </section>
  )
}
