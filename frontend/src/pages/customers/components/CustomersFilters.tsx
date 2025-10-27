import { Input } from '@/components/ui/input'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { Search, Filter } from 'lucide-react'

interface CustomersFiltersProps {
  searchQuery: string
  statusFilter: string
  reportFilter: string
  totalCustomers: number
  filteredCount: number
  onSearchChange: (value: string) => void
  onStatusChange: (value: string) => void
  onReportFilterChange: (value: string) => void
}

export function CustomersFilters({
  searchQuery,
  statusFilter,
  reportFilter,
  totalCustomers,
  filteredCount,
  onSearchChange,
  onStatusChange,
  onReportFilterChange,
}: CustomersFiltersProps) {
  const hasActiveFilters = searchQuery || statusFilter !== 'all' || reportFilter !== 'all'

  return (
    <div className="space-y-3">
      <div className="flex flex-col md:flex-row gap-3">
        {/* Search */}
        <div className="flex-1 relative">
          <Search className="absolute left-3 top-1/2 transform -translate-y-1/2 h-4 w-4 text-muted-foreground" />
          <Input
            placeholder="Search customers..."
            value={searchQuery}
            onChange={(e) => onSearchChange(e.target.value)}
            className="pl-10"
          />
        </div>
        
        {/* Status Filter */}
        <Select value={statusFilter} onValueChange={onStatusChange}>
          <SelectTrigger className="w-full md:w-[180px]">
            <SelectValue placeholder="Status" />
          </SelectTrigger>
          <SelectContent>
            <SelectItem value="all">All Status</SelectItem>
            <SelectItem value="active">Active</SelectItem>
            <SelectItem value="inactive">Inactive</SelectItem>
          </SelectContent>
        </Select>
        
        {/* Report Filter */}
        <Select value={reportFilter} onValueChange={onReportFilterChange}>
          <SelectTrigger className="w-full md:w-[180px]">
            <SelectValue placeholder="Reports" />
          </SelectTrigger>
          <SelectContent>
            <SelectItem value="all">All Customers</SelectItem>
            <SelectItem value="configured">Configured</SelectItem>
            <SelectItem value="not-configured">Not Configured</SelectItem>
          </SelectContent>
        </Select>
      </div>
      
      {/* Active Filters Display */}
      {hasActiveFilters && (
        <div className="flex items-center gap-2 text-sm text-muted-foreground">
          <Filter className="h-4 w-4" />
          <span>Showing {filteredCount} of {totalCustomers} customers</span>
        </div>
      )}
    </div>
  )
}
