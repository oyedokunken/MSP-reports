import { useEffect, useState } from 'react'
import { useParams, Link } from 'react-router-dom'
import FloatingLayout from '@/components/layout/FloatingLayout'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Badge } from '@/components/ui/badge'
import { Input } from '@/components/ui/input'
import { 
  ArrowLeft, Shield, Users, Calendar, Search, User, Mail
} from 'lucide-react'
// import { api } from '@/lib/api' // TODO: Uncomment when API endpoints are implemented
// import { useToast } from '@/components/ui/Toast' // TODO: Uncomment when API endpoints are implemented
import { GroupDetailSkeleton } from './components/Skeletons'

interface ADGroupDetail {
  id: number
  name: string
  samAccountName: string
  groupCategory: string
  groupScope: string
  description: string
  distinguishedName: string
  created: string
  modified: string
  memberCount: number
  members: Array<{
    displayName: string
    samAccountName: string
    email: string
    membershipType: string
  }>
}

export default function GroupDetail() {
  const { id } = useParams<{ id: string }>()
  // const toast = useToast() // TODO: Uncomment when API endpoints are implemented
  const [group, setGroup] = useState<ADGroupDetail | null>(null)
  const [isLoading, setIsLoading] = useState(true)
  const [searchQuery, setSearchQuery] = useState('')

  useEffect(() => {
    // TODO: Implement API endpoint
    // if (id) {
    //   fetchGroup()
    // }
    setIsLoading(false)
    setGroup(null)
  }, [id])

  const formatDate = (date: string) => {
    return new Date(date).toLocaleString('en-AU', {
      year: 'numeric',
      month: 'short',
      day: 'numeric',
      hour: '2-digit',
      minute: '2-digit'
    })
  }

  const getCategoryBadge = (category: string) => {
    return category === 'Security' ? (
      <Badge className="bg-blue-500 text-lg px-4 py-2">Security</Badge>
    ) : (
      <Badge className="bg-gray-500 text-lg px-4 py-2">Distribution</Badge>
    )
  }

  const getScopeBadge = (scope: string) => {
    const colors: Record<string, string> = {
      'Global': 'bg-green-500',
      'Universal': 'bg-purple-500',
      'DomainLocal': 'bg-orange-500'
    }
    return <Badge className={`${colors[scope] || 'bg-gray-500'} text-lg px-4 py-2`}>{scope}</Badge>
  }

  const filteredMembers = group?.members?.filter(member => 
    searchQuery === '' ||
    member.displayName?.toLowerCase().includes(searchQuery.toLowerCase()) ||
    member.samAccountName?.toLowerCase().includes(searchQuery.toLowerCase()) ||
    member.email?.toLowerCase().includes(searchQuery.toLowerCase())
  ) || []

  if (isLoading) {
    return (
      <FloatingLayout>
        <GroupDetailSkeleton />
      </FloatingLayout>
    )
  }

  if (!group) {
    return (
      <FloatingLayout>
        <div className="text-center py-12">
          <Shield className="w-12 h-12 text-gray-300 mx-auto mb-4" />
          <p className="text-gray-500">Group not found</p>
          <Link to="/active-directory/groups">
            <Button className="mt-4">
              <ArrowLeft className="w-4 h-4 mr-2" />
              Back to Groups
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
            <Link to="/active-directory/groups">
              <Button variant="ghost" size="sm">
                <ArrowLeft className="w-4 h-4 mr-2" />
                Back
              </Button>
            </Link>
            <div>
              <h1 className="text-3xl font-bold text-gray-900">{group.name}</h1>
              <p className="text-gray-500 mt-1">{group.samAccountName}</p>
            </div>
          </div>
          <div className="flex space-x-2">
            {getCategoryBadge(group.groupCategory)}
            {getScopeBadge(group.groupScope)}
          </div>
        </div>

        {/* Basic Information */}
        <Card>
          <CardHeader>
            <CardTitle>Group Information</CardTitle>
          </CardHeader>
          <CardContent>
            <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
              <div className="flex items-start space-x-3">
                <Shield className="w-5 h-5 text-gray-400 mt-0.5" />
                <div>
                  <p className="text-sm font-medium text-gray-500">Group Name</p>
                  <p className="text-base text-gray-900">{group.name}</p>
                </div>
              </div>

              <div className="flex items-start space-x-3">
                <Shield className="w-5 h-5 text-gray-400 mt-0.5" />
                <div>
                  <p className="text-sm font-medium text-gray-500">SAM Account Name</p>
                  <p className="text-base text-gray-900">{group.samAccountName}</p>
                </div>
              </div>

              <div className="flex items-start space-x-3">
                <Shield className="w-5 h-5 text-gray-400 mt-0.5" />
                <div>
                  <p className="text-sm font-medium text-gray-500">Group Category</p>
                  <p className="text-base text-gray-900">{group.groupCategory}</p>
                </div>
              </div>

              <div className="flex items-start space-x-3">
                <Shield className="w-5 h-5 text-gray-400 mt-0.5" />
                <div>
                  <p className="text-sm font-medium text-gray-500">Group Scope</p>
                  <p className="text-base text-gray-900">{group.groupScope}</p>
                </div>
              </div>

              <div className="flex items-start space-x-3">
                <Users className="w-5 h-5 text-gray-400 mt-0.5" />
                <div>
                  <p className="text-sm font-medium text-gray-500">Member Count</p>
                  <p className="text-base text-gray-900">{group.memberCount}</p>
                </div>
              </div>

              <div className="flex items-start space-x-3">
                <Calendar className="w-5 h-5 text-gray-400 mt-0.5" />
                <div>
                  <p className="text-sm font-medium text-gray-500">Created</p>
                  <p className="text-base text-gray-900">{formatDate(group.created)}</p>
                </div>
              </div>

              <div className="flex items-start space-x-3">
                <Calendar className="w-5 h-5 text-gray-400 mt-0.5" />
                <div>
                  <p className="text-sm font-medium text-gray-500">Last Modified</p>
                  <p className="text-base text-gray-900">{formatDate(group.modified)}</p>
                </div>
              </div>
            </div>

            {group.description && (
              <div className="mt-6 pt-6 border-t">
                <p className="text-sm font-medium text-gray-500 mb-2">Description</p>
                <p className="text-base text-gray-900">{group.description}</p>
              </div>
            )}
          </CardContent>
        </Card>

        {/* Members */}
        <Card>
          <CardHeader>
            <div className="flex items-center justify-between">
              <CardTitle className="flex items-center">
                <Users className="w-5 h-5 mr-2" />
                Members ({filteredMembers.length})
              </CardTitle>
              <div className="w-64">
                <div className="relative">
                  <Search className="absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400 w-4 h-4" />
                  <Input
                    placeholder="Search members..."
                    value={searchQuery}
                    onChange={(e) => setSearchQuery(e.target.value)}
                    className="pl-10"
                  />
                </div>
              </div>
            </div>
          </CardHeader>
          <CardContent>
            {filteredMembers.length === 0 ? (
              <div className="text-center py-12">
                <Users className="w-12 h-12 text-gray-300 mx-auto mb-4" />
                <p className="text-gray-500">
                  {searchQuery ? 'No members found matching your search' : 'No members in this group'}
                </p>
              </div>
            ) : (
              <div className="overflow-x-auto">
                <table className="w-full">
                  <thead className="bg-gray-50 border-b">
                    <tr>
                      <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Member</th>
                      <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Email</th>
                      <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Username</th>
                      <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Type</th>
                    </tr>
                  </thead>
                  <tbody className="divide-y divide-gray-200">
                    {filteredMembers.map((member, index) => (
                      <tr key={index} className="hover:bg-gray-50">
                        <td className="px-4 py-4">
                          <div className="flex items-center">
                            <div className="flex-shrink-0 h-10 w-10 bg-blue-100 rounded-full flex items-center justify-center">
                              <User className="w-5 h-5 text-blue-600" />
                            </div>
                            <div className="ml-4">
                              <div className="text-sm font-medium text-gray-900">{member.displayName}</div>
                            </div>
                          </div>
                        </td>
                        <td className="px-4 py-4">
                          <div className="flex items-center text-sm text-gray-900">
                            <Mail className="w-4 h-4 mr-2 text-gray-400" />
                            {member.email || '-'}
                          </div>
                        </td>
                        <td className="px-4 py-4 text-sm text-gray-900">
                          {member.samAccountName}
                        </td>
                        <td className="px-4 py-4">
                          <Badge variant="outline">{member.membershipType}</Badge>
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            )}
          </CardContent>
        </Card>

        {/* Technical Details */}
        <Card>
          <CardHeader>
            <CardTitle>Technical Details</CardTitle>
          </CardHeader>
          <CardContent>
            <div>
              <p className="text-sm font-medium text-gray-500 mb-1">Distinguished Name</p>
              <p className="text-sm text-gray-900 font-mono bg-gray-50 p-3 rounded break-all">
                {group.distinguishedName}
              </p>
            </div>
          </CardContent>
        </Card>
      </div>
    </FloatingLayout>
  )
}
