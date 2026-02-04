import { useEffect, useState } from 'react'
import { Link } from 'react-router-dom'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Button } from '@/components/ui/button'

import {
  Mail, Users, Database, Archive, Shield, RefreshCw,
  TrendingUp, HardDrive
} from 'lucide-react'
import { useToast } from '@/components/ui/Toast'
import { SyncStatus } from './components/SyncStatus'
import { DashboardSkeleton } from './components/Skeletons'

interface ExchangeStats {
  totalMailboxes: number
  userMailboxes: number
  sharedMailboxes: number
  resourceMailboxes: number
  totalSizeMB: number
  averageSizeMB: number
  mailboxesWithArchive: number
  mailboxesOnLitigationHold: number
  lastSyncAt: string | null
  nextSyncAt: string | null
  syncStatus: 'active' | 'error' | 'syncing' | 'never'
  domain: string
}

export default function ExchangeDashboard() {
  const toast = useToast()
  const [stats, setStats] = useState<ExchangeStats | null>(null)
  const [isLoading, setIsLoading] = useState(true)

  useEffect(() => {
    // TODO: Implement API endpoint
    setIsLoading(false)
    setStats({
      totalMailboxes: 0,
      userMailboxes: 0,
      sharedMailboxes: 0,
      resourceMailboxes: 0,
      totalSizeMB: 0,
      averageSizeMB: 0,
      mailboxesWithArchive: 0,
      mailboxesOnLitigationHold: 0,
      lastSyncAt: null,
      nextSyncAt: null,
      syncStatus: 'never',
      domain: 'Not configured'
    })
  }, [])

  const handleSyncNow = async () => {
    // TODO: Implement sync endpoint
    toast.error('Not Implemented', 'Sync endpoint not yet implemented')
  }

  const formatSize = (mb: number) => {
    if (mb >= 1024) {
      return `${(mb / 1024).toFixed(2)} GB`
    }
    return `${mb.toFixed(2)} MB`
  }

  if (isLoading) {
    return (
      <DashboardSkeleton />
    )
  }

  if (!stats) {
    return (
      <div className="text-center py-12">
        <Mail className="w-12 h-12 text-muted-foreground mx-auto mb-4" />
        <p className="text-muted-foreground">No Exchange data available</p>
      </div>
    )
  }

  return (
    <div className="space-y-6">
        <div className="flex items-center justify-between">
          <div>
            <h1 className="text-3xl font-bold text-foreground">Exchange Online</h1>
            <p className="text-muted-foreground mt-1">
              Mailbox management and statistics
            </p>
          </div>
          <Button onClick={handleSyncNow}>
            <RefreshCw className={`w-4 h-4 mr-2`} />
            Sync Now
          </Button>
        </div>

        <SyncStatus stats={{
          lastSyncAt: stats.lastSyncAt,
          nextSyncAt: stats.nextSyncAt,
          syncStatus: stats.syncStatus,
          domain: stats.domain
        }} />

        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
          <Card>
            <CardHeader className="pb-2">
              <CardTitle className="text-sm font-medium text-gray-500">Total Mailboxes</CardTitle>
            </CardHeader>
            <CardContent>
              <div className="flex items-center justify-between">
                <div>
                  <div className="text-3xl font-bold text-foreground">{stats.totalMailboxes}</div>
                  <p className="text-xs text-muted-foreground mt-1">All mailbox types</p>
                </div>
                <div className="rounded-full bg-blue-100 p-3">
                  <Mail className="w-6 h-6 text-blue-600" />
                </div>
              </div>
            </CardContent>
          </Card>

          <Card>
            <CardHeader className="pb-2">
              <CardTitle className="text-sm font-medium text-gray-500">User Mailboxes</CardTitle>
            </CardHeader>
            <CardContent>
              <div className="flex items-center justify-between">
                <div>
                  <div className="text-3xl font-bold text-foreground">{stats.userMailboxes}</div>
                  <p className="text-xs text-muted-foreground mt-1">Individual users</p>
                </div>
                <div className="rounded-full bg-green-100 p-3">
                  <Users className="w-6 h-6 text-green-600" />
                </div>
              </div>
            </CardContent>
          </Card>

          <Card>
            <CardHeader className="pb-2">
              <CardTitle className="text-sm font-medium text-gray-500">Shared Mailboxes</CardTitle>
            </CardHeader>
            <CardContent>
              <div className="flex items-center justify-between">
                <div>
                  <div className="text-3xl font-bold text-foreground">{stats.sharedMailboxes}</div>
                  <p className="text-xs text-muted-foreground mt-1">Shared accounts</p>
                </div>
                <div className="rounded-full bg-purple-100 p-3">
                  <Database className="w-6 h-6 text-purple-600" />
                </div>
              </div>
            </CardContent>
          </Card>

          <Card>
            <CardHeader className="pb-2">
              <CardTitle className="text-sm font-medium text-gray-500">Total Storage</CardTitle>
            </CardHeader>
            <CardContent>
              <div className="flex items-center justify-between">
                <div>
                  <div className="text-3xl font-bold text-foreground">{formatSize(stats.totalSizeMB)}</div>
                  <p className="text-xs text-muted-foreground mt-1">Across all mailboxes</p>
                </div>
                <div className="rounded-full bg-orange-100 p-3">
                  <HardDrive className="w-6 h-6 text-orange-600" />
                </div>
              </div>
            </CardContent>
          </Card>
        </div>

        <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
          <Card>
            <CardHeader>
              <CardTitle className="flex items-center text-base">
                <Archive className="w-5 h-5 mr-2 text-blue-500" />
                Archive Enabled
              </CardTitle>
            </CardHeader>
            <CardContent>
              <div className="text-2xl font-bold text-foreground">{stats.mailboxesWithArchive}</div>
              <p className="text-sm text-muted-foreground mt-1">
                {stats.totalMailboxes > 0
                  ? `${((stats.mailboxesWithArchive / stats.totalMailboxes) * 100).toFixed(1)}% of mailboxes`
                  : 'No mailboxes'}
              </p>
            </CardContent>
          </Card>

          <Card>
            <CardHeader>
              <CardTitle className="flex items-center text-base">
                <Shield className="w-5 h-5 mr-2 text-red-500" />
                Litigation Hold
              </CardTitle>
            </CardHeader>
            <CardContent>
              <div className="text-2xl font-bold text-foreground">{stats.mailboxesOnLitigationHold}</div>
              <p className="text-sm text-muted-foreground mt-1">
                {stats.totalMailboxes > 0
                  ? `${((stats.mailboxesOnLitigationHold / stats.totalMailboxes) * 100).toFixed(1)}% of mailboxes`
                  : 'No mailboxes'}
              </p>
            </CardContent>
          </Card>

          <Card>
            <CardHeader>
              <CardTitle className="flex items-center text-base">
                <TrendingUp className="w-5 h-5 mr-2 text-green-500" />
                Average Size
              </CardTitle>
            </CardHeader>
            <CardContent>
              <div className="text-2xl font-bold text-foreground">{formatSize(stats.averageSizeMB)}</div>
              <p className="text-sm text-muted-foreground mt-1">Per mailbox</p>
            </CardContent>
          </Card>
        </div>

        <Card>
          <CardHeader>
            <CardTitle>Quick Actions</CardTitle>
            <CardDescription>Common Exchange management tasks</CardDescription>
          </CardHeader>
          <CardContent>
            <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
              <Link to="/exchange/mailboxes">
                <Button variant="outline" className="w-full justify-start">
                  <Mail className="w-4 h-4 mr-2" />
                  View All Mailboxes
                </Button>
              </Link>
              <Link to="/exchange/mailboxes?type=shared">
                <Button variant="outline" className="w-full justify-start">
                  <Database className="w-4 h-4 mr-2" />
                  Shared Mailboxes
                </Button>
              </Link>
              <Link to="/exchange/mailboxes?archive=true">
                <Button variant="outline" className="w-full justify-start">
                  <Archive className="w-4 h-4 mr-2" />
                  Archive Mailboxes
                </Button>
              </Link>
            </div>
          </CardContent>
        </Card>
      </div>
  )
}
