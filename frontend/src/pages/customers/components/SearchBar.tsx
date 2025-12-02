import { Search, Filter } from 'lucide-react'

type SearchBarProps = {
  onFilterClick?: () => void
}

export function CustomersSearchBar ({ onFilterClick }: SearchBarProps) {
  return (
    <div className="flex gap-4">
      <div className="relative flex-1">
        <Search className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
        <input
          type="text"
          placeholder="Search customers..."
          className="w-full rounded-md border border-input bg-background pl-10 pr-4 py-2 text-sm text-foreground placeholder-muted-foreground focus:border-ring focus:outline-none focus:ring-2 focus:ring-ring"
        />
      </div>
      <button className="flex items-center gap-2 rounded-md border border-border bg-background px-4 py-2 text-sm font-medium text-foreground hover:bg-muted" onClick={onFilterClick}>
        <Filter className="h-4 w-4" />
        Filter
      </button>
    </div>
  )
}


