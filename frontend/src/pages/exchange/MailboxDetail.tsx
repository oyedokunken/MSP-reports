import { useEffect, useState } from 'react'
import { useParams, Link } from 'react-router-dom'
import FloatingLayout from '@/components/layout/FloatingLayout'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Badge } from '@/components/ui/badge'
import {
  ArrowLeft, Mail, Database, Archive, Shield, Users,
  Forward, AlertCircle, CheckCircle
} from 'lucide-react'
import { MailboxDetailSkeleton } from './components/Skeletons'
import { MailboxStatistics } from './components/MailboxStatistics'
import { EmailAliases } from './components/EmailAliases'
import { MailboxPermissions } from './components/MailboxPermissions'

interface MailboxDetail {
  id: number
  guid: string
  displayName: string
  alias: string
  primarySmtpAddress: string
  userPrincipalName: string
  samAccountName: string
  recipientType: string
  recipientTypeDetails: string
  isShared: boolean
  isResource: boolean
  resourceType: string | null
  databaseName: string
  serverName: string
  distinguishedName: string

  // Quotas
  prohibitSendQuotaMB: number | null
  prohibitSendReceiveQuotaMB: number | null
  issueWarningQuotaMB: number | null
  useDatabaseQuotaDefaults: boolean

  // Current usage
  totalItemSizeMB: number
  totalDeletedItemSizeMB: number
  itemCount: number
  deletedItemCount: number
  lastLogonTime: string | null
  lastLogoffTime: string | null

  // Archive
  archiveEnabled: boolean
  archiveGuid: string | null
  archiveName: string | null
  archiveDatabase: string | null
  archiveQuotaMB: number | null
  archiveSizeMB: number | null

  // Litigation hold
  litigationHoldEnabled: boolean
  litigationHoldDate: string | null
  litigationHoldOwner: string | null
  retentionPolicy: string | null

  // Forwarding
  forwardingAddress: string | null
  forwardingSmtpAddress: string | null
  deliverToMailboxAndForward: boolean

  // Organizational
  organizationalUnit: string | null
  office: string | null
  department: string | null
  company: string | null

  // Metadata
  whenCreated: string | null
  whenChanged: string | null
  exchangeVersion: string | null
}

