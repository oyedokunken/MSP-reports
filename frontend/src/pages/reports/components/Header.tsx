import { Plus } from 'lucide-react'

type ReportsHeaderProps = {
  onCreate: () => void
}

export function ReportsHeader ({ onCreate }: ReportsHeaderProps) {
  return (
    <div className="flex items-center justify-between">
      <div>
        <h1 className="text-3xl font-bold text-foreground">Reports</h1>
        <p className="mt-2 text-muted-foreground">Manage report schedules and generated reports</p>
      </div>
      <button className="flex items-center gap-2 text-white rounded-md bg-primary px-4 py-2 text-sm font-medium text-primary-foreground hover:bg-primary/90" onClick={onCreate}>
        <Plus className="h-4 w-4 text-white" />
        Create Schedule
      </button>
    </div>
  )
}


