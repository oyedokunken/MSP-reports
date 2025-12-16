import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { UserPlus, Search, SlidersHorizontal } from 'lucide-react'
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu'

interface UsersHeaderProps {
  onCreateUser: () => void
  onSearch: (query: string) => void
  searchQuery: string
  onFilterChange: (filter: string) => void
  statusFilter: string
}

export function UsersHeader({
  onCreateUser,
  onSearch,
  searchQuery,
  onFilterChange,
  statusFilter
}: UsersHeaderProps) {
  return (
    <div className="flex flex-col gap-4">
      <div className="space-y-1">
        <h1 className="text-3xl font-semibold tracking-tight text-foreground">MSP Users</h1>
        <p className="text-sm text-muted-foreground">
          Manage access and permissions for your team
        </p>
      </div>

      {/* Toolbar */}
      <div className="flex items-center gap-3">
        <div className="relative flex-1 max-w-md">
          <Search className="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground" />
          <Input
            placeholder="Search users..."
            className="pl-10 h-9 text-sm"
            value={searchQuery}
            onChange={(e) => onSearch(e.target.value)}
          />
        </div>
        <DropdownMenu>
          <DropdownMenuTrigger asChild>
            <Button variant="outline" size="icon" className={`h-9 w-9 ${statusFilter !== 'all' ? 'text-primary border-primary/50 bg-primary/5' : ''}`}>
              <SlidersHorizontal className="h-4 w-4" />
            </Button>
          </DropdownMenuTrigger>
          <DropdownMenuContent align="end">
            <DropdownMenuItem onClick={() => onFilterChange('all')}>
              All Users
            </DropdownMenuItem>
            <DropdownMenuItem onClick={() => onFilterChange('active')}>
              Active Only
            </DropdownMenuItem>
            <DropdownMenuItem onClick={() => onFilterChange('inactive')}>
              Inactive Only
            </DropdownMenuItem>
            <DropdownMenuItem onClick={() => onFilterChange('admin')}>
              Admins Only
            </DropdownMenuItem>
          </DropdownMenuContent>
        </DropdownMenu>
        <Button
          onClick={onCreateUser}
          className="gap-2"
        >
          <UserPlus className="h-4 w-4" />
          Add User
        </Button>
      </div>
    </div>
  )
}
