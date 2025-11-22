import { useEffect, useState } from 'react'
import { Link } from 'react-router-dom'
import FloatingLayout from '@/components/layout/FloatingLayout'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Badge } from '@/components/ui/badge'
import { Search, Download, RefreshCw, Shield, Users } from 'lucide-react'
import { useToast } from '@/components/ui/Toast'
import { GroupsListSkeleton } from './components/Skeletons'

interface ADGroup {
  id: number
  name: string
  samAccountName: string
  groupCategory: string
  groupScope: string
  description: string
  distinguishedName: string
  memberCount: number
  created: string
  modified: string
}

export default function GroupsList() {
  const toast = useToast()
  const [groups, setGroups] = useState<ADGroup[]>([])
  const [isLoading, setIsLoading] = useState(true)
  const [searchQuery, setSearchQuery] = useState('')
  const [categoryFilter, setCategoryFilter] = useState<string>('all')
  const [scopeFilter, setScopeFilter] = useState<string>('all')

  useEffect(() => {
    // TODO: Implement API endpoint
    // fetchGroups()
    setIsLoading(false)
    // Empty state for now
    setGroups([])
  }, [])

  const filteredGroups = groups.filter(group => {
    const matchesSearch = searchQuery === '' || 
      group.name?.toLowerCase().includes(searchQuery.toLowerCase()) ||
      group.samAccountName?.toLowerCase().includes(searchQuery.toLowerCase()) ||
      group.description?.toLowerCase().includes(searchQuery.toLowerCase())

    const matchesCategory = categoryFilter === 'all' || group.groupCategory === categoryFilter
    const matchesScope = scopeFilter === 'all' || group.groupScope === scopeFilter

    return matchesSearch && matchesCategory && matchesScope
  })

  const formatDate = (date: string) => {
    return new Date(date).toLocaleDateString('en-AU', {
      year: 'numeric',
      month: 'short',
      day: 'numeric'
    })
  }

  const handleExport = () => {
    const csv = [
      ['Group Name', 'SAM Account Name', 'Category', 'Scope', 'Members', 'Description', 'Created'].join(','),
      ...filteredGroups.map(group => [
        group.name,
        group.samAccountName,
        group.groupCategory,
        group.groupScope,
        group.memberCount,
        group.description || '',
        formatDate(group.created)
      ].join(','))
    ].join('\n')

    const blob = new Blob([csv], { type: 'text/csv' })
    const url = window.URL.createObjectURL(blob)
    const a = document.createElement('a')
    a.href = url
    a.download = `ad-groups-${new Date().toISOString().split('T')[0]}.csv`
    a.click()
    toast.success('Exported', 'Groups list exported successfully')
  }

  const getCategoryBadge = (category: string) => {
    return category === 'Security' ? (
      <Badge className="bg-blue-500">Security</Badge>
    ) : (
      <Badge className="bg-gray-500">Distribution</Badge>
    )
  }

  const getScopeBadge = (scope: string) => {
    const colors: Record<string, string> = {
      'Global': 'bg-green-500',
      'Universal': 'bg-purple-500',
      'DomainLocal': 'bg-orange-500'
    }
    return <Badge className={colors[scope] || 'bg-gray-500'}>{scope}</Badge>
  }

  if (isLoading) {
    return (
      <FloatingLayout>
        <GroupsListSkeleton />
      </FloatingLayout>
    )
  }

  return (
    <FloatingLayout>
      <div className="space-y-6">
        {/* Header */}
        <div className="flex items-center justify-between">
          <div>
            <h1 className="text-3xl font-bold text-gray-900">Security Groups</h1>
            <p className="text-gray-500 mt-1">
              {filteredGroups.length} of {groups.length} groups
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

        {/* Filters */}
        <Card>
          <CardContent className="pt-6">
            <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
              {/* Search */}
              <div>
                <div className="relative">
                  <Search className="absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400 w-4 h-4" />
                  <Input
                    placeholder="Search groups..."
                    value={searchQuery}
                    onChange={(e) => setSearchQuery(e.target.value)}
                    className="pl-10"
                  />
                </div>
              </div>

              {/* Category Filter */}
              <div>
                <select
                  value={categoryFilter}
                  onChange={(e) => setCategoryFilter(e.target.value)}
                  className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                >
                  <option value="all">All Categories</option>
                  <option value="Security">Security</option>
                  <option value="Distribution">Distribution</option>
                </select>
              </div>

              {/* Scope Filter */}
              <div>
                <select
                  value={scopeFilter}
                  onChange={(e) => setScopeFilter(e.target.value)}
                  className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                >
                  <option value="all">All Scopes</option>
                  <option value="Global">Global</option>
                  <option value="Universal">Universal</option>
                  <option value="DomainLocal">Domain Local</option>
                </select>
              </div>
            </div>
          </CardContent>
        </Card>

        {/* Groups Table */}
        <Card>
          <CardHeader>
            <CardTitle>Groups</CardTitle>
          </CardHeader>
          <CardContent>
            {filteredGroups.length === 0 ? (
              <div className="text-center py-12">
                <Shield className="w-12 h-12 text-gray-300 mx-auto mb-4" />
                <p className="text-gray-500">No groups found</p>
              </div>
            ) : (
              <div className="overflow-x-auto">
                <table className="w-full">
                  <thead className="bg-gray-50 border-b">
                    <tr>
                      <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Group</th>
                      <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Category</th>
                      <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Scope</th>
                      <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Members</th>
                      <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Description</th>
                      <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Modified</th>
                      <th className="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Actions</th>
                    </tr>
                  </thead>
                  <tbody className="divide-y divide-gray-200">
                    {filteredGroups.map((group) => (
                      <tr key={group.id} className="hover:bg-gray-50">
                        <td className="px-4 py-4">
                          <div className="flex items-center">
                            <div className="flex-shrink-0 h-10 w-10 bg-blue-100 rounded-full flex items-center justify-center">
                              <Shield className="w-5 h-5 text-blue-600" />
                            </div>
                            <div className="ml-4">
                              <div className="text-sm font-medium text-gray-900">{group.name}</div>
                              <div className="text-sm text-gray-500">{group.samAccountName}</div>
                            </div>
                          </div>
                        </td>
                        <td className="px-4 py-4">
                          {getCategoryBadge(group.groupCategory)}
                        </td>
                        <td className="px-4 py-4">
                          {getScopeBadge(group.groupScope)}
                        </td>
                        <td className="px-4 py-4">
                          <div className="flex items-center text-sm text-gray-900">
                            <Users className="w-4 h-4 mr-2 text-gray-400" />
                            {group.memberCount}
                          </div>
                        </td>
                        <td className="px-4 py-4 text-sm text-gray-500 max-w-xs truncate">
                          {group.description || '-'}
                        </td>
                        <td className="px-4 py-4 text-sm text-gray-500">
                          {formatDate(group.modified)}
                        </td>
                        <td className="px-4 py-4 text-right">
                          <Link to={`/active-directory/groups/${group.id}`}>
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
