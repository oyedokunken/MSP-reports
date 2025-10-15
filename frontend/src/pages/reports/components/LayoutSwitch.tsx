import { LayoutGrid, List } from 'lucide-react'

type LayoutSwitchProps = {
  mode: 'grid' | 'list'
  onChange: (mode: 'grid' | 'list') => void
}

export function ReportsLayoutSwitch ({ mode, onChange }: LayoutSwitchProps) {
  return (
    <div className="flex items-center gap-2">
      <button
        onClick={() => onChange('list')}
        className={`flex items-center gap-1 rounded-md px-3 py-1 text-sm font-medium ${mode === 'list' ? 'bg-primary text-white' : 'bg-muted text-muted-foreground hover:bg-muted/80'}`}
      >
        <List className="h-4 w-4" /> List
      </button>
      <button
        onClick={() => onChange('grid')}
        className={`flex items-center gap-1 rounded-md px-3 py-1 text-sm font-medium ${mode === 'grid' ? 'bg-primary text-white' : 'bg-muted text-muted-foreground hover:bg-muted/80'}`}
      >
        <LayoutGrid className="h-4 w-4" /> Grid
      </button>
    </div>
  )
}


