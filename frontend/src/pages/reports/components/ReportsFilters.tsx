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
import { Search, Filter, Trash2, Loader2, LayoutGrid, LayoutList, Users } from 'lucide-react'
import { formatReportType } from '../utils/formatters'
import { cn } from '@/lib/utils'

export type ViewMode = 'grid' | 'table'

interface ReportsFiltersProps {
  searchQuery: string
  statusFilters: string[]
  reportTypeFilters: string[]
  uniqueReportTypes: string[]
  onSearchChange: (value: string) => void
  onStatusFiltersChange: (statuses: string[]) => void
  onReportTypeFiltersChange: (types: string[]) => void
  onBulkDelete?: () => void
  onBulkGenerate?: () => void
  customerId?: string | null
  selectedCount?: number
  isDeleting?: boolean
  viewMode?: ViewMode
  onViewModeChange?: (mode: ViewMode) => void
}

export function ReportsFilters({
  searchQuery,
  statusFilters,
  reportTypeFilters,
  uniqueReportTypes,
  onSearchChange,
  onStatusFiltersChange,
  onReportTypeFiltersChange,
  onBulkDelete,
  onBulkGenerate,
  customerId,
  selectedCount = 0,
  isDeleting = false,
  viewMode = 'grid',
  onViewModeChange,
}: ReportsFiltersProps) {
  const activeFilterCount = statusFilters.length + reportTypeFilters.length

  const toggleStatusFilter = (status: string) => {
    if (statusFilters.includes(status)) {
      onStatusFiltersChange(statusFilters.filter(s => s !== status))
    } else {
      onStatusFiltersChange([...statusFilters, status])
    }
  }

  const toggleReportTypeFilter = (type: string) => {
    if (reportTypeFilters.includes(type)) {
      onReportTypeFiltersChange(reportTypeFilters.filter(t => t !== type))
    } else {
      onReportTypeFiltersChange([...reportTypeFilters, type])
    }
  }

  return (
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
            checked={statusFilters.includes('completed')}
            onCheckedChange={() => toggleStatusFilter('completed')}
          >
            Completed
          </DropdownMenuCheckboxItem>
          <DropdownMenuCheckboxItem
            checked={statusFilters.includes('pending')}
            onCheckedChange={() => toggleStatusFilter('pending')}
          >
            Pending
          </DropdownMenuCheckboxItem>
          <DropdownMenuCheckboxItem
            checked={statusFilters.includes('generating')}
            onCheckedChange={() => toggleStatusFilter('generating')}
          >
            Generating
          </DropdownMenuCheckboxItem>
          <DropdownMenuCheckboxItem
            checked={statusFilters.includes('failed')}
            onCheckedChange={() => toggleStatusFilter('failed')}
          >
            Failed
          </DropdownMenuCheckboxItem>

          {/* Report Types Section */}
          {uniqueReportTypes.length > 0 && (
            <>
              <DropdownMenuSeparator />
              <DropdownMenuLabel>Report Types</DropdownMenuLabel>
              {uniqueReportTypes.map((type) => (
                <DropdownMenuCheckboxItem
                  key={type}
                  checked={reportTypeFilters.includes(type)}
                  onCheckedChange={() => toggleReportTypeFilter(type)}
                >
                  {formatReportType(type)}
                </DropdownMenuCheckboxItem>
              ))}
            </>
          )}
        </DropdownMenuContent>
      </DropdownMenu>

      {/* View Mode Toggle - Matches Map Customers design */}
      {onViewModeChange && (
        <div className="flex items-center border border-slate-200 dark:border-slate-800 rounded-md bg-white dark:bg-slate-900 p-1 h-10">
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
        </div>
      )}

      {/* Search */}
      <div className="flex-1 relative">
        <Search className="absolute left-3 top-1/2 transform -translate-y-1/2 h-4 w-4 text-muted-foreground" />
        <Input
          placeholder="Search by customer name..."
          value={searchQuery}
          onChange={(e) => onSearchChange(e.target.value)}
          className="pl-10 h-10"
        />
      </div>

      {/* Bulk Generate Button */}
      {!customerId && onBulkGenerate && (
        <Button 
          onClick={onBulkGenerate}
          className="w-full md:w-auto h-10"
        >
          <Users className="h-4 w-4 mr-2" />
          Bulk Generate
        </Button>
      )}

      {/* Bulk Delete Button */}
      {selectedCount > 0 && (
        <Button
          variant="destructive"
          className="w-full md:w-auto h-10 animate-in fade-in slide-in-from-right-2"
          onClick={onBulkDelete}
          disabled={isDeleting}
        >
          {isDeleting ? (
            <Loader2 className="h-4 w-4 mr-2 animate-spin" />
          ) : (
            <Trash2 className="h-4 w-4 mr-2" />
          )}
          Delete {selectedCount} Selected
        </Button>
      )}
    </div>
  )
}
