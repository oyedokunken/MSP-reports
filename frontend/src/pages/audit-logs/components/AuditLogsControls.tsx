import { Search, Filter, Download, X, Calendar } from 'lucide-react'
import { Input } from '@/components/ui/input'
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue
} from '@/components/ui/select'
import { Button } from '@/components/ui/button'
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuLabel,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu'
import { type AuditLogsFilters as FiltersType, EVENT_TYPES, ACTOR_TYPES, TARGET_TYPES } from '../types'

interface AuditLogsControlsProps {
  filters: FiltersType
  onFiltersChange: (filters: Partial<FiltersType>) => void
  onExport: () => void
}

export function AuditLogsControls({
  filters,
  onFiltersChange,
  onExport
}: AuditLogsControlsProps) {
  const activeFiltersCount = [
    filters.event_type,
    filters.actor_type,
    filters.target_type,
  ].filter(Boolean).length

  const hasDateFilter = filters.date_from || filters.date_to

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
    <div className="flex flex-col md:flex-row items-center gap-3">
      {/* Filters Dropdown */}
      <DropdownMenu>
        <DropdownMenuTrigger asChild>
          <Button variant="outline" className="w-full md:w-auto gap-2 border shadow-sm">
            <Filter className="h-4 w-4 text-gray-400" />
            Filters
            {activeFiltersCount > 0 && (
              <span className="ml-1 flex h-5 w-5 items-center justify-center rounded-full bg-indigo-600 text-[10px] font-bold text-white shadow-sm">
                {activeFiltersCount}
              </span>
            )}
          </Button>
        </DropdownMenuTrigger>
        <DropdownMenuContent align="start" className="w-64 p-2 shadow-xl border-gray-100">
          <DropdownMenuLabel className="flex items-center justify-between">
            <span>Filter Events</span>
            {activeFiltersCount > 0 && (
              <Button variant="ghost" size="sm" onClick={clearFilters} className="h-6 px-2 text-[10px] text-gray-400 hover:text-red-500">
                Clear all
              </Button>
            )}
          </DropdownMenuLabel>
          <DropdownMenuSeparator />
          <div className="space-y-3 py-2">
            <div className="space-y-1">
              <span className="text-[10px] font-medium text-gray-400 uppercase tracking-wider px-1">Event Type</span>
              <Select
                value={filters.event_type || 'none'}
                onValueChange={(value) => onFiltersChange({ event_type: value === 'none' ? '' : value })}
              >
                <SelectTrigger className="h-9 text-xs">
                  <SelectValue placeholder="All Events" />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="none">All Events</SelectItem>
                  {Object.entries(EVENT_TYPES).map(([key, label]) => (
                    <SelectItem key={key} value={key}>{label}</SelectItem>
                  ))}
                </SelectContent>
              </Select>
            </div>

            <div className="space-y-1">
              <span className="text-[10px] font-medium text-gray-400 uppercase tracking-wider px-1">Actor Type</span>
              <Select
                value={filters.actor_type || 'none'}
                onValueChange={(value) => onFiltersChange({ actor_type: value === 'none' ? '' : value })}
              >
                <SelectTrigger className="h-9 text-xs">
                  <SelectValue placeholder="All Actors" />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="none">All Actors</SelectItem>
                  {Object.entries(ACTOR_TYPES).map(([key, label]) => (
                    <SelectItem key={key} value={key}>{label}</SelectItem>
                  ))}
                </SelectContent>
              </Select>
            </div>

            <div className="space-y-1">
              <span className="text-[10px] font-medium text-gray-400 uppercase tracking-wider px-1">Target Type</span>
              <Select
                value={filters.target_type || 'none'}
                onValueChange={(value) => onFiltersChange({ target_type: value === 'none' ? '' : value })}
              >
                <SelectTrigger className="h-9 text-xs">
                  <SelectValue placeholder="All Targets" />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="none">All Targets</SelectItem>
                  {Object.entries(TARGET_TYPES).map(([key, label]) => (
                    <SelectItem key={key} value={key}>{label}</SelectItem>
                  ))}
                </SelectContent>
              </Select>
            </div>
          </div>
        </DropdownMenuContent>
      </DropdownMenu>

      {/* Search Bar */}
      <div className="relative flex-1 w-full md:w-auto">
        <Search className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" />
        <Input
          placeholder="Search activity, actors or targets..."
          value={filters.search}
          onChange={(e) => onFiltersChange({ search: e.target.value })}
          className="pl-9 h-10 border rounded-lg shadow-sm focus-visible:ring-indigo-500 transition-all"
        />
        {filters.search && (
          <button
            onClick={() => onFiltersChange({ search: '' })}
            className="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600"
          >
            <X className="h-3 w-3" />
          </button>
        )}
      </div>

      {/* Date Range */}
      <div className="flex items-center gap-2 w-full md:w-auto">
        <div className="relative flex items-center bg-white dark:bg-gray-900 border rounded-lg h-10 px-1 shadow-sm overflow-hidden group">
          <Calendar className="h-4 w-4 ml-2 mr-1 text-gray-400" />
          <Input
            type="date"
            value={filters.date_from}
            onChange={(e) => onFiltersChange({ date_from: e.target.value })}
            className="border-0 h-8 text-[11px] focus-visible:ring-0 w-24 bg-transparent outline-none p-0 cursor-pointer"
          />
          <span className="text-gray-300 mx-1"> - </span>
          <Input
            type="date"
            value={filters.date_to}
            onChange={(e) => onFiltersChange({ date_to: e.target.value })}
            className="border-0 h-8 text-[11px] focus-visible:ring-0 w-24 bg-transparent outline-none p-0 cursor-pointer"
          />
          {hasDateFilter && (
            <button
              onClick={() => onFiltersChange({ date_from: '', date_to: '' })}
              className="px-2 text-gray-300 hover:text-gray-600 transition-colors"
            >
              <X className="h-3 w-3" />
            </button>
          )}
        </div>
      </div>

      {/* Export Button */}
      <Button
        variant="default"
        onClick={onExport}
        className="h-10 px-4 gap-2 bg-indigo-600 hover:bg-indigo-700 shadow-md transform transition active:scale-95 w-full md:w-auto"
      >
        <Download className="h-4 w-4" />
        Export
      </Button>
    </div>
  )
}
