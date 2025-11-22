import { useEffect, useState } from 'react'
import { useParams, Link } from 'react-router-dom'
import FloatingLayout from '@/components/layout/FloatingLayout'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Badge } from '@/components/ui/badge'
import { 
  ArrowLeft, User, Mail, Briefcase, Calendar, Shield, Key, 
  CheckCircle, XCircle, Clock, Building, Phone, MapPin, RefreshCw
} from 'lucide-react'
// import { api } from '@/lib/api' // TODO: Uncomment when API endpoints are implemented
// import { useToast } from '@/components/ui/Toast' // TODO: Uncomment when API endpoints are implemented
import { UserDetailSkeleton } from './components/Skeletons'

interface ADUserDetail {
  id: number
  userPrincipalName: string
  samAccountName: string
  displayName: string
  givenName: string
  surname: string
  email: string
  department: string
  title: string
  manager: string
  managerDN: string
  enabled: boolean
  lastLogon: string | null
  passwordLastSet: string | null
  passwordNeverExpires: boolean
  accountExpiration: string | null
  lockedOut: boolean
  created: string
  modified: string
  distinguishedName: string
  description: string
  office: string
  officePhone: string
  mobilePhone: string
  company: string
  groups: string[]
  licenses: Array<{
    skuPartNumber: string
    assignedDate: string
  }>
}

