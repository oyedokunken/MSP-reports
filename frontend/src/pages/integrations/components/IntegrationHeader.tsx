import { Plus, Search, Filter, LayoutGrid, LayoutList } from 'lucide-react'
import { Input } from '@/components/ui/input'
import { Button } from '@/components/ui/button'
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuLabel,
  DropdownMenuSeparator,
  DropdownMenuCheckboxItem,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu'
import {
  Tooltip,
  TooltipContent,
  TooltipProvider,
  TooltipTrigger,
} from '@/components/ui/tooltip'
import { cn } from '@/lib/utils'

export type ViewMode = 'grid' | 'table'

type IntegrationHeaderProps = {
  onAdd: () => void
  searchQuery: string
  statusFilters: string[]
  integrationTypeFilters: string[]
  uniqueTypes: string[]
  onSearchChange: (query: string) => void
  onStatusFiltersChange: (statuses: string[]) => void
  onIntegrationTypeFiltersChange: (types: string[]) => void
  viewMode?: ViewMode
  onViewModeChange?: (mode: ViewMode) => void
}

function formatType(type: string): string {
  return type.charAt(0).toUpperCase() + type.slice(1).toLowerCase()
}

export function IntegrationHeader ({ 
  onAdd, 
  searchQuery, 
  statusFilters,
  integrationTypeFilters,
  uniqueTypes,
  onSearchChange,
  onStatusFiltersChange,
  onIntegrationTypeFiltersChange,
  viewMode = 'grid',
  onViewModeChange
}: IntegrationHeaderProps) {
  const activeFilterCount = statusFilters.length + integrationTypeFilters.length

  const toggleStatusFilter = (status: string) => {
    if (statusFilters.includes(status)) {
      onStatusFiltersChange(statusFilters.filter(s => s !== status))
    } else {
      onStatusFiltersChange([...statusFilters, status])
    }
  }

  const toggleIntegrationTypeFilter = (type: string) => {
    if (integrationTypeFilters.includes(type)) {
      onIntegrationTypeFiltersChange(integrationTypeFilters.filter(t => t !== type))
    } else {
      onIntegrationTypeFiltersChange([...integrationTypeFilters, type])
    }
  }

  return (
    <div className="space-y-4">
      <div>
        <h1 className="text-3xl font-bold text-foreground">Integrations</h1>
        <p className="mt-2 text-muted-foreground">Manage connections to external systems</p>
      </div>
      
      {/* Search and Filters */}
      <div className="flex flex-col md:flex-row gap-3">
        {/* Filter Dropdown */}
        <DropdownMenu>
          <DropdownMenuTrigger asChild>
            <Button variant="outline" className="w-full md:w-auto h-10">
              <Filter className="h-4 w-4 mr-2" />
              Filters
              {activeFilterCount > 0 && (
                <span className="ml-2 px-2 py-0.5 text-xs bg-primary text-primary-foreground rounded-full">
                  {activeFilterCount}
                </span>
              )}
            </Button>
          </DropdownMenuTrigger>
          <DropdownMenuContent align="start" className="w-56">
            {/* Status Section */}
            <DropdownMenuLabel>Status</DropdownMenuLabel>
            <DropdownMenuCheckboxItem
              checked={statusFilters.includes('active')}
              onCheckedChange={() => toggleStatusFilter('active')}
            >
              Active
            </DropdownMenuCheckboxItem>
            <DropdownMenuCheckboxItem
              checked={statusFilters.includes('disconnected')}
              onCheckedChange={() => toggleStatusFilter('disconnected')}
            >
              Disconnected
            </DropdownMenuCheckboxItem>
            <DropdownMenuCheckboxItem
              checked={statusFilters.includes('failed')}
              onCheckedChange={() => toggleStatusFilter('failed')}
            >
              Failed
            </DropdownMenuCheckboxItem>
            <DropdownMenuCheckboxItem
              checked={statusFilters.includes('error')}
              onCheckedChange={() => toggleStatusFilter('error')}
            >
              Error
            </DropdownMenuCheckboxItem>

            {/* Integration Types Section */}
            {uniqueTypes.length > 0 && (
              <>
                <DropdownMenuSeparator />
                <DropdownMenuLabel>Integration</DropdownMenuLabel>
                {uniqueTypes.map((type) => (
                  <DropdownMenuCheckboxItem
                    key={type}
                    checked={integrationTypeFilters.includes(type)}
                    onCheckedChange={() => toggleIntegrationTypeFilter(type)}
                  >
                    {formatType(type)}
                  </DropdownMenuCheckboxItem>
                ))}
              </>
            )}
          </DropdownMenuContent>
        </DropdownMenu>

        {/* View Mode Toggle */}
        {onViewModeChange && (
          <TooltipProvider>
            <div className="flex items-center border border-slate-200 dark:border-slate-800 rounded-md bg-white dark:bg-slate-900 p-1 h-10">
              <Tooltip>
                <TooltipTrigger asChild>
                  <button
                    onClick={() => onViewModeChange('grid')}
                    className={cn(
                      "flex items-center gap-1.5 px-3 py-1.5 text-sm font-medium transition-colors rounded-md",
                      viewMode === 'grid'
                        ? "bg-primary text-primary-foreground shadow-sm"
                        : "text-muted-foreground hover:text-foreground"
                    )}
                  >
                    <LayoutGrid className="h-4 w-4" />
                    <span className="hidden sm:inline">Grid</span>
                  </button>
                </TooltipTrigger>
                <TooltipContent>
                  Grid View
                </TooltipContent>
              </Tooltip>
              <Tooltip>
                <TooltipTrigger asChild>
                  <button
                    onClick={() => onViewModeChange('table')}
                    className={cn(
                      "flex items-center gap-1.5 px-3 py-1.5 text-sm font-medium transition-colors rounded-md",
                      viewMode === 'table'
                        ? "bg-primary text-primary-foreground shadow-sm"
                        : "text-muted-foreground hover:text-foreground"
                    )}
                  >
                    <LayoutList className="h-4 w-4" />
                    <span className="hidden sm:inline">Table</span>
                  </button>
                </TooltipTrigger>
                <TooltipContent>
                  Table View
                </TooltipContent>
              </Tooltip>
            </div>
          </TooltipProvider>
        )}

        {/* Search */}
        <div className="flex-1 relative">
          <Search className="absolute left-3 top-1/2 transform -translate-y-1/2 h-4 w-4 text-muted-foreground" />
          <Input
            placeholder="Search by name or type..."
            value={searchQuery}
            onChange={(e) => onSearchChange(e.target.value)}
            className="pl-10 h-10"
          />
        </div>

        {/* Add Integration Button */}
        <button className="flex items-center text-white gap-2 rounded-md bg-primary px-4 py-2 text-sm font-medium text-primary-foreground hover:bg-primary/90 h-10" onClick={onAdd}>
          <Plus className="h-4 w-4 text-white" />
          Add Integration
        </button>
      </div>
    </div>
  )
}


