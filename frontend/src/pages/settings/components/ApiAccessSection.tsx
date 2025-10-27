import { Button } from '@/components/ui/button'

interface ApiAccessSectionProps {
  apiKeyMasked: string
  lastGenerated: string | null
  onRegenerate: () => void
}

export function ApiAccessSection({
  apiKeyMasked,
  lastGenerated,
  onRegenerate,
}: ApiAccessSectionProps) {
  return (
    <section className="rounded-lg border border-gray-200 dark:border-0 bg-white dark:bg-[hsl(var(--auth-card))] p-5 shadow-sm">
      <h2 className="text-base font-semibold text-foreground">API Access</h2>
      <p className="mt-1 text-sm text-muted-foreground">Manage API keys for external integrations</p>

      <div className="mt-4">
        <label className="block text-sm font-medium text-foreground">API Key</label>
        <input
          type="password"
          readOnly
          value={apiKeyMasked}
          className="mt-1 w-full rounded-md border border-input bg-background dark:bg-muted/20 px-3 py-2 text-sm text-foreground"
        />
      </div>

      <div className="mt-4 flex items-center gap-3">
        <Button
          onClick={onRegenerate}
          variant="outline"
        >
          Regenerate API Key
        </Button>
        {lastGenerated && (
          <span className="text-xs text-muted-foreground">A new key was generated this session.</span>
        )}
      </div>
    </section>
  )
}
