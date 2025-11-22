import { useEffect, useState } from 'react'
import { Link } from 'react-router-dom'
import FloatingLayout from '@/components/layout/FloatingLayout'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Badge } from '@/components/ui/badge'
import { Users, Shield, RefreshCw, AlertCircle, CheckCircle, Clock, TrendingUp } from 'lucide-react'
import { useToast } from '@/components/ui/Toast'
import { SyncStatus } from './components/SyncStatus'
import { DashboardSkeleton } from './components/Skeletons'

interface ADStats {
  totalUsers: number
  enabledUsers: number
  disabledUsers: number
  totalGroups: number
  totalMemberships: number
  lastSyncAt: string | null
  nextSyncAt: string | null
  syncStatus: 'active' | 'error' | 'syncing' | 'never'
  domain: string
}

export default function ActiveDirectoryDashboard() {
  const toast = useToast()
  const [stats, setStats] = useState<ADStats | null>(null)
  const [isLoading, setIsLoading] = useState(true)

  useEffect(() => {
    // TODO: Implement API endpoints
    // fetchStats()
    setIsLoading(false)
    // Mock data for now
    setStats({
      totalUsers: 0,
      enabledUsers: 0,
      disabledUsers: 0,
      totalGroups: 0,
      totalMemberships: 0,
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

  const getStatusBadge = (status: string) => {
    switch (status) {
      case 'active':
        return <Badge className="bg-green-500"><CheckCircle className="w-3 h-3 mr-1" />Active</Badge>
      case 'syncing':
        return <Badge className="bg-blue-500"><RefreshCw className="w-3 h-3 mr-1 animate-spin" />Syncing</Badge>
      case 'error':
        return <Badge className="bg-red-500"><AlertCircle className="w-3 h-3 mr-1" />Error</Badge>
      default:
        return <Badge className="bg-gray-500"><Clock className="w-3 h-3 mr-1" />Never Synced</Badge>
    }
  }

  if (isLoading) {
    return (
      <FloatingLayout>
        <DashboardSkeleton />
      </FloatingLayout>
    )
  }

  return (
    <FloatingLayout>
      <div className="space-y-6">
        {/* Header */}
        <div className="flex items-center justify-between">
          <div>
            <h1 className="text-3xl font-bold text-gray-900">Active Directory</h1>
            <p className="text-gray-500 mt-1">Monitor and manage Active Directory integration</p>
          </div>
          <Button onClick={handleSyncNow}>
            <RefreshCw className={`w-4 h-4 mr-2`} />
            Sync Now
          </Button>
        </div>

        {/* Sync Status Card */}
        {stats && <SyncStatus stats={stats} />}

        {/* Statistics Grid */}
        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
          {/* Total Users */}
          <Card>
            <CardHeader className="flex flex-row items-center justify-between pb-2">
              <CardTitle className="text-sm font-medium text-gray-600">Total Users</CardTitle>
              <Users className="w-4 h-4 text-gray-400" />
            </CardHeader>
            <CardContent>
              <div className="text-2xl font-bold">{stats?.totalUsers || 0}</div>
              <p className="text-xs text-gray-500 mt-1">
                {stats?.enabledUsers || 0} enabled, {stats?.disabledUsers || 0} disabled
              </p>
              <Link to="/active-directory/users">
                <Button variant="link" className="px-0 mt-2 text-sm">
                  View all users →
                </Button>
              </Link>
            </CardContent>
          </Card>

          {/* Security Groups */}
          <Card>
            <CardHeader className="flex flex-row items-center justify-between pb-2">
              <CardTitle className="text-sm font-medium text-gray-600">Security Groups</CardTitle>
              <Shield className="w-4 h-4 text-gray-400" />
            </CardHeader>
            <CardContent>
              <div className="text-2xl font-bold">{stats?.totalGroups || 0}</div>
              <p className="text-xs text-gray-500 mt-1">
                {stats?.totalMemberships || 0} total memberships
              </p>
              <Link to="/active-directory/groups">
                <Button variant="link" className="px-0 mt-2 text-sm">
                  View all groups →
                </Button>
              </Link>
            </CardContent>
          </Card>

          {/* Domain */}
          <Card>
            <CardHeader className="flex flex-row items-center justify-between pb-2">
              <CardTitle className="text-sm font-medium text-gray-600">Domain</CardTitle>
              <TrendingUp className="w-4 h-4 text-gray-400" />
            </CardHeader>
            <CardContent>
              <div className="text-2xl font-bold truncate">{stats?.domain || 'N/A'}</div>
              <p className="text-xs text-gray-500 mt-1">
                {stats && getStatusBadge(stats.syncStatus)}
              </p>
            </CardContent>
          </Card>

          {/* Last Sync */}
          <Card>
            <CardHeader className="flex flex-row items-center justify-between pb-2">
              <CardTitle className="text-sm font-medium text-gray-600">Last Sync</CardTitle>
              <Clock className="w-4 h-4 text-gray-400" />
            </CardHeader>
            <CardContent>
              <div className="text-sm font-medium">{formatDate(stats?.lastSyncAt || null)}</div>
              <p className="text-xs text-gray-500 mt-1">
                Next: {formatDate(stats?.nextSyncAt || null)}
              </p>
            </CardContent>
          </Card>
        </div>

        {/* Quick Actions */}
        <Card>
          <CardHeader>
            <CardTitle>Quick Actions</CardTitle>
            <CardDescription>Common Active Directory management tasks</CardDescription>
          </CardHeader>
          <CardContent>
            <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
              <Link to="/active-directory/users">
                <Button variant="outline" className="w-full justify-start">
                  <Users className="w-4 h-4 mr-2" />
                  View All Users
                </Button>
              </Link>
              <Link to="/active-directory/groups">
                <Button variant="outline" className="w-full justify-start">
                  <Shield className="w-4 h-4 mr-2" />
                  View Security Groups
                </Button>
              </Link>
              <Link to="/integrations">
                <Button variant="outline" className="w-full justify-start">
                  <RefreshCw className="w-4 h-4 mr-2" />
                  Integration Settings
                </Button>
              </Link>
            </div>
          </CardContent>
        </Card>

        {/* Recent Activity */}
        <Card>
          <CardHeader>
            <CardTitle>Recent Sync Activity</CardTitle>
            <CardDescription>Latest synchronization events</CardDescription>
          </CardHeader>
          <CardContent>
            <div className="space-y-4">
              <div className="flex items-center justify-between py-2 border-b">
                <div className="flex items-center space-x-3">
                  <CheckCircle className="w-5 h-5 text-green-500" />
                  <div>
                    <p className="text-sm font-medium">Full sync completed</p>
                    <p className="text-xs text-gray-500">{formatDate(stats?.lastSyncAt || null)}</p>
                  </div>
                </div>
                <Badge variant="outline">Success</Badge>
              </div>
              <div className="text-center py-4 text-sm text-gray-500">
                View detailed sync logs in Integration Settings
              </div>
            </div>
          </CardContent>
        </Card>
      </div>
    </FloatingLayout>
  )
}
