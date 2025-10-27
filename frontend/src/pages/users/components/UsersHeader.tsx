import { Button } from '@/components/ui/button'

interface UsersHeaderProps {
  onCreateUser: () => void
}

export function UsersHeader({ onCreateUser }: UsersHeaderProps) {
  return (
    <div className="flex items-center justify-between">
      <div>
        <h1 className="text-3xl font-bold text-foreground">Users</h1>
        <p className="mt-2 text-muted-foreground">
          Manage user accounts and permissions
        </p>
      </div>
      <Button onClick={onCreateUser}>
        + Create User
      </Button>
    </div>
  )
}