export default function MailboxDetail() {
  const { id } = useParams<{ id: string }>()
  const [mailbox, setMailbox] = useState<MailboxDetail | null>(null)
  const [isLoading, setIsLoading] = useState(true)

  useEffect(() => {
    // TODO: Implement API endpoint
    // if (id) {
    //   fetchMailbox()
    // }
    setIsLoading(false)
    setMailbox(null)
  }, [id])

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

  const getTypeBadge = () => {
    if (!mailbox) return null
    if (mailbox.isShared) return <Badge className="bg-purple-500 text-lg px-4 py-2">Shared Mailbox</Badge>
    if (mailbox.isResource) {
      return <Badge className="bg-blue-500 text-lg px-4 py-2">{mailbox.resourceType || 'Resource'} Mailbox</Badge>
    }
    return <Badge className="bg-muted/500 text-lg px-4 py-2">User Mailbox</Badge>
  }

  if (isLoading) {
    return (
      <FloatingLayout>
        <MailboxDetailSkeleton />
      </FloatingLayout>
    )
  }

  if (!mailbox) {
    return (
      <FloatingLayout>
        <div className="text-center py-12">
          <Mail className="w-12 h-12 text-muted-foreground mx-auto mb-4" />
          <p className="text-muted-foreground">Mailbox not found</p>
          <Link to="/exchange/mailboxes">
            <Button className="mt-4">
              <ArrowLeft className="w-4 h-4 mr-2" />
              Back to Mailboxes
            </Button>
          </Link>
        </div>
      </FloatingLayout>
    )
  }

  return (
    <FloatingLayout>
      <div className="space-y-6">
        {/* Header */}
        <div className="flex items-center space-between">
          <div className="flex items-center space-x-4">
            <Link to="/exchange/mailboxes">
              <Button variant="ghost" className="flex items-center space-x-2">
                <ArrowLeft className="w-4 h-4" />
                <span>Back</span>
              </Button>
            </Link>
            <div>
              <h1 className="text-3xl font-bold text-foreground">{mailbox.displayName}</h1>
              <p className="text-muted-foreground mt-1">{mailbox.primarySmtpAddress}</p>
            </div>
          </div>
          <div className="flex space-x-2">
            {getTypeBadge()}
          </div>
        </div>


        {/* Basic Information */}
        <Card>
          <CardHeader>
            <CardTitle>Mailbox Information</CardTitle>
          </CardHeader>
          <CardContent>
            <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
              <div className="flex items-start space-x-3">
                <Mail className="w-5 h-5 text-muted-foreground mt-0.5" />
                <div>
                  <p className="text-sm font-medium text-muted-foreground">Display Name</p>
                  <p className="text-base text-foreground">{mailbox.displayName}</p>
                </div>
              </div>

              <div className="flex items-start space-x-3">
                <Mail className="w-5 h-5 text-muted-foreground mt-0.5" />
                <div>
                  <p className="text-sm font-medium text-muted-foreground">Alias</p>
                  <p className="text-base text-foreground">{mailbox.alias}</p>
                </div>
              </div>

              <div className="flex items-start space-x-3">
                <Mail className="w-5 h-5 text-muted-foreground mt-0.5" />
                <div>
                  <p className="text-sm font-medium text-muted-foreground">Primary SMTP Address</p>
                  <p className="text-base text-foreground">{mailbox.primarySmtpAddress}</p>
                </div>
              </div>

              <div className="flex items-start space-x-3">
                <Mail className="w-5 h-5 text-muted-foreground mt-0.5" />
                <div>
                  <p className="text-sm font-medium text-muted-foreground">User Principal Name</p>
                  <p className="text-base text-foreground">{mailbox.userPrincipalName || 'N/A'}</p>
                </div>
              </div>

              <div className="flex items-start space-x-3">
                <Database className="w-5 h-5 text-muted-foreground mt-0.5" />
                <div>
                  <p className="text-sm font-medium text-muted-foreground">Database</p>
                  <p className="text-base text-foreground">{mailbox.databaseName || 'N/A'}</p>
                </div>
              </div>

              <div className="flex items-start space-x-3">
                <Database className="w-5 h-5 text-muted-foreground mt-0.5" />
                <div>
                  <p className="text-sm font-medium text-muted-foreground">Server</p>
                  <p className="text-base text-foreground">{mailbox.serverName || 'N/A'}</p>
                </div>
              </div>

              {mailbox.department && (
                <div className="flex items-start space-x-3">
                  <Users className="w-5 h-5 text-muted-foreground mt-0.5" />
                  <div>
                    <p className="text-sm font-medium text-muted-foreground">Department</p>
                    <p className="text-base text-foreground">{mailbox.department}</p>
                  </div>
                </div>
              )}

              {mailbox.office && (
                <div className="flex items-start space-x-3">
                  <Users className="w-5 h-5 text-muted-foreground mt-0.5" />
                  <div>
                    <p className="text-sm font-medium text-muted-foreground">Office</p>
                    <p className="text-base text-foreground">{mailbox.office}</p>
                  </div>
                </div>
              )}
            </div>
          </CardContent>
        </Card>

        {/* Statistics */}
        <MailboxStatistics mailbox={mailbox} />

        {/* Email Aliases */}
        <EmailAliases mailboxId={mailbox.id} />

        {/* Permissions */}
        <MailboxPermissions mailboxId={mailbox.id} />

        {/* Archive Information */}
        {mailbox.archiveEnabled && (
          <Card>
            <CardHeader>
              <CardTitle className="flex items-center">
                <Archive className="w-5 h-5 mr-2" />
                Archive Mailbox
              </CardTitle>
            </CardHeader>
            <CardContent>
              <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                  <p className="text-sm font-medium text-muted-foreground">Archive Name</p>
                  <p className="text-base text-foreground">{mailbox.archiveName || 'N/A'}</p>
                </div>
                <div>
                  <p className="text-sm font-medium text-muted-foreground">Archive Database</p>
                  <p className="text-base text-foreground">{mailbox.archiveDatabase || 'N/A'}</p>
                </div>
                <div>
                  <p className="text-sm font-medium text-muted-foreground">Archive Size</p>
                  <p className="text-base text-foreground">{formatSize(mailbox.archiveSizeMB)}</p>
                </div>
                <div>
                  <p className="text-sm font-medium text-muted-foreground">Archive Quota</p>
                  <p className="text-base text-foreground">{formatSize(mailbox.archiveQuotaMB)}</p>
                </div>
              </div>
            </CardContent>
          </Card>
        )}

        {/* Litigation Hold */}
        {mailbox.litigationHoldEnabled && (
          <Card className="border-red-200 dark:border-red-900 bg-red-50 dark:bg-red-950/20">
            <CardHeader>
              <CardTitle className="flex items-center text-red-700">
                <Shield className="w-5 h-5 mr-2" />
                Litigation Hold Enabled
              </CardTitle>
            </CardHeader>
            <CardContent>
              <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                  <p className="text-sm font-medium text-muted-foreground">Hold Date</p>
                  <p className="text-base text-foreground">{formatDate(mailbox.litigationHoldDate)}</p>
                </div>
                <div>
                  <p className="text-sm font-medium text-muted-foreground">Hold Owner</p>
                  <p className="text-base text-foreground">{mailbox.litigationHoldOwner || 'N/A'}</p>
                </div>
                {mailbox.retentionPolicy && (
                  <div className="col-span-2">
                    <p className="text-sm font-medium text-muted-foreground">Retention Policy</p>
                    <p className="text-base text-foreground">{mailbox.retentionPolicy}</p>
                  </div>
                )}
              </div>
            </CardContent>
          </Card>
        )}

        {/* Forwarding */}
        {(mailbox.forwardingAddress || mailbox.forwardingSmtpAddress) && (
          <Card>
            <CardHeader>
              <CardTitle className="flex items-center">
                <Forward className="w-5 h-5 mr-2" />
                Email Forwarding
              </CardTitle>
            </CardHeader>
            <CardContent>
              <div className="space-y-4">
                {mailbox.forwardingSmtpAddress && (
                  <div>
                    <p className="text-sm font-medium text-muted-foreground">Forwarding To</p>
                    <p className="text-base text-foreground">{mailbox.forwardingSmtpAddress}</p>
                  </div>
                )}
                <div className="flex items-center space-x-2">
                  {mailbox.deliverToMailboxAndForward ? (
                    <>
                      <CheckCircle className="w-4 h-4 text-green-500" />
                      <span className="text-sm text-foreground">Deliver to mailbox and forward</span>
                    </>
                  ) : (
                    <>
                      <AlertCircle className="w-4 h-4 text-orange-500" />
                      <span className="text-sm text-foreground">Forward only (not delivered to mailbox)</span>
                    </>
                  )}
                </div>
              </div>
            </CardContent>
          </Card>
        )}

        {/* Technical Details */}
        <Card>
          <CardHeader>
            <CardTitle>Technical Details</CardTitle>
          </CardHeader>
          <CardContent>
            <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
              <div>
                <p className="text-sm font-medium text-muted-foreground">GUID</p>
                <p className="text-sm text-foreground font-mono">{mailbox.guid}</p>
              </div>
              <div>
                <p className="text-sm font-medium text-muted-foreground">Exchange Version</p>
                <p className="text-sm text-foreground">{mailbox.exchangeVersion || 'N/A'}</p>
              </div>
              <div>
                <p className="text-sm font-medium text-muted-foreground">Created</p>
                <p className="text-sm text-foreground">{formatDate(mailbox.whenCreated)}</p>
              </div>
              <div>
                <p className="text-sm font-medium text-muted-foreground">Last Modified</p>
                <p className="text-sm text-foreground">{formatDate(mailbox.whenChanged)}</p>
              </div>
              <div className="col-span-2">
                <p className="text-sm font-medium text-muted-foreground mb-1">Distinguished Name</p>
                <p className="text-sm text-foreground font-mono bg-muted/50 p-3 rounded break-all">
                  {mailbox.distinguishedName}
                </p>
              </div>
            </div>
          </CardContent>
        </Card>
      </div>
    </FloatingLayout>
  )
}
