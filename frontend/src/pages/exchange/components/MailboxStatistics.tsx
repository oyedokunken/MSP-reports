import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { HardDrive, Mail, Trash2, Calendar, AlertTriangle } from 'lucide-react'

interface MailboxStatisticsProps {
  mailbox: {
    totalItemSizeMB: number
    totalDeletedItemSizeMB: number
    itemCount: number
    deletedItemCount: number
    lastLogonTime: string | null
    lastLogoffTime: string | null
    prohibitSendQuotaMB: number | null
    prohibitSendReceiveQuotaMB: number | null
    issueWarningQuotaMB: number | null
  }
}

export function MailboxStatistics({ mailbox }: MailboxStatisticsProps) {
  const formatSize = (mb: number | null) => {
    if (!mb) return 'N/A'
    if (mb >= 1024) {
      return `${(mb / 1024).toFixed(2)} GB`
    }
    return `${mb.toFixed(2)} MB`
  }

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

  const getUsagePercentage = () => {
    if (!mailbox.prohibitSendReceiveQuotaMB || mailbox.prohibitSendReceiveQuotaMB === 0) {
      return 0
    }
    return (mailbox.totalItemSizeMB / mailbox.prohibitSendReceiveQuotaMB) * 100
  }

  const usagePercentage = getUsagePercentage()
  const isNearQuota = usagePercentage > 80

  return (
    <Card>
      <CardHeader>
        <CardTitle className="flex items-center">
          <HardDrive className="w-5 h-5 mr-2" />
          Mailbox Statistics
        </CardTitle>
      </CardHeader>
      <CardContent>
        <div className="space-y-6">
          {mailbox.prohibitSendReceiveQuotaMB && (
            <div>
              <div className="flex items-center justify-between mb-2">
                <span className="text-sm font-medium text-foreground">Storage Usage</span>
                <span className="text-sm text-muted-foreground">
                  {formatSize(mailbox.totalItemSizeMB)} / {formatSize(mailbox.prohibitSendReceiveQuotaMB)}
                </span>
              </div>
              <div className="w-full bg-muted rounded-full h-3">
                <div
                  className={`h-3 rounded-full transition-all ${
                    isNearQuota ? 'bg-red-500' : 'bg-blue-500'
                  }`}
                  style={{ width: `${Math.min(usagePercentage, 100)}%` }}
                />
              </div>
              {isNearQuota && (
                <div className="flex items-center mt-2 text-sm text-red-600">
                  <AlertTriangle className="w-4 h-4 mr-1" />
                  Mailbox is near quota limit
                </div>
              )}
            </div>
          )}

          <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div className="flex items-start space-x-3">
              <HardDrive className="w-5 h-5 text-muted-foreground mt-0.5" />
              <div>
                <p className="text-sm font-medium text-muted-foreground">Total Size</p>
                <p className="text-base text-foreground">{formatSize(mailbox.totalItemSizeMB)}</p>
              </div>
            </div>

            <div className="flex items-start space-x-3">
              <Mail className="w-5 h-5 text-muted-foreground mt-0.5" />
              <div>
                <p className="text-sm font-medium text-muted-foreground">Item Count</p>
                <p className="text-base text-foreground">{mailbox.itemCount?.toLocaleString() || 0}</p>
              </div>
            </div>

            <div className="flex items-start space-x-3">
              <Trash2 className="w-5 h-5 text-muted-foreground mt-0.5" />
              <div>
                <p className="text-sm font-medium text-muted-foreground">Deleted Items Size</p>
                <p className="text-base text-foreground">{formatSize(mailbox.totalDeletedItemSizeMB)}</p>
              </div>
            </div>

            <div className="flex items-start space-x-3">
              <Trash2 className="w-5 h-5 text-muted-foreground mt-0.5" />
              <div>
                <p className="text-sm font-medium text-muted-foreground">Deleted Item Count</p>
                <p className="text-base text-foreground">{mailbox.deletedItemCount?.toLocaleString() || 0}</p>
              </div>
            </div>

            <div className="flex items-start space-x-3">
              <Calendar className="w-5 h-5 text-muted-foreground mt-0.5" />
              <div>
                <p className="text-sm font-medium text-muted-foreground">Last Logon</p>
                <p className="text-base text-foreground">{formatDate(mailbox.lastLogonTime)}</p>
              </div>
            </div>

            <div className="flex items-start space-x-3">
              <Calendar className="w-5 h-5 text-muted-foreground mt-0.5" />
              <div>
                <p className="text-sm font-medium text-muted-foreground">Last Logoff</p>
                <p className="text-base text-foreground">{formatDate(mailbox.lastLogoffTime)}</p>
              </div>
            </div>
          </div>

          {(mailbox.prohibitSendQuotaMB || mailbox.prohibitSendReceiveQuotaMB || mailbox.issueWarningQuotaMB) && (
            <div className="pt-6 border-t">
              <h4 className="text-sm font-semibold text-foreground mb-4">Quotas</h4>
              <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                {mailbox.issueWarningQuotaMB && (
                  <div>
                    <p className="text-sm font-medium text-muted-foreground">Warning Quota</p>
                    <p className="text-base text-foreground">{formatSize(mailbox.issueWarningQuotaMB)}</p>
                  </div>
                )}
                {mailbox.prohibitSendQuotaMB && (
                  <div>
                    <p className="text-sm font-medium text-muted-foreground">Prohibit Send</p>
                    <p className="text-base text-foreground">{formatSize(mailbox.prohibitSendQuotaMB)}</p>
                  </div>
                )}
                {mailbox.prohibitSendReceiveQuotaMB && (
                  <div>
                    <p className="text-sm font-medium text-muted-foreground">Prohibit Send/Receive</p>
                    <p className="text-base text-foreground">{formatSize(mailbox.prohibitSendReceiveQuotaMB)}</p>
                  </div>
                )}
              </div>
            </div>
          )}
        </div>
      </CardContent>
    </Card>
  )
}
