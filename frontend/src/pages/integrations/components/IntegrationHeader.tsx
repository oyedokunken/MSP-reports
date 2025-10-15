import { Plus } from 'lucide-react'

type IntegrationHeaderProps = {
  onAdd: () => void
}

export function IntegrationHeader ({ onAdd }: IntegrationHeaderProps) {
  return (
    <div className="flex items-center justify-between">
      <div>
        <h1 className="text-3xl font-bold text-foreground">Integrations</h1>
        <p className="mt-2 text-muted-foreground">Manage connections to external systems</p>
      </div>
      <button className="flex items-center text-white gap-2 rounded-md bg-primary px-4 py-2 text-sm font-medium text-primary-foreground hover:bg-primary/90" onClick={onAdd}>
        <Plus className="h-4 w-4 text-white" />
        Add Integration
      </button>
    </div>
  )
}


