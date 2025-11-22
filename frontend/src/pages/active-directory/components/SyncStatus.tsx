import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Badge } from '@/components/ui/badge'
import { CheckCircle, AlertCircle, RefreshCw, Clock } from 'lucide-react'

interface SyncStatusProps {
  stats: {
    lastSyncAt: string | null
    nextSyncAt: string | null
    syncStatus: 'active' | 'error' | 'syncing' | 'never'
    domain: string
  }
}

export function SyncStatus({ stats }: SyncStatusProps) {
  const formatDate = (date: string | null) => {
    if (!date) return 'Never'
    return new Date(date).toLocaleString('en-AU', {
      year: 'numeric',
      month: 'short',
      day: 'numeric',
      hour: '2-digit',
      minute: '2-digit'
    })
  }

  const getStatusInfo = () => {
    switch (stats.syncStatus) {
      case 'active':
        return {
          icon: <CheckCircle className="w-5 h-5 text-green-500" />,
          badge: <Badge className="bg-green-500">Active</Badge>,
          message: 'Active Directory is syncing successfully',
          color: 'text-green-700',
          bgColor: 'bg-green-50',
          borderColor: 'border-green-200'
        }
      case 'syncing':
        return {
          icon: <RefreshCw className="w-5 h-5 text-blue-500 animate-spin" />,
          badge: <Badge className="bg-blue-500">Syncing</Badge>,
          message: 'Sync in progress...',
          color: 'text-blue-700',
          bgColor: 'bg-blue-50',
          borderColor: 'border-blue-200'
        }
      case 'error':
        return {
          icon: <AlertCircle className="w-5 h-5 text-red-500" />,
          badge: <Badge className="bg-red-500">Error</Badge>,
          message: 'Last sync failed. Check integration settings.',
          color: 'text-red-700',
          bgColor: 'bg-red-50',
          borderColor: 'border-red-200'
        }
      default:
        return {
          icon: <Clock className="w-5 h-5 text-gray-500" />,
          badge: <Badge className="bg-gray-500">Never Synced</Badge>,
          message: 'Waiting for first sync',
          color: 'text-gray-700',
          bgColor: 'bg-gray-50',
          borderColor: 'border-gray-200'
        }
    }
  }

  const statusInfo = getStatusInfo()

  return (
    <Card className={`border-2 ${statusInfo.borderColor} ${statusInfo.bgColor}`}>
      <CardHeader>
        <div className="flex items-center justify-between">
          <CardTitle className="flex items-center">
            {statusInfo.icon}
            <span className="ml-2">Sync Status</span>
          </CardTitle>
          {statusInfo.badge}
        </div>
      </CardHeader>
      <CardContent>
        <div className="space-y-4">
          <p className={`text-sm ${statusInfo.color}`}>{statusInfo.message}</p>
          
          <div className="grid grid-cols-1 md:grid-cols-3 gap-4 pt-4 border-t">
            <div>
              <p className="text-xs font-medium text-gray-500 uppercase mb-1">Domain</p>
              <p className="text-sm font-semibold text-gray-900">{stats.domain || 'N/A'}</p>
            </div>
            
            <div>
              <p className="text-xs font-medium text-gray-500 uppercase mb-1">Last Sync</p>
              <p className="text-sm font-semibold text-gray-900">{formatDate(stats.lastSyncAt)}</p>
            </div>
            
            <div>
              <p className="text-xs font-medium text-gray-500 uppercase mb-1">Next Sync</p>
              <p className="text-sm font-semibold text-gray-900">{formatDate(stats.nextSyncAt)}</p>
            </div>
          </div>
        </div>
      </CardContent>
    </Card>
  )
}
