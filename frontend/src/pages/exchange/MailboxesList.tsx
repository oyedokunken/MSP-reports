import { useEffect, useState } from 'react'
import { Link, useSearchParams } from 'react-router-dom'
import FloatingLayout from '@/components/layout/FloatingLayout'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Badge } from '@/components/ui/badge'
import { Search, Download, RefreshCw, Mail, Database, Home, Users as UsersIcon, Archive, Shield } from 'lucide-react'
import { useToast } from '@/components/ui/Toast'
import { MailboxesListSkeleton } from './components/Skeletons'

interface Mailbox {
  id: number
  displayName: string
  primarySmtpAddress: string
  recipientType: string
  recipientTypeDetails: string
  isShared: boolean
  isResource: boolean
  resourceType: string | null
  totalItemSizeMB: number
  itemCount: number
  archiveEnabled: boolean
  litigationHoldEnabled: boolean
  databaseName: string
  lastLogonTime: string | null
}

export default function MailboxesList() {
  const toast = useToast()
  const [searchParams] = useSearchParams()
  const [mailboxes, setMailboxes] = useState<Mailbox[]>([])
  const [isLoading, setIsLoading] = useState(true)
  const [searchQuery, setSearchQuery] = useState('')
  const [typeFilter, setTypeFilter] = useState<string>(searchParams.get('type') || 'all')
  const [archiveFilter, setArchiveFilter] = useState<string>(searchParams.get('archive') || 'all')

  useEffect(() => {
    // TODO: Implement API endpoint
    setIsLoading(false)
    setMailboxes([])
  }, [])

  const filteredMailboxes = mailboxes.filter(mailbox => {
    const matchesSearch = searchQuery === '' ||
      mailbox.displayName?.toLowerCase().includes(searchQuery.toLowerCase()) ||
      mailbox.primarySmtpAddress?.toLowerCase().includes(searchQuery.toLowerCase())

    const matchesType = typeFilter === 'all' ||
      (typeFilter === 'user' && !mailbox.isShared && !mailbox.isResource) ||
      (typeFilter === 'shared' && mailbox.isShared) ||
      (typeFilter === 'resource' && mailbox.isResource)

    const matchesArchive = archiveFilter === 'all' ||
      (archiveFilter === 'true' && mailbox.archiveEnabled) ||
      (archiveFilter === 'false' && !mailbox.archiveEnabled)

    return matchesSearch && matchesType && matchesArchive
  })

  const formatSize = (mb: number) => {
    if (mb >= 1024) {
      return `${(mb / 1024).toFixed(2)} GB`
    }
    return `${mb.toFixed(2)} MB`
  }

  const formatDate = (date: string | null) => {
    if (!date) return 'Never'
    return new Date(date).toLocaleDateString('en-AU', {
      year: 'numeric',
      month: 'short',
      day: 'numeric'
    })
  }

  const handleExport = () => {
    const csv = [
      ['Display Name', 'Email', 'Type', 'Size (MB)', 'Items', 'Archive', 'Litigation Hold', 'Last Logon'].join(','),
      ...filteredMailboxes.map(mailbox => [
        mailbox.displayName,
        mailbox.primarySmtpAddress,
        mailbox.recipientTypeDetails,
        mailbox.totalItemSizeMB,
        mailbox.itemCount,
        mailbox.archiveEnabled ? 'Yes' : 'No',
        mailbox.litigationHoldEnabled ? 'Yes' : 'No',
        formatDate(mailbox.lastLogonTime)
      ].join(','))
    ].join('\n')

    const blob = new Blob([csv], { type: 'text/csv' })
    const url = window.URL.createObjectURL(blob)
    const a = document.createElement('a')
    a.href = url
    a.download = `exchange-mailboxes-${new Date().toISOString().split('T')[0]}.csv`
    a.click()
    toast.success('Exported', 'Mailboxes list exported successfully')
  }

  const getTypeIcon = (mailbox: Mailbox) => {
    if (mailbox.isShared) return <Database className="w-5 h-5 text-purple-600" />
    if (mailbox.isResource) {
      return mailbox.resourceType === 'Room'
        ? <Home className="w-5 h-5 text-blue-600" />
        : <UsersIcon className="w-5 h-5 text-green-600" />
    }
    return <Mail className="w-5 h-5 text-gray-600" />
  }

  const getTypeBadge = (mailbox: Mailbox) => {
    if (mailbox.isShared) return <Badge className="bg-purple-500">Shared</Badge>
    if (mailbox.isResource) {
      return <Badge className="bg-blue-500">{mailbox.resourceType || 'Resource'}</Badge>
    }
    return <Badge className="bg-gray-500">User</Badge>
  }

  if (isLoading) {
    return (
      <FloatingLayout>
        <MailboxesListSkeleton />
      </FloatingLayout>
    )
  }

  return (
    <FloatingLayout>
      <div className="space-y-6">
        <div className="flex items-center justify-between">
          <div>
            <h1 className="text-3xl font-bold text-foreground">Exchange Mailboxes</h1>
            <p className="text-muted-foreground mt-1">
              {filteredMailboxes.length} of {mailboxes.length} mailboxes
            </p>
          </div>
          <div className="flex space-x-2">
            <Button variant="outline" onClick={handleExport}>
              <Download className="w-4 h-4 mr-2" />
              Export
            </Button>
            <Button onClick={() => toast.error('Not Implemented', 'API endpoint not yet implemented')}>
              <RefreshCw className="w-4 h-4 mr-2" />
              Refresh
            </Button>
          </div>
        </div>

        <Card>
          <CardContent className="pt-6">
            <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
              <div>
                <div className="relative">
                  <Search className="absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400 w-4 h-4" />
                  <Input
                    placeholder="Search mailboxes..."
                    value={searchQuery}
                    onChange={(e) => setSearchQuery(e.target.value)}
                    className="pl-10"
                  />
                </div>
              </div>

              <div>
                <select
                  value={typeFilter}
                  onChange={(e) => setTypeFilter(e.target.value)}
                  className="w-full px-3 py-2 border border-input bg-background rounded-md focus:outline-none focus:ring-2 focus:ring-ring"
                >
                  <option value="all">All Types</option>
                  <option value="user">User Mailboxes</option>
                  <option value="shared">Shared Mailboxes</option>
                  <option value="resource">Resource Mailboxes</option>
                </select>
              </div>

              <div>
                <select
                  value={archiveFilter}
                  onChange={(e) => setArchiveFilter(e.target.value)}
                  className="w-full px-3 py-2 border border-input bg-background rounded-md focus:outline-none focus:ring-2 focus:ring-ring"
                >
                  <option value="all">All Mailboxes</option>
                  <option value="true">Archive Enabled</option>
                  <option value="false">No Archive</option>
                </select>
              </div>
            </div>
          </CardContent>
        </Card>

        <Card>
          <CardHeader>
            <CardTitle>Mailboxes</CardTitle>
          </CardHeader>
          <CardContent>
            {filteredMailboxes.length === 0 ? (
              <div className="text-center py-12">
                <Mail className="w-12 h-12 text-muted-foreground mx-auto mb-4" />
                <p className="text-muted-foreground">No mailboxes found</p>
              </div>
            ) : (
              <div className="overflow-x-auto">
                <table className="w-full">
                  <thead className="bg-muted/50 border-b">
                    <tr>
                      <th className="px-4 py-3 text-left text-xs font-medium text-muted-foreground uppercase">Mailbox</th>
                      <th className="px-4 py-3 text-left text-xs font-medium text-muted-foreground uppercase">Type</th>
                      <th className="px-4 py-3 text-left text-xs font-medium text-muted-foreground uppercase">Size</th>
                      <th className="px-4 py-3 text-left text-xs font-medium text-muted-foreground uppercase">Items</th>
                      <th className="px-4 py-3 text-left text-xs font-medium text-muted-foreground uppercase">Features</th>
                      <th className="px-4 py-3 text-left text-xs font-medium text-muted-foreground uppercase">Last Logon</th>
                      <th className="px-4 py-3 text-right text-xs font-medium text-muted-foreground uppercase">Actions</th>
                    </tr>
                  </thead>
                  <tbody className="divide-y divide-border">
                    {filteredMailboxes.map((mailbox) => (
                      <tr key={mailbox.id} className="hover:bg-muted/50">
                        <td className="px-4 py-4">
                          <div className="flex items-center">
                            <div className="flex-shrink-0 h-10 w-10 bg-blue-100 dark:bg-blue-900/30 rounded-full flex items-center justify-center">
                              {getTypeIcon(mailbox)}
                            </div>
                            <div className="ml-4">
                              <div className="text-sm font-medium text-foreground">{mailbox.displayName}</div>
                              <div className="text-sm text-muted-foreground">{mailbox.primarySmtpAddress}</div>
                            </div>
                          </div>
                        </td>
                        <td className="px-4 py-4">
                          {getTypeBadge(mailbox)}
                        </td>
                        <td className="px-4 py-4 text-sm text-foreground">
                          {formatSize(mailbox.totalItemSizeMB)}
                        </td>
                        <td className="px-4 py-4 text-sm text-foreground">
                          {mailbox.itemCount?.toLocaleString() || 0}
                        </td>
                        <td className="px-4 py-4">
                          <div className="flex space-x-2">
                            {mailbox.archiveEnabled && (
                              <Badge variant="outline" className="text-xs">
                                <Archive className="w-3 h-3 mr-1" />
                                Archive
                              </Badge>
                            )}
                            {mailbox.litigationHoldEnabled && (
                              <Badge variant="outline" className="text-xs text-red-600 dark:text-red-400 border-red-600 dark:border-red-400">
                                <Shield className="w-3 h-3 mr-1" />
                                Hold
                              </Badge>
                            )}
                          </div>
                        </td>
                        <td className="px-4 py-4 text-sm text-muted-foreground">
                          {formatDate(mailbox.lastLogonTime)}
                        </td>
                        <td className="px-4 py-4 text-right">
                          <Link to={`/exchange/mailboxes/${mailbox.id}`}>
                            <Button variant="ghost" size="sm">
                              View Details
                            </Button>
                          </Link>
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            )}
          </CardContent>
        </Card>
      </div>
    </FloatingLayout>
  )
}
