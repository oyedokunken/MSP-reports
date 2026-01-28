import { Button } from '@/components/ui/button'
import { Badge } from '@/components/ui/badge'
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table'
import {
  Pagination,
  PaginationContent,
  PaginationItem,
  PaginationLink,
  PaginationNext,
  PaginationPrevious,
} from '@/components/ui/pagination'
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuLabel,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu'
import {
  Mail,
  Shield,
  MoreHorizontal,
  Edit,
  Settings,
  Lock,
  UserX,
  UserCheck,
  Trash2,
  UserPlus,
  CheckCircle2,
  XCircle,
  AlertCircle
} from 'lucide-react'
import { Card, CardContent } from '@/components/ui/card'
import { Skeleton } from '@/components/ui/skeleton'
import { formatDateOnly } from '../utils/formatters'
import type { UserWithPermissions } from '../types'
import { cn } from '@/lib/utils'

interface UsersTableProps {
  users: UserWithPermissions[]
  isLoading: boolean
  error: string | null
  currentUserId: number | undefined
  currentPage: number
  totalPages: number
  totalUsers: number
  onPageChange: (page: number) => void
  onCreateUser: () => void
  onEdit: (user: UserWithPermissions) => void
  onEditPermissions: (user: UserWithPermissions) => void
  onResetPassword: (user: UserWithPermissions) => void
  onDeactivate: (user: UserWithPermissions) => void
  onReactivate: (user: UserWithPermissions) => void
  onDelete: (user: UserWithPermissions) => void
}

