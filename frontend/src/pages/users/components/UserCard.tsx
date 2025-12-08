import { Button } from '@/components/ui/button'
import { Card, CardContent } from '@/components/ui/card'
import { Badge } from '@/components/ui/badge'
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
  Clock,
  MoreHorizontal,
  Edit,
  Settings,
  Lock,
  UserX,
  UserCheck,
  Trash2,
  Calendar,
  Key,
  CheckCircle2,
  XCircle,
  AlertCircle
} from 'lucide-react'
import { formatDate } from '../utils/formatters'
import type { UserWithPermissions } from '../types'
import { cn } from '@/lib/utils'

interface UserCardProps {
  user: UserWithPermissions
  isCurrentUser: boolean
  onEdit: () => void
  onEditPermissions: () => void
  onResetPassword: () => void
  onDeactivate: () => void
  onReactivate: () => void
  onDelete: () => void
}

export function UserCard({
  user,
  isCurrentUser,
  onEdit,
  onEditPermissions,
  onResetPassword,
  onDeactivate,
  onReactivate,
  onDelete,
}: UserCardProps) {

  const getStatusConfig = (status: string) => {
    switch (status) {
      case 'active':
        return { icon: CheckCircle2, color: 'text-emerald-500', bg: 'bg-emerald-500/10', label: 'Active' }
      case 'inactive':
        return { icon: XCircle, color: 'text-slate-500', bg: 'bg-slate-500/10', label: 'Inactive' }
      case 'locked':
        return { icon: AlertCircle, color: 'text-rose-500', bg: 'bg-rose-500/10', label: 'Locked' }
      default:
        return { icon: CheckCircle2, color: 'text-slate-500', bg: 'bg-slate-500/10', label: status }
    }
  }

  const getRoleConfig = (roleName: string) => {
    const isAdmin = roleName.toLowerCase() === 'admin'
    return {
      label: isAdmin ? 'Administrator' : 'Team Member',
      className: isAdmin
        ? 'bg-blue-600 text-white shadow-blue-600/20 shadow-lg border-transparent'
        : 'bg-secondary text-secondary-foreground border-transparent'
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

  const statusConfig = getStatusConfig(user.status)
  const roleConfig = getRoleConfig(user.role.name)
  const StatusIcon = statusConfig.icon

  return (
    <Card className="group relative overflow-hidden border-border/50 bg-card hover:border-primary/20 hover:shadow-xl hover:shadow-primary/5 transition-all duration-300">
      {/* Top Accent Line */}
      <div className={cn("absolute top-0 left-0 right-0 h-1 bg-gradient-to-r from-transparent via-primary/20 to-transparent opacity-0 group-hover:opacity-100 transition-opacity")} />

      <CardContent className="p-6">
        {/* Header Section */}
        <div className="flex items-start justify-between mb-6">
          <div className="flex items-center gap-4">
            <div className="relative">
              <div className="h-14 w-14 rounded-2xl bg-gradient-to-br from-primary/10 to-primary/5 flex items-center justify-center text-primary font-bold text-xl ring-1 ring-inset ring-black/5 dark:ring-white/10">
                {getInitials(user.name)}
              </div>
              <div className={cn("absolute -bottom-1 -right-1 h-5 w-5 rounded-full border-[3px] border-card flex items-center justify-center", statusConfig.bg)}>
                <StatusIcon className={cn("h-3 w-3", statusConfig.color)} />
              </div>
            </div>

            <div className="space-y-1">
              <div className="flex items-center gap-2">
                <h3 className="font-bold text-lg leading-none tracking-tight">{user.name}</h3>
                {isCurrentUser && (
                  <Badge variant="outline" className="text-[10px] h-5 px-1.5 border-primary/30 text-primary bg-primary/5">
                    YOU
                  </Badge>
                )}
              </div>
              <div className="flex items-center text-sm text-muted-foreground">
                <Mail className="mr-1.5 h-3.5 w-3.5 opacity-70" />
                {user.email}
              </div>
            </div>
          </div>

          <DropdownMenu>
            <DropdownMenuTrigger asChild>
              <Button variant="ghost" size="icon" className="h-8 w-8 -mr-2 text-muted-foreground hover:text-foreground">
                <MoreHorizontal className="h-5 w-5" />
                <span className="sr-only">Open menu</span>
              </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end" className="w-56">
              <DropdownMenuLabel>User Actions</DropdownMenuLabel>
              <DropdownMenuItem onClick={onEdit}>
                <Edit className="mr-2 h-4 w-4" />
                Edit Profile
              </DropdownMenuItem>
              {user.role.name.toLowerCase() !== 'admin' && (
                <DropdownMenuItem onClick={onEditPermissions}>
                  <Settings className="mr-2 h-4 w-4" />
                  Manage Permissions
                </DropdownMenuItem>
              )}
              <DropdownMenuItem onClick={onResetPassword} disabled={user.auth_method !== 'local' || isCurrentUser}>
                <Lock className="mr-2 h-4 w-4" />
                Reset Password
              </DropdownMenuItem>
              <DropdownMenuSeparator />
              {!isCurrentUser && (
                <>
                  {user.status === 'active' ? (
                    <DropdownMenuItem onClick={onDeactivate} className="text-orange-600 focus:text-orange-600 focus:bg-orange-50 dark:focus:bg-orange-950/20">
                      <UserX className="mr-2 h-4 w-4" />
                      Deactivate User
                    </DropdownMenuItem>
                  ) : (
                    <DropdownMenuItem onClick={onReactivate} className="text-emerald-600 focus:text-emerald-600 focus:bg-emerald-50 dark:focus:bg-emerald-950/20">
                      <UserCheck className="mr-2 h-4 w-4" />
                      Reactivate User
                    </DropdownMenuItem>
                  )}
                  <DropdownMenuItem onClick={onDelete} className="text-destructive focus:text-destructive focus:bg-destructive/10">
                    <Trash2 className="mr-2 h-4 w-4" />
                    Delete User
                  </DropdownMenuItem>
                </>
              )}
            </DropdownMenuContent>
          </DropdownMenu>
        </div>

        {/* Role Badge */}
        <div className="mb-6">
          <Badge variant="secondary" className={cn("px-3 py-1 text-xs font-medium rounded-full transition-colors", roleConfig.className)}>
            {user.role.name.toLowerCase() === 'admin' && <Shield className="mr-1.5 h-3 w-3" />}
            {roleConfig.label}
          </Badge>
        </div>

        {/* Details Grid */}
        <div className="grid grid-cols-2 gap-y-4 gap-x-2 py-4 border-t border-border/50">
          <div className="space-y-1">
            <span className="text-[10px] uppercase tracking-wider text-muted-foreground font-semibold flex items-center gap-1.5">
              <Key className="h-3 w-3" /> Auth
            </span>
            <p className="text-sm font-medium capitalize pl-4.5">{user.auth_method}</p>
          </div>

          <div className="space-y-1">
            <span className="text-[10px] uppercase tracking-wider text-muted-foreground font-semibold flex items-center gap-1.5">
              <Clock className="h-3 w-3" /> Last Active
            </span>
            <p className="text-sm font-medium pl-4.5 truncate" title={formatDate(user.last_login)}>
              {user.last_login ? formatDate(user.last_login).split(',')[0] : 'Never'}
            </p>
          </div>

          <div className="space-y-1 col-span-2">
            <span className="text-[10px] uppercase tracking-wider text-muted-foreground font-semibold flex items-center gap-1.5">
              <Calendar className="h-3 w-3" /> Joined
            </span>
            <p className="text-sm font-medium pl-4.5">{formatDate(user.created_at)}</p>
          </div>
        </div>
      </CardContent>
    </Card>
  )
}
