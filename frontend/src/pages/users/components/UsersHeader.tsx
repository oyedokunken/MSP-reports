import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { UserPlus, Search, Filter } from 'lucide-react'
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuCheckboxItem,
  DropdownMenuLabel,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu'

interface UsersHeaderProps {
  onCreateUser: () => void
  onSearch: (query: string) => void
  searchQuery: string
  onFilterChange: (filter: string) => void
  statusFilter: string
  filteredCount: number
}

export function UsersHeader({
  onCreateUser,
  onSearch,
  searchQuery,
  onFilterChange,
  statusFilter,
  filteredCount
}: UsersHeaderProps) {
  const activeFilterCount = statusFilter !== 'all' ? 1 : 0

  return (
    <div className="space-y-4">
      <div>
        <h1 className="text-3xl font-bold tracking-tight">Users</h1>
        <p className="text-muted-foreground mt-2">
          Manage access and permissions for your team
        </p>
      </div>

      <div className="flex flex-col md:flex-row gap-3">
        <div className="flex items-center text-sm text-muted-foreground whitespace-nowrap px-3 h-10 border rounded-md">
          {filteredCount} user{filteredCount !== 1 ? 's' : ''}
        </div>

        <DropdownMenu>
          <DropdownMenuTrigger asChild>
            <Button variant="outline" className="w-full md:w-auto h-10 border">
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
              checked={statusFilter === 'active'}
              onCheckedChange={() => onFilterChange(statusFilter === 'active' ? 'all' : 'active')}
            >
              Active
            </DropdownMenuCheckboxItem>
            <DropdownMenuCheckboxItem
              checked={statusFilter === 'inactive'}
              onCheckedChange={() => onFilterChange(statusFilter === 'inactive' ? 'all' : 'inactive')}
            >
              Inactive
            </DropdownMenuCheckboxItem>
            <DropdownMenuSeparator />
            <DropdownMenuLabel>Role</DropdownMenuLabel>
            <DropdownMenuCheckboxItem
              checked={statusFilter === 'admin'}
              onCheckedChange={() => onFilterChange(statusFilter === 'admin' ? 'all' : 'admin')}
            >
              Admins Only
            </DropdownMenuCheckboxItem>
          </DropdownMenuContent>
        </DropdownMenu>

        <div className="flex-1 relative">
          <Search className="absolute left-3 top-1/2 transform -translate-y-1/2 h-4 w-4 text-muted-foreground" />
          <Input
            placeholder="Search users..."
            value={searchQuery}
            onChange={(e) => onSearch(e.target.value)}
            className="pl-10 h-10"
          />
        </div>

        <Button onClick={onCreateUser} className="h-10">
          <UserPlus className="h-4 w-4 mr-2" />
          Add User
        </Button>
      </div>
    </div>
  )
}
