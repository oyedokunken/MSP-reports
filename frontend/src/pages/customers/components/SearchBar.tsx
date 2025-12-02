import { Search, Filter } from 'lucide-react'
import { Input } from '@/components/ui/input'

type SearchBarProps = {
  searchQuery?: string
  onSearchChange?: (query: string) => void
  onFilterClick?: () => void
}

export function CustomersSearchBar ({ searchQuery = '', onSearchChange, onFilterClick }: SearchBarProps) {
  return (
    <div className="flex gap-4">
      <div className="relative flex-1">
        <Search className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
        <Input
          type="text"
          placeholder="Search customers..."
          value={searchQuery}
          onChange={(e) => onSearchChange?.(e.target.value)}
          className="pl-10"
        />
      </div>
      <button className="flex items-center gap-2 rounded-md border border-border bg-background px-4 py-2 text-sm font-medium text-foreground hover:bg-muted" onClick={onFilterClick}>
        <Filter className="h-4 w-4" />
        Filter
      </button>
    </div>
  )
}


