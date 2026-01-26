import { useState } from 'react'
import {
  User,
  Settings,
  Key,
  Shield,
  FileText,
  Users,
  Eye,
  Clock,
  MapPin,
} from 'lucide-react'
import { Button } from '@/components/ui/button'
import { Badge } from '@/components/ui/badge'
import { type AuditLog, EVENT_TYPES } from '../types'
import { AuditLogDetailDialog } from './AuditLogDetailDialog'

interface AuditLogsTableProps {
  logs: AuditLog[]
  isLoading: boolean
  error: string | null
}

// Helper function to format relative time
function formatRelativeTime(date: Date): string {
  const now = new Date()
  const diffInSeconds = Math.floor((now.getTime() - date.getTime()) / 1000)

  if (diffInSeconds < 60) return 'just now'
  if (diffInSeconds < 3600) return `${Math.floor(diffInSeconds / 60)} minutes ago`
  if (diffInSeconds < 86400) return `${Math.floor(diffInSeconds / 3600)} hours ago`
  if (diffInSeconds < 604800) return `${Math.floor(diffInSeconds / 86400)} days ago`
  return `${Math.floor(diffInSeconds / 604800)} weeks ago`
}

export function AuditLogsTable({
  logs,
  isLoading,
  error,
}: AuditLogsTableProps) {
  const [selectedLog, setSelectedLog] = useState<AuditLog | null>(null)

  const getEventIcon = (eventType: string) => {
    if (eventType.includes('user')) return User
    if (eventType.includes('integration')) return Settings
    if (eventType.includes('api_key')) return Key
    if (eventType.includes('customer')) return Users
    if (eventType.includes('report')) return FileText
    return Shield
  }

  const getEventColorScale = (eventType: string) => {
    if (eventType.includes('deleted') || eventType.includes('failed')) {
      return { bg: 'bg-red-100/80 dark:bg-red-900/30', iconBg: 'bg-red-500', text: 'text-red-600 dark:text-red-400', badge: 'destructive' as const }
    }
    if (eventType.includes('created') || eventType.includes('login') || eventType.includes('synced')) {
      return { bg: 'bg-emerald-100/80 dark:bg-emerald-900/30', iconBg: 'bg-emerald-500', text: 'text-emerald-600 dark:text-emerald-400', badge: 'default' as const }
    }
    if (eventType.includes('updated') || eventType.includes('merged')) {
      return { bg: 'bg-amber-100/80 dark:bg-amber-900/30', iconBg: 'bg-amber-500', text: 'text-amber-600 dark:text-amber-400', badge: 'secondary' as const }
    }
    return { bg: 'bg-blue-100/80 dark:bg-blue-900/30', iconBg: 'bg-blue-500', text: 'text-blue-600 dark:text-blue-400', badge: 'outline' as const }
  }

  const getActorBadgeColor = (actorType: string): "default" | "secondary" | "outline" => {
    if (actorType === 'System') return 'secondary'
    if (actorType === 'ApiKey') return 'outline'
    return 'default'
  }

  if (isLoading) {
    return (
      <div className="rounded-xl border border-gray-200 bg-white p-8 shadow-sm dark:border-gray-700 dark:bg-gray-800">
        <div className="space-y-4">
          {[...Array(5)].map((_, i) => (
            <div key={i} className="animate-pulse">
              <div className="flex items-center gap-4">
                <div className="h-10 w-10 rounded-lg bg-gray-200 dark:bg-gray-700" />
                <div className="flex-1 space-y-2">
                  <div className="h-4 w-1/3 rounded bg-gray-200 dark:bg-gray-700" />
                  <div className="h-3 w-1/2 rounded bg-gray-200 dark:bg-gray-700" />
                </div>
              </div>
            </div>
          ))}
        </div>
      </div>
    )
  }

  if (error) {
    return (
      <div className="rounded-xl border border-red-100 bg-red-50 p-8 text-center dark:border-red-900/30 dark:bg-red-900/10">
        <p className="text-red-600 dark:text-red-400">{error}</p>
      </div>
    )
  }

  if (logs.length === 0) {
    return (
      <div className="rounded-xl border border-dashed border-gray-200 bg-white p-12 text-center dark:border-gray-700 dark:bg-gray-800">
        <Shield className="mx-auto h-12 w-12 text-gray-400" />
        <h3 className="mt-4 text-lg font-semibold text-gray-900 dark:text-white">
          No audit logs found
        </h3>
        <p className="mt-2 text-sm text-gray-600 dark:text-gray-400">
          Try adjusting your filters to see more results
        </p>
      </div>
    )
  }

  return (
    <div className="space-y-4">
      {/* Desktop Table View */}
      <div className="hidden md:block overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800">
        <div className="overflow-x-auto">
          <table className="w-full">
            <thead className="border-b border-gray-200 bg-gray-50 dark:border-gray-700 dark:bg-gray-900/50">
              <tr>
                <th className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-600 dark:text-gray-400">
                  Event
                </th>
                <th className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-600 dark:text-gray-400">
                  Actor
                </th>
                <th className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-600 dark:text-gray-400">
                  Target
                </th>
                <th className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-600 dark:text-gray-400">
                  Time
                </th>
                <th className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-600 dark:text-gray-400">
                  IP Address
                </th>
                <th className="w-10 px-4 py-3"></th>
              </tr>
            </thead>
            <tbody className="divide-y divide-gray-200 dark:divide-gray-700">
              {logs.map((log) => {
                const EventIcon = getEventIcon(log.event_type)
                const colors = getEventColorScale(log.event_type)

                return (
                  <tr
                    key={log.audit_log_id}
                    className="group transition-colors hover:bg-gray-50 dark:hover:bg-gray-700/50"
                  >
                    {/* Event */}
                    <td className="px-4 py-4">
                      <div className="flex items-center gap-3">
                        <div className={`flex h-10 w-10 items-center justify-center rounded-lg ${colors.bg}`}>
                          <EventIcon className={`h-5 w-5 ${colors.text}`} />
                        </div>
                        <div>
                          <div className="font-medium text-gray-900 dark:text-white">
                            {EVENT_TYPES[log.event_type as keyof typeof EVENT_TYPES] || log.event_type}
                          </div>
                          <Badge variant={colors.badge} className="mt-1">
                            {log.event_type}
                          </Badge>
                        </div>
                      </div>
                    </td>

                    {/* Actor */}
                    <td className="px-4 py-4">
                      <div className="flex flex-col gap-1">
                        <span className="text-sm font-medium text-gray-900 dark:text-white">
                          {log.actor_name || 'Unknown'}
                        </span>
                        <Badge variant={getActorBadgeColor(log.actor_type)}>
                          {log.actor_type}
                        </Badge>
                      </div>
                    </td>

                    {/* Target */}
                    <td className="px-4 py-4">
                      <div className="flex flex-col gap-1">
                        <span className="text-sm text-gray-900 dark:text-white">
                          {log.target_name || '-'}
                        </span>
                        {log.target_type && (
                          <span className="text-xs text-gray-500 dark:text-gray-400">
                            {log.target_type}
                          </span>
                        )}
                      </div>
                    </td>

                    {/* Time */}
                    <td className="px-4 py-4">
                      <div className="flex items-center gap-1.5 text-sm text-gray-600 dark:text-gray-400">
                        <Clock className="h-4 w-4 text-gray-400" />
                        <span>
                          {formatRelativeTime(new Date(log.created_at))}
                        </span>
                      </div>
                      <div className="mt-1 text-xs text-gray-500 dark:text-gray-500">
                        {new Date(log.created_at).toLocaleString()}
                      </div>
                    </td>

                    {/* IP Address */}
                    <td className="px-4 py-4">
                      <div className="flex items-center gap-1.5 text-sm text-gray-600 dark:text-gray-400">
                        <MapPin className="h-4 w-4 text-gray-400" />
                        <span className="font-mono text-xs">
                          {log.ip_address || '-'}
                        </span>
                      </div>
                    </td>

                    {/* View Button */}
                    <td className="px-4 py-4 text-right">
                      <Button
                        variant="ghost"
                        size="icon"
                        onClick={() => setSelectedLog(log)}
                        className="h-8 w-8 opacity-0 group-hover:opacity-100 transition-opacity"
                        title="View technical details"
                      >
                        <Eye className="h-4 w-4 text-gray-400" />
                      </Button>
                    </td>
                  </tr>
                )
              })}
            </tbody>
          </table>
        </div>
      </div>

      {/* Mobile Card View */}
      <div className="grid grid-cols-1 gap-4 md:hidden">
        {logs.map((log) => {
          const EventIcon = getEventIcon(log.event_type)
          const colors = getEventColorScale(log.event_type)

          return (
            <div
              key={log.audit_log_id}
              className="rounded-xl border border-gray-200 bg-white p-4 shadow-sm active:bg-gray-50 dark:border-gray-700 dark:bg-gray-800"
              onClick={() => setSelectedLog(log)}
            >
              <div className="flex items-start justify-between gap-4">
                <div className="flex items-start gap-3">
                  <div className={`mt-1 flex h-10 w-10 shrink-0 items-center justify-center rounded-lg ${colors.bg}`}>
                    <EventIcon className={`h-5 w-5 ${colors.text}`} />
                  </div>
                  <div className="min-w-0">
                    <div className="font-semibold text-gray-900 truncate dark:text-white">
                      {EVENT_TYPES[log.event_type as keyof typeof EVENT_TYPES] || log.event_type}
                    </div>
                    <div className="mt-1 flex flex-wrap gap-2">
                      <Badge variant={colors.badge} className="px-1.5 py-0 text-[10px]">
                        {log.event_type}
                      </Badge>
                    </div>
                  </div>
                </div>
                <Button variant="ghost" size="icon" className="h-8 w-8 shrink-0">
                  <Eye className="h-4 w-4 text-gray-400" />
                </Button>
              </div>

              <div className="mt-4 grid grid-cols-2 gap-y-3 border-t pt-3 dark:border-gray-700">
                <div>
                  <div className="text-[10px] font-medium uppercase tracking-wider text-gray-500">Actor</div>
                  <div className="mt-0.5 text-xs font-medium text-gray-900 dark:text-white truncate">
                    {log.actor_name || 'System'}
                  </div>
                  <Badge variant={getActorBadgeColor(log.actor_type)} className="mt-1 h-4 px-1.5 text-[9px]">
                    {log.actor_type}
                  </Badge>
                </div>
                <div>
                  <div className="text-[10px] font-medium uppercase tracking-wider text-gray-500">Target</div>
                  <div className="mt-0.5 text-xs text-gray-900 dark:text-white truncate">
                    {log.target_name || '-'}
                  </div>
                  {log.target_type && (
                    <div className="text-[9px] text-gray-500 mt-0.5">{log.target_type}</div>
                  )}
                </div>
                <div className="col-span-2 flex items-center justify-between border-t pt-2 dark:border-gray-700">
                  <div className="flex items-center gap-1.5 text-[10px] text-gray-500">
                    <Clock className="h-3 w-3" />
                    <span>{formatRelativeTime(new Date(log.created_at))}</span>
                  </div>
                  <div className="flex items-center gap-1.5 text-[10px] text-gray-500">
                    <MapPin className="h-3 w-3" />
                    <span className="font-mono">{log.ip_address || '-'}</span>
                  </div>
                </div>
              </div>
            </div>
          )
        })}
      </div>

      <AuditLogDetailDialog
        log={selectedLog}
        isOpen={!!selectedLog}
        onClose={() => setSelectedLog(null)}
      />
    </div>
  )
}