export function UsersTable({
  users,
  isLoading,
  error,
  currentUserId,
  currentPage,
  totalPages,
  totalUsers,
  onPageChange,
  onCreateUser,
  onEdit,
  onEditPermissions,
  onResetPassword,
  onDeactivate,
  onReactivate,
  onDelete,
}: UsersTableProps) {
  const getStatusConfig = (status: string) => {
    switch (status) {
      case 'active':
        return { icon: CheckCircle2, color: 'text-green-600 dark:text-green-400', bg: 'bg-green-50 dark:bg-green-950/20', label: 'Active' }
      case 'inactive':
        return { icon: XCircle, color: 'text-slate-500', bg: 'bg-slate-50 dark:bg-slate-950/20', label: 'Inactive' }
      case 'locked':
        return { icon: AlertCircle, color: 'text-red-600 dark:text-red-400', bg: 'bg-red-50 dark:bg-red-950/20', label: 'Locked' }
      default:
        return { icon: CheckCircle2, color: 'text-slate-500', bg: 'bg-slate-50 dark:bg-slate-950/20', label: status }
    }
  }

  const getInitials = (name: string) => {
    return name
      .split(' ')
      .map((n) => n[0])
      .join('')
      .toUpperCase()
      .slice(0, 2)
  }

  if (isLoading) {
    return (
      <Card className="border-border/50">
        <CardContent className="p-0">
          <Table>
            <TableHeader>
              <TableRow className="hover:bg-transparent border-border/50">
                <TableHead className="w-[300px] h-10 py-2">User</TableHead>
                <TableHead className="h-10 py-2">Role</TableHead>
                <TableHead className="h-10 py-2">Status</TableHead>
                <TableHead className="h-10 py-2">Auth Method</TableHead>
                <TableHead className="h-10 py-2">Last Active</TableHead>
                <TableHead className="w-[80px] h-10 py-2"></TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {[1, 2, 3, 4, 5].map((i) => (
                <TableRow key={i} className="border-border/50">
                  <TableCell>
                    <div className="flex items-center gap-3">
                      <Skeleton className="h-10 w-10 rounded-full" />
                      <div className="space-y-2">
                        <Skeleton className="h-4 w-32" />
                        <Skeleton className="h-3 w-48" />
                      </div>
                    </div>
                  </TableCell>
                  <TableCell><Skeleton className="h-5 w-24" /></TableCell>
                  <TableCell><Skeleton className="h-5 w-16" /></TableCell>
                  <TableCell><Skeleton className="h-4 w-16" /></TableCell>
                  <TableCell><Skeleton className="h-4 w-24" /></TableCell>
                  <TableCell><Skeleton className="h-8 w-8 rounded" /></TableCell>
                </TableRow>
              ))}
            </TableBody>
          </Table>
        </CardContent>
      </Card>
    )
  }

  if (error) {
    return (
      <div className="rounded-lg border border-destructive/50 bg-destructive/10 p-4">
        <p className="text-sm text-destructive font-medium">{error}</p>
      </div>
    )
  }

  if (users.length === 0) {
    return (
      <Card className="border-border/50">
        <CardContent className="flex flex-col items-center justify-center py-12">
          <UserPlus className="h-12 w-12 text-muted-foreground mb-4" />
          <p className="text-lg font-medium">No users found</p>
          <p className="text-sm text-muted-foreground mb-4">
            Get started by creating your first user
          </p>
          <Button onClick={onCreateUser}>
            <UserPlus className="mr-2 h-4 w-4" />
            Create User
          </Button>
        </CardContent>
      </Card>
    )
  }

  const renderPagination = () => {
    if (totalPages <= 1) return null

    const pages = []
    const maxVisiblePages = 5
    let startPage = Math.max(1, currentPage - Math.floor(maxVisiblePages / 2))
    const endPage = Math.min(totalPages, startPage + maxVisiblePages - 1)

    if (endPage - startPage + 1 < maxVisiblePages) {
      startPage = Math.max(1, endPage - maxVisiblePages + 1)
    }

    for (let i = startPage; i <= endPage; i++) {
      pages.push(i)
    }

    return (
      <div className="flex items-center justify-between px-6 py-4 border-t border-border/50">
        <div className="text-sm text-muted-foreground">
          Showing {users.length} of {totalUsers} users
        </div>
        <Pagination>
          <PaginationContent>
            <PaginationItem>
              <PaginationPrevious 
                onClick={() => currentPage > 1 && onPageChange(currentPage - 1)}
                className={currentPage === 1 ? 'pointer-events-none opacity-50' : 'cursor-pointer'}
              />
            </PaginationItem>
            {pages.map((page) => (
              <PaginationItem key={page}>
                <PaginationLink
                  onClick={() => onPageChange(page)}
                  isActive={currentPage === page}
                  className="cursor-pointer"
                >
                  {page}
                </PaginationLink>
              </PaginationItem>
            ))}
            <PaginationItem>
              <PaginationNext 
                onClick={() => currentPage < totalPages && onPageChange(currentPage + 1)}
                className={currentPage === totalPages ? 'pointer-events-none opacity-50' : 'cursor-pointer'}
              />
            </PaginationItem>
          </PaginationContent>
        </Pagination>
      </div>
    )
  }

  return (
    <Card className="border-border/50 py-0">
      <CardContent className="p-0">
        <Table>
          <TableHeader>
            <TableRow className="hover:bg-transparent border-border/50">
              <TableHead className="w-[300px] font-medium h-10 py-2">User</TableHead>
              <TableHead className="font-medium h-10 py-2">Role</TableHead>
              <TableHead className="font-medium h-10 py-2">Status</TableHead>
              <TableHead className="font-medium h-10 py-2">Auth Method</TableHead>
              <TableHead className="font-medium h-10 py-2">Last Active</TableHead>
              <TableHead className="w-[80px] h-10 py-2"></TableHead>
            </TableRow>
          </TableHeader>
          <TableBody>
            {users.map((user) => {
              const isCurrentUser = user.user_id === currentUserId
              const statusConfig = getStatusConfig(user.status)
              const StatusIcon = statusConfig.icon
              const isAdmin = user.role.name.toLowerCase() === 'admin'

              return (
                <TableRow 
                  key={user.user_id} 
                  className={cn(
                    "border-border/50 hover:bg-muted/30 transition-colors",
                    isCurrentUser && "bg-primary/5"
                  )}
                >
                  <TableCell className="py-3">
                    <div className="flex items-center gap-3">
                      <div className="relative flex-shrink-0">
                        {user.profile_picture ? (
                          <img 
                            src={`${import.meta.env.VITE_API_URL?.replace('/api', '')}/storage/${user.profile_picture}`}
                            alt={user.name}
                            className="h-10 w-10 rounded-full object-cover"
                            onError={(e) => {
                              const target = e.target as HTMLImageElement
                              target.style.display = 'none'
                              target.nextElementSibling?.classList.remove('hidden')
                            }}
                          />
                        ) : null}
                        <div className={cn(
                          "h-10 w-10 rounded-full bg-primary/10 flex items-center justify-center text-primary font-semibold text-sm",
                          user.profile_picture && "hidden"
                        )}>
                          {getInitials(user.name)}
                        </div>
                        <div className={cn("absolute -bottom-0.5 -right-0.5 h-4 w-4 rounded-full border-2 border-card flex items-center justify-center", statusConfig.bg)}>
                          <StatusIcon className={cn("h-2.5 w-2.5", statusConfig.color)} />
                        </div>
                      </div>
                      <div className="min-w-0 flex-1">
                        <div className="flex items-center gap-2">
                          <p className="font-medium text-sm truncate">{user.name}</p>
                          {isCurrentUser && (
                            <Badge variant="outline" className="text-[10px] h-4 px-1.5 border-primary/30 text-primary bg-primary/5">
                              YOU
                            </Badge>
                          )}
                        </div>
                        <div className="flex items-center text-xs text-muted-foreground mt-0.5">
                          <Mail className="mr-1 h-3 w-3" />
                          <span className="truncate">{user.email}</span>
                        </div>
                      </div>
                    </div>
                  </TableCell>

                  <TableCell className="py-3">
                    <Badge 
                      variant={isAdmin ? "default" : "secondary"}
                      className={cn(
                        "text-xs font-medium",
                        isAdmin && "bg-blue-600 hover:bg-blue-700"
                      )}
                    >
                      {isAdmin && <Shield className="mr-1 h-3 w-3" />}
                      {user.role.name}
                    </Badge>
                  </TableCell>

                  <TableCell className="py-3">
                    <Badge 
                      variant="outline" 
                      className={cn("text-xs border-transparent", statusConfig.bg, statusConfig.color)}
                    >
                      {statusConfig.label}
                    </Badge>
                  </TableCell>

                  <TableCell className="py-3">
                    <span className="text-sm text-muted-foreground capitalize">{user.auth_method}</span>
                  </TableCell>

                  <TableCell className="py-3">
                    <span className="text-sm text-muted-foreground">
                      {user.last_login ? formatDateOnly(user.last_login) : 'Never'}
                    </span>
                  </TableCell>

                  <TableCell className="py-3">
                    <DropdownMenu>
                      <DropdownMenuTrigger asChild>
                        <Button variant="ghost" size="icon" className="h-8 w-8">
                          <MoreHorizontal className="h-4 w-4" />
                          <span className="sr-only">Open menu</span>
                        </Button>
                      </DropdownMenuTrigger>
                      <DropdownMenuContent align="end" className="w-56">
                        <DropdownMenuLabel>User Actions</DropdownMenuLabel>
                        <DropdownMenuItem onClick={() => onEdit(user)}>
                          <Edit className="mr-2 h-4 w-4" />
                          Edit Profile
                        </DropdownMenuItem>
                        {!isAdmin && (
                          <DropdownMenuItem onClick={() => onEditPermissions(user)}>
                            <Settings className="mr-2 h-4 w-4" />
                            Manage Permissions
                          </DropdownMenuItem>
                        )}
                        <DropdownMenuItem 
                          onClick={() => onResetPassword(user)} 
                          disabled={user.auth_method !== 'local' || isCurrentUser}
                        >
                          <Lock className="mr-2 h-4 w-4" />
                          Reset Password
                        </DropdownMenuItem>
                        <DropdownMenuSeparator />
                        {!isCurrentUser && (
                          <>
                            {user.status === 'active' ? (
                              <DropdownMenuItem 
                                onClick={() => onDeactivate(user)} 
                                className="text-orange-600 focus:text-orange-600 focus:bg-orange-50 dark:focus:bg-orange-950/20"
                              >
                                <UserX className="mr-2 h-4 w-4" />
                                Deactivate User
                              </DropdownMenuItem>
                            ) : (
                              <DropdownMenuItem 
                                onClick={() => onReactivate(user)} 
                                className="text-emerald-600 focus:text-emerald-600 focus:bg-emerald-50 dark:focus:bg-emerald-950/20"
                              >
                                <UserCheck className="mr-2 h-4 w-4" />
                                Reactivate User
                              </DropdownMenuItem>
                            )}
                            <DropdownMenuItem 
                              onClick={() => onDelete(user)} 
                              className="text-destructive focus:text-destructive focus:bg-destructive/10"
                            >
                              <Trash2 className="mr-2 h-4 w-4" />
                              Delete User
                            </DropdownMenuItem>
                          </>
                        )}
                      </DropdownMenuContent>
                    </DropdownMenu>
                  </TableCell>
                </TableRow>
              )
            })}
          </TableBody>
        </Table>
        {renderPagination()}
      </CardContent>
    </Card>
  )
}
