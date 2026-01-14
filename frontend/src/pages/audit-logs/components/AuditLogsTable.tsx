import { useState } from 'react'
import { 
  User, 
  Settings, 
  Key, 
  Shield, 
  FileText, 
  Users,
  ChevronDown,
  ChevronUp,
  Clock,
  MapPin,
  Monitor,
} from 'lucide-react'
import { Button } from '@/components/ui/button'
import { Badge } from '@/components/ui/badge'
import type { AuditLog } from '../types'
import { EVENT_TYPES } from '../types'

interface AuditLogsTableProps {
  logs: AuditLog[]
  isLoading: boolean
  error: string | null
  currentPage: number
  totalPages: number
  totalLogs: number
  onPageChange: (page: number) => void
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
  currentPage,
  totalPages,
  totalLogs,
  onPageChange,
}: AuditLogsTableProps) {
  const [expandedRow, setExpandedRow] = useState<number | null>(null)

  const getEventIcon = (eventType: string) => {
    if (eventType.includes('user')) return User
    if (eventType.includes('integration')) return Settings
    if (eventType.includes('api_key')) return Key
    if (eventType.includes('customer')) return Users
    if (eventType.includes('report')) return FileText
    return Shield
  }

  const getEventColor = (eventType: string): "default" | "secondary" | "destructive" | "outline" => {
    if (eventType.includes('deleted')) return 'destructive'
    if (eventType.includes('created')) return 'default'
    if (eventType.includes('updated')) return 'secondary'
    return 'outline'
  }

  const getActorBadgeColor = (actorType: string): "default" | "secondary" | "outline" => {
    if (actorType === 'System') return 'secondary'
    if (actorType === 'ApiKey') return 'outline'
    return 'default'
  }

  const toggleRow = (logId: number) => {
    setExpandedRow(expandedRow === logId ? null : logId)
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
      <div className="rounded-xl border border-red-200 bg-red-50 p-8 text-center dark:border-red-800 dark:bg-red-900/20">
        <p className="text-red-600 dark:text-red-400">{error}</p>
      </div>
    )
  }

  if (logs.length === 0) {
    return (
      <div className="rounded-xl border border-gray-200 bg-white p-12 text-center shadow-sm dark:border-gray-700 dark:bg-gray-800">
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
      {/* Table */}
      <div className="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800">
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
                <th className="w-12 px-4 py-3"></th>
              </tr>
            </thead>
            <tbody className="divide-y divide-gray-200 dark:divide-gray-700">
              {logs.map((log) => {
                const EventIcon = getEventIcon(log.event_type)
                const isExpanded = expandedRow === log.audit_log_id
                
                return (
                  <>
                    <tr
                      key={log.audit_log_id}
                      className="transition-colors hover:bg-gray-50 dark:hover:bg-gray-700/50"
                    >
                      {/* Event */}
                      <td className="px-4 py-4">
                        <div className="flex items-center gap-3">
                          <div className="flex h-10 w-10 items-center justify-center rounded-lg bg-gradient-to-br from-purple-500 to-indigo-600">
                            <EventIcon className="h-5 w-5 text-white" />
                          </div>
                          <div>
                            <div className="font-medium text-gray-900 dark:text-white">
                              {EVENT_TYPES[log.event_type as keyof typeof EVENT_TYPES] || log.event_type}
                            </div>
                            <Badge variant={getEventColor(log.event_type)} className="mt-1">
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
                          <Clock className="h-4 w-4" />
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
                          <MapPin className="h-4 w-4" />
                          <span className="font-mono text-xs">
                            {log.ip_address || '-'}
                          </span>
                        </div>
                      </td>

                      {/* Expand Button */}
                      <td className="px-4 py-4">
                        <Button
                          variant="ghost"
                          size="sm"
                          onClick={() => toggleRow(log.audit_log_id)}
                          className="h-8 w-8 p-0"
                        >
                          {isExpanded ? (
                            <ChevronUp className="h-4 w-4" />
                          ) : (
                            <ChevronDown className="h-4 w-4" />
                          )}
                        </Button>
                      </td>
                    </tr>

                    {/* Expanded Row */}
                    {isExpanded && (
                      <tr className="bg-gray-50 dark:bg-gray-900/50">
                        <td colSpan={6} className="px-4 py-4">
                          <div className="space-y-3">
                            {/* User Agent */}
                            {log.user_agent && (
                              <div>
                                <div className="mb-1 flex items-center gap-2 text-xs font-semibold uppercase tracking-wider text-gray-600 dark:text-gray-400">
                                  <Monitor className="h-3.5 w-3.5" />
                                  User Agent
                                </div>
                                <div className="rounded-lg bg-white p-3 font-mono text-xs text-gray-700 dark:bg-gray-800 dark:text-gray-300">
                                  {log.user_agent}
                                </div>
                              </div>
                            )}

                            {/* Event Data */}
                            {log.event_data && Object.keys(log.event_data).length > 0 && (
                              <div>
                                <div className="mb-1 text-xs font-semibold uppercase tracking-wider text-gray-600 dark:text-gray-400">
                                  Event Data
                                </div>
                                <div className="rounded-lg bg-white p-3 dark:bg-gray-800">
                                  <pre className="overflow-x-auto text-xs text-gray-700 dark:text-gray-300">
                                    {JSON.stringify(log.event_data, null, 2)}
                                  </pre>
                                </div>
                              </div>
                            )}
                          </div>
                        </td>
                      </tr>
                    )}
                  </>
                )
              })}
            </tbody>
          </table>
        </div>
      </div>

      {/* Pagination */}
      {totalPages > 1 && (
        <div className="flex items-center justify-between rounded-xl border border-gray-200 bg-white px-4 py-3 shadow-sm dark:border-gray-700 dark:bg-gray-800">
          <div className="text-sm text-gray-600 dark:text-gray-400">
            Showing page {currentPage} of {totalPages} ({totalLogs} total logs)
          </div>
          
          <div className="flex gap-2">
            <Button
              variant="outline"
              size="sm"
              onClick={() => onPageChange(currentPage - 1)}
              disabled={currentPage === 1}
            >
              Previous
            </Button>
            
            <Button
              variant="outline"
              size="sm"
              onClick={() => onPageChange(currentPage + 1)}
              disabled={currentPage === totalPages}
            >
              Next
            </Button>
          </div>
        </div>
      )}
    </div>
  )
}
