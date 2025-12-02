import { Plus, Search } from 'lucide-react'

type IntegrationHeaderProps = {
  onAdd: () => void
  searchQuery: string
  onSearchChange: (query: string) => void
}

export function IntegrationHeader ({ onAdd, searchQuery, onSearchChange }: IntegrationHeaderProps) {
  return (
    <div className="space-y-4">
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
      
      {/* Search */}
      <div className="relative max-w-md">
        <Search className="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground" />
        <input
          type="text"
          placeholder="Search integrations..."
          value={searchQuery}
          onChange={(e) => onSearchChange(e.target.value)}
          className="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary/50 focus:border-primary"
        />
      </div>
    </div>
  )
}


