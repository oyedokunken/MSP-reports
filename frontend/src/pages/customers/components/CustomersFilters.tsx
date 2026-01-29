import { useEffect, useState } from 'react'
import { Input } from '@/components/ui/input'
import { logger } from '@/lib/logger'
import { Button } from '@/components/ui/button'
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuLabel,
  DropdownMenuSeparator,
  DropdownMenuCheckboxItem,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu'
import { Search, Filter, UserPlus, LayoutGrid, List } from 'lucide-react'
import { Tabs, TabsList, TabsTrigger } from '@/components/ui/tabs'
import { api } from '@/lib/api'
import { useAuth } from '@/contexts/AuthContext'
import { hasPermission } from '@/utils/permissions'

interface CustomersFiltersProps {
  searchQuery: string
  statusFilters: string[]
  reportFilters: string[]
  integrationFilters: string[]
  totalCustomers: number
  filteredCount: number
  onSearchChange: (value: string) => void
  onStatusFiltersChange: (statuses: string[]) => void
  onReportFiltersChange: (filters: string[]) => void
  onIntegrationFiltersChange: (filters: string[]) => void
  onCreateStatic?: () => void
  view?: 'grid' | 'table'
  onViewChange?: (view: 'grid' | 'table') => void
}

export function CustomersFilters({
  searchQuery,
  statusFilters,
  reportFilters,
  integrationFilters,
  totalCustomers,
  filteredCount,
  onSearchChange,
  onStatusFiltersChange,
  onReportFiltersChange,
  onIntegrationFiltersChange,
  onCreateStatic,
  view = 'grid',
  onViewChange,
}: CustomersFiltersProps) {
  const [integrationTypes, setIntegrationTypes] = useState<string[]>([])
  const hasActiveFilters = searchQuery || statusFilters.length > 0 || reportFilters.length > 0 || integrationFilters.length > 0
  const activeFilterCount = statusFilters.length + reportFilters.length + integrationFilters.length

  const { user } = useAuth()
  const canViewIntegrations = hasPermission(user, 'integrations', 'view')

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
        logger.error('Failed to fetch integration types:', error)
      }
    }
    
    if (canViewIntegrations) {
      fetchIntegrationTypes()
    }
  }, [canViewIntegrations])

  const toggleStatusFilter = (status: string) => {
    if (statusFilters.includes(status)) {
      onStatusFiltersChange(statusFilters.filter(s => s !== status))
    } else {
      onStatusFiltersChange([...statusFilters, status])
    }
  }

  const toggleReportFilter = (filter: string) => {
    if (reportFilters.includes(filter)) {
      onReportFiltersChange(reportFilters.filter(f => f !== filter))
    } else {
      onReportFiltersChange([...reportFilters, filter])
    }
  }

  const toggleIntegrationFilter = (integration: string) => {
    if (integrationFilters.includes(integration)) {
      onIntegrationFiltersChange(integrationFilters.filter(i => i !== integration))
    } else {
      onIntegrationFiltersChange([...integrationFilters, integration])
    }
  }

  return (
    <div className="space-y-3">
      <div className="flex flex-col md:flex-row gap-3">
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
            <DropdownMenuLabel>Status</DropdownMenuLabel>
            <DropdownMenuCheckboxItem
              checked={statusFilters.includes('active')}
              onCheckedChange={() => toggleStatusFilter('active')}
            >
              Active
            </DropdownMenuCheckboxItem>
            <DropdownMenuCheckboxItem
              checked={statusFilters.includes('inactive')}
              onCheckedChange={() => toggleStatusFilter('inactive')}
            >
              Inactive
            </DropdownMenuCheckboxItem>

            <DropdownMenuSeparator />

            <DropdownMenuLabel>Customers</DropdownMenuLabel>
            <DropdownMenuCheckboxItem
              checked={reportFilters.includes('configured')}
              onCheckedChange={() => toggleReportFilter('configured')}
            >
              Configured
            </DropdownMenuCheckboxItem>
            <DropdownMenuCheckboxItem
              checked={reportFilters.includes('not-configured')}
              onCheckedChange={() => toggleReportFilter('not-configured')}
            >
              Not Configured
            </DropdownMenuCheckboxItem>

            {integrationTypes.length > 0 && (
              <>
                <DropdownMenuSeparator />
                <DropdownMenuLabel>Integrations</DropdownMenuLabel>
                {integrationTypes.map((type) => (
                  <DropdownMenuCheckboxItem
                    key={type}
                    checked={integrationFilters.includes(type)}
                    onCheckedChange={() => toggleIntegrationFilter(type)}
                    className="capitalize"
                  >
                    {type}
                  </DropdownMenuCheckboxItem>
                ))}
              </>
            )}
          </DropdownMenuContent>
        </DropdownMenu>

        {onViewChange && (
          <Tabs value={view} onValueChange={(v) => onViewChange(v as 'grid' | 'table')} className="w-auto">
            <TabsList className="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 p-1 h-10">
              <TabsTrigger value="grid" className="data-[state=active]:bg-primary data-[state=active]:text-primary-foreground data-[state=active]:shadow-sm px-3 rounded-md">
                <LayoutGrid className="h-4 w-4" />
                <span className="ml-2 hidden sm:inline">Grid</span>
              </TabsTrigger>
              <TabsTrigger value="table" className="data-[state=active]:bg-primary data-[state=active]:text-primary-foreground data-[state=active]:shadow-sm px-3 rounded-md">
                <List className="h-4 w-4" />
                <span className="ml-2 hidden sm:inline">Table</span>
              </TabsTrigger>
            </TabsList>
          </Tabs>
        )}

        <div className="flex-1 relative">
          <Search className="absolute left-3 top-1/2 transform -translate-y-1/2 h-4 w-4 text-muted-foreground" />
          <Input
            placeholder="Search customers..."
            value={searchQuery}
            onChange={(e) => onSearchChange(e.target.value)}
            className="pl-10 h-10"
          />
        </div>

        {onCreateStatic && (
          <Button onClick={onCreateStatic} className="flex items-center gap-2 h-10">
            <UserPlus className="h-4 w-4" />
            Create Static Customer
          </Button>
        )}
      </div>

      {hasActiveFilters && (
        <div className="flex items-center gap-2 text-sm text-muted-foreground">
          <Filter className="h-4 w-4" />
          <span>Showing {filteredCount} of {totalCustomers} customers</span>
        </div>
      )}
    </div>
  )
}