export default function UserDetail() {
  const { id } = useParams<{ id: string }>()
  // const toast = useToast() // TODO: Uncomment when API endpoints are implemented
  const [user, setUser] = useState<ADUserDetail | null>(null)
  const [isLoading, setIsLoading] = useState(true)

  useEffect(() => {
    // TODO: Implement API endpoint
    // if (id) {
    //   fetchUser()
    // }
    setIsLoading(false)
    setUser(null)
  }, [id])

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

  if (isLoading) {
    return (
      <FloatingLayout>
        <UserDetailSkeleton />
      </FloatingLayout>
    )
  }

  if (!user) {
    return (
      <FloatingLayout>
        <div className="text-center py-12">
          <User className="w-12 h-12 text-gray-300 mx-auto mb-4" />
          <p className="text-gray-500">User not found</p>
          <Link to="/active-directory/users">
            <Button className="mt-4">
              <ArrowLeft className="w-4 h-4 mr-2" />
              Back to Users
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
        <div className="flex items-center justify-between">
          <div className="flex items-center space-x-4">
            <Link to="/active-directory/users">
              <Button variant="ghost" size="sm">
                <ArrowLeft className="w-4 h-4 mr-2" />
                Back
              </Button>
            </Link>
            <div>
              <h1 className="text-3xl font-bold text-gray-900">{user.displayName}</h1>
              <p className="text-gray-500 mt-1">{user.userPrincipalName}</p>
            </div>
          </div>
          <div>
            {user.enabled ? (
              <Badge className="bg-green-500 text-lg px-4 py-2">
                <CheckCircle className="w-4 h-4 mr-2" />
                Enabled
              </Badge>
            ) : (
              <Badge className="bg-red-500 text-lg px-4 py-2">
                <XCircle className="w-4 h-4 mr-2" />
                Disabled
              </Badge>
            )}
          </div>
        </div>

        {/* Basic Information */}
        <Card>
          <CardHeader>
            <CardTitle>Basic Information</CardTitle>
          </CardHeader>
          <CardContent>
            <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
              <div className="flex items-start space-x-3">
                <User className="w-5 h-5 text-gray-400 mt-0.5" />
                <div>
                  <p className="text-sm font-medium text-gray-500">Display Name</p>
                  <p className="text-base text-gray-900">{user.displayName}</p>
                </div>
              </div>

              <div className="flex items-start space-x-3">
                <User className="w-5 h-5 text-gray-400 mt-0.5" />
                <div>
                  <p className="text-sm font-medium text-gray-500">Username (SAM)</p>
                  <p className="text-base text-gray-900">{user.samAccountName}</p>
                </div>
              </div>

              <div className="flex items-start space-x-3">
                <Mail className="w-5 h-5 text-gray-400 mt-0.5" />
                <div>
                  <p className="text-sm font-medium text-gray-500">Email</p>
                  <p className="text-base text-gray-900">{user.email || '-'}</p>
                </div>
              </div>

              <div className="flex items-start space-x-3">
                <Briefcase className="w-5 h-5 text-gray-400 mt-0.5" />
                <div>
                  <p className="text-sm font-medium text-gray-500">Department</p>
                  <p className="text-base text-gray-900">{user.department || '-'}</p>
                </div>
              </div>

              <div className="flex items-start space-x-3">
                <Briefcase className="w-5 h-5 text-gray-400 mt-0.5" />
                <div>
                  <p className="text-sm font-medium text-gray-500">Title</p>
                  <p className="text-base text-gray-900">{user.title || '-'}</p>
                </div>
              </div>

              <div className="flex items-start space-x-3">
                <User className="w-5 h-5 text-gray-400 mt-0.5" />
                <div>
                  <p className="text-sm font-medium text-gray-500">Manager</p>
                  <p className="text-base text-gray-900">{user.manager || '-'}</p>
                </div>
              </div>

              <div className="flex items-start space-x-3">
                <Building className="w-5 h-5 text-gray-400 mt-0.5" />
                <div>
                  <p className="text-sm font-medium text-gray-500">Company</p>
                  <p className="text-base text-gray-900">{user.company || '-'}</p>
                </div>
              </div>

              <div className="flex items-start space-x-3">
                <MapPin className="w-5 h-5 text-gray-400 mt-0.5" />
                <div>
                  <p className="text-sm font-medium text-gray-500">Office</p>
                  <p className="text-base text-gray-900">{user.office || '-'}</p>
                </div>
              </div>

              <div className="flex items-start space-x-3">
                <Phone className="w-5 h-5 text-gray-400 mt-0.5" />
                <div>
                  <p className="text-sm font-medium text-gray-500">Office Phone</p>
                  <p className="text-base text-gray-900">{user.officePhone || '-'}</p>
                </div>
              </div>

              <div className="flex items-start space-x-3">
                <Phone className="w-5 h-5 text-gray-400 mt-0.5" />
                <div>
                  <p className="text-sm font-medium text-gray-500">Mobile Phone</p>
                  <p className="text-base text-gray-900">{user.mobilePhone || '-'}</p>
                </div>
              </div>
            </div>

            {user.description && (
              <div className="mt-6 pt-6 border-t">
                <p className="text-sm font-medium text-gray-500 mb-2">Description</p>
                <p className="text-base text-gray-900">{user.description}</p>
              </div>
            )}
          </CardContent>
        </Card>

        {/* Account Status */}
        <Card>
          <CardHeader>
            <CardTitle>Account Status</CardTitle>
          </CardHeader>
          <CardContent>
            <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
              <div className="flex items-start space-x-3">
                <CheckCircle className={`w-5 h-5 mt-0.5 ${user.enabled ? 'text-green-500' : 'text-red-500'}`} />
                <div>
                  <p className="text-sm font-medium text-gray-500">Account Status</p>
                  <p className="text-base text-gray-900">{user.enabled ? 'Enabled' : 'Disabled'}</p>
                </div>
              </div>

              <div className="flex items-start space-x-3">
                <Key className={`w-5 h-5 mt-0.5 ${user.lockedOut ? 'text-red-500' : 'text-green-500'}`} />
                <div>
                  <p className="text-sm font-medium text-gray-500">Locked Out</p>
                  <p className="text-base text-gray-900">{user.lockedOut ? 'Yes' : 'No'}</p>
                </div>
              </div>

              <div className="flex items-start space-x-3">
                <Clock className="w-5 h-5 text-gray-400 mt-0.5" />
                <div>
                  <p className="text-sm font-medium text-gray-500">Last Logon</p>
                  <p className="text-base text-gray-900">{formatDate(user.lastLogon)}</p>
                </div>
              </div>

              <div className="flex items-start space-x-3">
                <Key className="w-5 h-5 text-gray-400 mt-0.5" />
                <div>
                  <p className="text-sm font-medium text-gray-500">Password Last Set</p>
                  <p className="text-base text-gray-900">{formatDate(user.passwordLastSet)}</p>
                </div>
              </div>

              <div className="flex items-start space-x-3">
                <Key className="w-5 h-5 text-gray-400 mt-0.5" />
                <div>
                  <p className="text-sm font-medium text-gray-500">Password Never Expires</p>
                  <p className="text-base text-gray-900">{user.passwordNeverExpires ? 'Yes' : 'No'}</p>
                </div>
              </div>

              <div className="flex items-start space-x-3">
                <Calendar className="w-5 h-5 text-gray-400 mt-0.5" />
                <div>
                  <p className="text-sm font-medium text-gray-500">Account Expiration</p>
                  <p className="text-base text-gray-900">{formatDate(user.accountExpiration)}</p>
                </div>
              </div>

              <div className="flex items-start space-x-3">
                <Calendar className="w-5 h-5 text-gray-400 mt-0.5" />
                <div>
                  <p className="text-sm font-medium text-gray-500">Account Created</p>
                  <p className="text-base text-gray-900">{formatDate(user.created)}</p>
                </div>
              </div>

              <div className="flex items-start space-x-3">
                <Calendar className="w-5 h-5 text-gray-400 mt-0.5" />
                <div>
                  <p className="text-sm font-medium text-gray-500">Last Modified</p>
                  <p className="text-base text-gray-900">{formatDate(user.modified)}</p>
                </div>
              </div>
            </div>
          </CardContent>
        </Card>

        {/* Group Memberships */}
        <Card>
          <CardHeader>
            <CardTitle className="flex items-center">
              <Shield className="w-5 h-5 mr-2" />
              Group Memberships ({user.groups?.length || 0})
            </CardTitle>
          </CardHeader>
          <CardContent>
            {user.groups && user.groups.length > 0 ? (
              <div className="grid grid-cols-1 md:grid-cols-2 gap-3">
                {user.groups.map((group, index) => (
                  <div key={index} className="flex items-center space-x-2 p-3 bg-gray-50 rounded-lg">
                    <Shield className="w-4 h-4 text-blue-500" />
                    <span className="text-sm text-gray-900">{group}</span>
                  </div>
                ))}
              </div>
            ) : (
              <p className="text-gray-500 text-center py-4">No group memberships</p>
            )}
          </CardContent>
        </Card>

        {/* M365 Licenses */}
        {user.licenses && user.licenses.length > 0 && (
          <Card>
            <CardHeader>
              <CardTitle className="flex items-center">
                <Key className="w-5 h-5 mr-2" />
                Microsoft 365 Licenses ({user.licenses.length})
              </CardTitle>
            </CardHeader>
            <CardContent>
              <div className="space-y-3">
                {user.licenses.map((license, index) => (
                  <div key={index} className="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                    <div className="flex items-center space-x-3">
                      <Key className="w-4 h-4 text-green-500" />
                      <span className="text-sm font-medium text-gray-900">{license.skuPartNumber}</span>
                    </div>
                    <span className="text-xs text-gray-500">
                      Assigned: {formatDate(license.assignedDate)}
                    </span>
                  </div>
                ))}
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
            <div className="space-y-4">
              <div>
                <p className="text-sm font-medium text-gray-500 mb-1">Distinguished Name</p>
                <p className="text-sm text-gray-900 font-mono bg-gray-50 p-3 rounded break-all">
                  {user.distinguishedName}
                </p>
              </div>
              <div>
                <p className="text-sm font-medium text-gray-500 mb-1">User Principal Name</p>
                <p className="text-sm text-gray-900 font-mono bg-gray-50 p-3 rounded">
                  {user.userPrincipalName}
                </p>
              </div>
            </div>
          </CardContent>
        </Card>
      </div>
    </FloatingLayout>
  )
}
