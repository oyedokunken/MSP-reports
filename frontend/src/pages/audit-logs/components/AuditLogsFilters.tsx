import { Search, Filter, X } from 'lucide-react'
import { Input } from '@/components/ui/input'
import { 
  Select, 
  SelectContent, 
  SelectItem, 
  SelectTrigger, 
  SelectValue 
} from '@/components/ui/select'
import { Button } from '@/components/ui/button'
import type { AuditLogsFilters as FiltersType } from '../types'
import { EVENT_TYPES, ACTOR_TYPES, TARGET_TYPES } from '../types'

interface AuditLogsFiltersProps {
  filters: FiltersType
  onFiltersChange: (filters: Partial<FiltersType>) => void
}

export function AuditLogsFilters({ filters, onFiltersChange }: AuditLogsFiltersProps) {
  const hasActiveFilters = 
    filters.search ||
    filters.event_type ||
    filters.actor_type ||
    filters.target_type ||
    filters.date_from ||
    filters.date_to

  const clearFilters = () => {
    onFiltersChange({
      search: '',
      event_type: '',
      actor_type: '',
      target_type: '',
      date_from: '',
      date_to: '',
    })
  }

  return (
    <div className="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-800">
      <div className="mb-3 flex items-center justify-between">
        <div className="flex items-center gap-2">
          <Filter className="h-4 w-4 text-gray-500 dark:text-gray-400" />
          <h3 className="text-sm font-semibold text-gray-900 dark:text-white">
            Filters
          </h3>
        </div>
        
        {hasActiveFilters && (
          <Button
            variant="ghost"
            size="sm"
            onClick={clearFilters}
            className="gap-1 text-xs"
          >
            <X className="h-3 w-3" />
            Clear All
          </Button>
        )}
      </div>

      <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6">
        {/* Search */}
        <div className="sm:col-span-2">
          <div className="relative">
            <Search className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" />
            <Input
              type="text"
              placeholder="Search logs..."
              value={filters.search}
              onChange={(e) => onFiltersChange({ search: e.target.value })}
              className="pl-9"
            />
          </div>
        </div>

        {/* Event Type */}
        <div>
          <Select
            value={filters.event_type || 'none'}
            onValueChange={(value) => onFiltersChange({ event_type: value === 'none' ? '' : value })}
          >
            <SelectTrigger>
              <SelectValue placeholder="All Events" />
            </SelectTrigger>
            <SelectContent>
              <SelectItem value="none">All Events</SelectItem>
              {Object.entries(EVENT_TYPES).map(([key, label]) => (
                <SelectItem key={key} value={key}>
                  {label}
                </SelectItem>
              ))}
            </SelectContent>
          </Select>
        </div>

        {/* Actor Type */}
        <div>
          <Select
            value={filters.actor_type || 'none'}
            onValueChange={(value) => onFiltersChange({ actor_type: value === 'none' ? '' : value })}
          >
            <SelectTrigger>
              <SelectValue placeholder="All Actors" />
            </SelectTrigger>
            <SelectContent>
              <SelectItem value="none">All Actors</SelectItem>
              {Object.entries(ACTOR_TYPES).map(([key, label]) => (
                <SelectItem key={key} value={key}>
                  {label}
                </SelectItem>
              ))}
            </SelectContent>
          </Select>
        </div>

        {/* Target Type */}
        <div>
          <Select
            value={filters.target_type || 'none'}
            onValueChange={(value) => onFiltersChange({ target_type: value === 'none' ? '' : value })}
          >
            <SelectTrigger>
              <SelectValue placeholder="All Targets" />
            </SelectTrigger>
            <SelectContent>
              <SelectItem value="none">All Targets</SelectItem>
              {Object.entries(TARGET_TYPES).map(([key, label]) => (
                <SelectItem key={key} value={key}>
                  {label}
                </SelectItem>
              ))}
            </SelectContent>
          </Select>
        </div>

        {/* Date From */}
        <div>
          <Input
            type="date"
            value={filters.date_from}
            onChange={(e) => onFiltersChange({ date_from: e.target.value })}
            placeholder="From Date"
          />
        </div>

        {/* Date To */}
        <div>
          <Input
            type="date"
            value={filters.date_to}
            onChange={(e) => onFiltersChange({ date_to: e.target.value })}
            placeholder="To Date"
          />
        </div>
      </div>
    </div>
  )
}
