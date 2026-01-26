import {
  Dialog,
  DialogContent,
  DialogHeader,
  DialogTitle,
  DialogDescription,
} from '@/components/ui/dialog'
import { Badge } from '@/components/ui/badge'
import { Clock, User, Target, MapPin, Monitor, Database } from 'lucide-react'
import { type AuditLog, EVENT_TYPES } from '../types'

interface AuditLogDetailDialogProps {
  log: AuditLog | null
  isOpen: boolean
  onClose: () => void
}

export function AuditLogDetailDialog({ log, isOpen, onClose }: AuditLogDetailDialogProps) {
  if (!log) return null

  const getEventBadgeVariant = (eventType: string) => {
    if (eventType.includes('deleted') || eventType.includes('failed')) return 'destructive'
    if (eventType.includes('created') || eventType.includes('login')) return 'default'
    return 'secondary'
  }

  return (
    <Dialog open={isOpen} onOpenChange={(open) => !open && onClose()}>
      <DialogContent className="max-w-3xl max-h-[90vh] overflow-y-auto">
        <DialogHeader>
          <div className="flex items-center gap-2 mb-2">
            <Badge variant={getEventBadgeVariant(log.event_type)}>
              {EVENT_TYPES[log.event_type as keyof typeof EVENT_TYPES] || log.event_type}
            </Badge>
            <span className="text-xs text-gray-400 font-mono">#{log.audit_log_id}</span>
          </div>
          <DialogTitle className="text-2xl font-bold text-gray-900 dark:text-white">
            Audit Event Details
          </DialogTitle>
          <DialogDescription>
            Detailed technical information about this system event.
          </DialogDescription>
        </DialogHeader>

        <div className="mt-6 space-y-8">
          {/* Main Info Grid */}
          <div className="grid grid-cols-1 md:grid-cols-2 gap-x-8 gap-y-6">
            <div className="space-y-4">
              <section>
                <h4 className="text-xs font-semibold uppercase tracking-wider text-gray-500 mb-3 flex items-center gap-2">
                  <User className="h-4 w-4" />
                  Actor (Subject)
                </h4>
                <div className="p-3 border rounded-xl bg-gray-50/50 dark:bg-gray-800/50 dark:border-gray-700">
                  <p className="text-sm font-semibold text-gray-900 dark:text-white">{log.actor_name || 'System'}</p>
                  <p className="text-xs text-gray-500 dark:text-gray-400 mt-1">{log.actor_type} ID: {log.actor_id || 'N/A'}</p>
                </div>
              </section>

              <section>
                <h4 className="text-xs font-semibold uppercase tracking-wider text-gray-500 mb-3 flex items-center gap-2">
                  <Target className="h-4 w-4" />
                  Target (Object)
                </h4>
                <div className="p-3 border rounded-xl bg-gray-50/50 dark:bg-gray-800/50 dark:border-gray-700">
                  <p className="text-sm font-semibold text-gray-900 dark:text-white">{log.target_name || '-'}</p>
                  <p className="text-xs text-gray-500 dark:text-gray-400 mt-1">{log.target_type || 'N/A'} ID: {log.target_id || 'N/A'}</p>
                </div>
              </section>
            </div>

            <div className="space-y-4">
              <section>
                <h4 className="text-xs font-semibold uppercase tracking-wider text-gray-500 mb-3 flex items-center gap-2">
                  <Clock className="h-4 w-4" />
                  Time & Origin
                </h4>
                <div className="p-3 border rounded-xl bg-gray-50/50 dark:bg-gray-800/50 dark:border-gray-700 space-y-2">
                  <div className="flex items-center gap-2 text-sm">
                    <Clock className="h-3.5 w-3.5 text-gray-400" />
                    <span className="text-gray-900 dark:text-white">{new Date(log.created_at).toLocaleString()}</span>
                  </div>
                  <div className="flex items-center gap-2 text-sm">
                    <MapPin className="h-3.5 w-3.5 text-gray-400" />
                    <span className="text-gray-900 dark:text-white font-mono">{log.ip_address || 'Internal'}</span>
                  </div>
                </div>
              </section>

              {log.user_agent && (
                <section>
                  <h4 className="text-xs font-semibold uppercase tracking-wider text-gray-500 mb-3 flex items-center gap-2">
                    <Monitor className="h-4 w-4" />
                    User Agent
                  </h4>
                  <div className="p-3 border rounded-xl bg-gray-50/50 dark:bg-gray-800/50 dark:border-gray-700">
                    <p className="text-[11px] font-mono text-gray-600 dark:text-gray-400 break-all">
                      {log.user_agent}
                    </p>
                  </div>
                </section>
              )}
            </div>
          </div>

          {/* Event Payload */}
          {log.event_data && Object.keys(log.event_data).length > 0 && (
            <section>
              <h4 className="text-xs font-semibold uppercase tracking-wider text-gray-500 mb-3 flex items-center gap-2">
                <Database className="h-4 w-4" />
                Event Data Payload
              </h4>
              <div className="rounded-xl border border-gray-200 dark:border-gray-700 overflow-hidden shadow-inner">
                <div className="bg-gray-50 dark:bg-gray-800/50 px-4 py-2 border-b dark:border-gray-700 flex justify-between items-center">
                  <span className="text-[10px] font-medium text-gray-500">JSON DATA</span>
                  <Badge variant="outline" className="text-[10px] h-4">Object</Badge>
                </div>
                <div className="p-4 bg-white dark:bg-gray-900 overflow-x-auto max-h-[400px]">
                  <pre className="text-xs font-mono text-indigo-600 dark:text-indigo-400">
                    {JSON.stringify(log.event_data, null, 2)}
                  </pre>
                </div>
              </div>
            </section>
          )}
        </div>
      </DialogContent>
    </Dialog>
  )
}
