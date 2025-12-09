import { useEffect, useState } from 'react'
import { Input } from '@/components/ui/input'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { Search, Filter } from 'lucide-react'
import { api } from '@/lib/api'

interface CustomersFiltersProps {
  searchQuery: string
  statusFilter: string
  reportFilter: string
  integrationFilter: string
  totalCustomers: number
  filteredCount: number
  onSearchChange: (value: string) => void
  onStatusChange: (value: string) => void
  onReportFilterChange: (value: string) => void
  onIntegrationFilterChange: (value: string) => void
}

export function CustomersFilters({
  searchQuery,
  statusFilter,
  reportFilter,
  integrationFilter,
  totalCustomers,
  filteredCount,
  onSearchChange,
  onStatusChange,
  onReportFilterChange,
  onIntegrationFilterChange,
}: CustomersFiltersProps) {
  const [integrationTypes, setIntegrationTypes] = useState<string[]>([])
  const hasActiveFilters = searchQuery || statusFilter !== 'all' || reportFilter !== 'all' || integrationFilter !== 'all'

  // Fetch available integration types
  useEffect(() => {
    const fetchIntegrationTypes = async () => {
      try {
        const response = await api.get('/integrations')
        const data = response as { configured?: Array<{ integration_type?: string }> }
        if (data.configured) {
          const types = [...new Set(data.configured.map(int => int.integration_type).filter(Boolean))] as string[]
          setIntegrationTypes(types)
        }
      } catch (error) {
        console.error('Failed to fetch integration types:', error)
      }
    }
    fetchIntegrationTypes()
  }, [])

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

        {/* Integration Filter */}
        {integrationTypes.length > 0 && (
          <Select value={integrationFilter} onValueChange={onIntegrationFilterChange}>
            <SelectTrigger className="w-full md:w-[180px]">
              <SelectValue placeholder="Integration" />
            </SelectTrigger>
            <SelectContent>
              <SelectItem value="all">All Integrations</SelectItem>
              {integrationTypes.map((type) => (
                <SelectItem key={type} value={type} className="capitalize">
                  {type}
                </SelectItem>
              ))}
            </SelectContent>
          </Select>
        )}
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
