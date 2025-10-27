import { Input } from '@/components/ui/input'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { Search } from 'lucide-react'
import { formatReportType } from '../utils/formatters'

interface ReportsFiltersProps {
  searchQuery: string
  statusFilter: string
  reportTypeFilter: string
  uniqueReportTypes: string[]
  onSearchChange: (value: string) => void
  onStatusChange: (value: string) => void
  onReportTypeChange: (value: string) => void
}

export function ReportsFilters({
  searchQuery,
  statusFilter,
  reportTypeFilter,
  uniqueReportTypes,
  onSearchChange,
  onStatusChange,
  onReportTypeChange,
}: ReportsFiltersProps) {
  return (
    <div className="flex flex-col md:flex-row gap-3">
      {/* Search */}
      <div className="flex-1 relative">
        <Search className="absolute left-3 top-1/2 transform -translate-y-1/2 h-4 w-4 text-muted-foreground" />
        <Input
          placeholder="Search by customer name..."
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
          <SelectItem value="completed">Completed</SelectItem>
          <SelectItem value="pending">Pending</SelectItem>
          <SelectItem value="generating">Generating</SelectItem>
          <SelectItem value="failed">Failed</SelectItem>
        </SelectContent>
      </Select>
      
      {/* Report Type Filter */}
      <Select value={reportTypeFilter} onValueChange={onReportTypeChange}>
        <SelectTrigger className="w-full md:w-[200px]">
          <SelectValue placeholder="Report Type" />
        </SelectTrigger>
        <SelectContent>
          <SelectItem value="all">All Report Types</SelectItem>
          {uniqueReportTypes.map(type => (
            <SelectItem key={type} value={type}>
              {formatReportType(type)}
            </SelectItem>
          ))}
        </SelectContent>
      </Select>
    </div>
  )
}
