import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { UserPlus, Users, Search, SlidersHorizontal } from 'lucide-react'
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
    <div className="flex flex-col gap-6 mb-8">
      <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div className="space-y-1">
          <h1 className="text-3xl font-bold tracking-tight text-foreground flex items-center gap-3">
            <div className="p-2 rounded-lg bg-primary/10 text-primary">
              <Users className="h-6 w-6" />
            </div>
            Users
          </h1>
          <p className="text-muted-foreground text-lg pl-14">
            Manage access and permissions for your team.
          </p>
        </div>
        <Button
          onClick={onCreateUser}
          size="lg"
          className="shadow-lg hover:shadow-primary/25 transition-all duration-300 bg-primary hover:bg-primary/90"
        >
          <UserPlus className="mr-2 h-5 w-5" />
          Add Team Member
        </Button>
      </div>

      {/* Toolbar */}
      <div className="flex items-center gap-4 p-1">
        <div className="relative flex-1 max-w-md">
          <Search className="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground" />
          <Input
            placeholder="Search users by name or email..."
            className="pl-10 h-11 bg-background/50 border-muted-foreground/20 focus:border-primary/50 transition-colors"
            value={searchQuery}
            onChange={(e) => onSearch(e.target.value)}
          />
        </div>
        <DropdownMenu>
          <DropdownMenuTrigger asChild>
            <Button variant="outline" size="icon" className={`h-11 w-11 border-muted-foreground/20 ${statusFilter !== 'all' ? 'text-primary border-primary/50 bg-primary/5' : ''}`}>
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
      </div>
    </div>
  )
}
