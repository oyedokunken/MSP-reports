import { Button } from '@/components/ui/button'

interface SecuritySectionProps {
  onChangePassword: () => void
}

export function SecuritySection({ onChangePassword }: SecuritySectionProps) {
  return (
    <section className="rounded-lg border border-gray-200 dark:border-0 bg-white dark:bg-[hsl(var(--auth-card))] p-5 shadow-sm">
      <h2 className="text-base font-semibold text-foreground">Security</h2>
      <p className="mt-1 text-sm text-muted-foreground">Manage your account security settings</p>

      <div className="mt-4">
        <label className="block text-sm font-medium text-foreground mb-2">Password</label>
        <p className="text-sm text-muted-foreground mb-4">
          Change your password to keep your account secure
        </p>
        <Button
          onClick={onChangePassword}
          variant="outline"
        >
          Change Password
        </Button>
      </div>
    </section>
  )
}
