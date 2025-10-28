import { useNavigate } from 'react-router-dom'
import { Cable, Plus } from 'lucide-react'

type DashboardHeaderActionsProps = {
  onToastSuccess: (title: string, description?: string) => void
  onToastInfo: (title: string, description?: string) => void
}

export function DashboardHeaderActions ({ onToastSuccess, onToastInfo }: DashboardHeaderActionsProps) {
  const navigate = useNavigate()

  return (
    <div className="flex gap-2">
      <button
        className="flex items-center gap-2 rounded-md bg-primary px-4 py-2 text-sm font-medium text-primary-foreground hover:bg-primary/90"
        onClick={() => navigate('/schedules')}
      >
        <Plus className="h-4 w-4 text-white" />
        Create Schedule
      </button>
      <button
        className="flex items-center gap-2 rounded-md border border-border bg-background px-4 py-2 text-sm font-medium text-foreground hover:bg-muted"
        onClick={() => navigate('/integrations')}
      >
        <Cable className="h-4 w-4" />
        Add Integration
      </button>
    </div>
  )
}


