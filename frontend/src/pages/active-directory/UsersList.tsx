import { useEffect, useState } from 'react'
import { Link } from 'react-router-dom'
import FloatingLayout from '@/components/layout/FloatingLayout'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Badge } from '@/components/ui/badge'
import { 
  Search, Download, RefreshCw, CheckCircle, XCircle, 
  User, Mail, Briefcase, Calendar 
} from 'lucide-react'
import { useToast } from '@/components/ui/Toast'
import { UsersListSkeleton } from './components/Skeletons'

interface ADUser {
  id: number
  userPrincipalName: string
  samAccountName: string
  displayName: string
  givenName: string
  surname: string
  email: string
  department: string
  title: string
  enabled: boolean
  lastLogon: string | null
  passwordLastSet: string | null
  created: string
}

export default function UsersList() {
  const toast = useToast()
  const [users, setUsers] = useState<ADUser[]>([])
  const [isLoading, setIsLoading] = useState(true)
  const [searchQuery, setSearchQuery] = useState('')
  const [statusFilter, setStatusFilter] = useState<'all' | 'enabled' | 'disabled'>('all')
  const [departmentFilter, setDepartmentFilter] = useState<string>('all')
  const [departments, setDepartments] = useState<string[]>([])

  useEffect(() => {
    // TODO: Implement API endpoint
    // fetchUsers()
    setIsLoading(false)
    // Empty state for now
    setUsers([])
    setDepartments([])
  }, [])

  const filteredUsers = users.filter(user => {
    // Search filter
    const matchesSearch = searchQuery === '' || 
      user.displayName?.toLowerCase().includes(searchQuery.toLowerCase()) ||
      user.email?.toLowerCase().includes(searchQuery.toLowerCase()) ||
      user.samAccountName?.toLowerCase().includes(searchQuery.toLowerCase()) ||
      user.department?.toLowerCase().includes(searchQuery.toLowerCase())

    // Status filter
    const matchesStatus = statusFilter === 'all' || 
      (statusFilter === 'enabled' && user.enabled) ||
      (statusFilter === 'disabled' && !user.enabled)

    // Department filter
    const matchesDepartment = departmentFilter === 'all' || user.department === departmentFilter

    return matchesSearch && matchesStatus && matchesDepartment
  })

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
      ['Display Name', 'Email', 'Username', 'Department', 'Title', 'Status', 'Last Logon'].join(','),
      ...filteredUsers.map(user => [
        user.displayName,
        user.email,
        user.samAccountName,
        user.department || '',
        user.title || '',
        user.enabled ? 'Enabled' : 'Disabled',
        formatDate(user.lastLogon)
      ].join(','))
    ].join('\n')

    const blob = new Blob([csv], { type: 'text/csv' })
    const url = window.URL.createObjectURL(blob)
    const a = document.createElement('a')
    a.href = url
    a.download = `ad-users-${new Date().toISOString().split('T')[0]}.csv`
    a.click()
    toast.success('Exported', 'Users list exported successfully')
  }

  if (isLoading) {
    return (
      <FloatingLayout>
        <UsersListSkeleton />
      </FloatingLayout>
    )
  }

  return (
    <FloatingLayout>
      <div className="space-y-6">
        {/* Header */}
        <div className="flex items-center justify-between">
          <div>
            <h1 className="text-3xl font-bold text-gray-900">Active Directory Users</h1>
            <p className="text-gray-500 mt-1">
              {filteredUsers.length} of {users.length} users
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
            <div className="grid grid-cols-1 md:grid-cols-4 gap-4">
              {/* Search */}
              <div className="md:col-span-2">
                <div className="relative">
                  <Search className="absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400 w-4 h-4" />
                  <Input
                    placeholder="Search by name, email, username, or department..."
                    value={searchQuery}
                    onChange={(e) => setSearchQuery(e.target.value)}
                    className="pl-10"
                  />
                </div>
              </div>

              {/* Status Filter */}
              <div>
                <select
                  value={statusFilter}
                  onChange={(e) => setStatusFilter(e.target.value as any)}
                  className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                >
                  <option value="all">All Status</option>
                  <option value="enabled">Enabled Only</option>
                  <option value="disabled">Disabled Only</option>
                </select>
              </div>

              {/* Department Filter */}
              <div>
                <select
                  value={departmentFilter}
                  onChange={(e) => setDepartmentFilter(e.target.value)}
                  className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                >
                  <option value="all">All Departments</option>
                  {departments.map(dept => (
                    <option key={dept} value={dept}>{dept}</option>
                  ))}
                </select>
              </div>
            </div>
          </CardContent>
        </Card>

        {/* Users Table */}
        <Card>
          <CardHeader>
            <CardTitle>Users</CardTitle>
          </CardHeader>
          <CardContent>
            {filteredUsers.length === 0 ? (
              <div className="text-center py-12">
                <User className="w-12 h-12 text-gray-300 mx-auto mb-4" />
                <p className="text-gray-500">No users found</p>
              </div>
            ) : (
              <div className="overflow-x-auto">
                <table className="w-full">
                  <thead className="bg-gray-50 border-b">
                    <tr>
                      <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">User</th>
                      <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Email</th>
                      <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Department</th>
                      <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Title</th>
                      <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                      <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Last Logon</th>
                      <th className="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Actions</th>
                    </tr>
                  </thead>
                  <tbody className="divide-y divide-gray-200">
                    {filteredUsers.map((user) => (
                      <tr key={user.id} className="hover:bg-gray-50">
                        <td className="px-4 py-4">
                          <div className="flex items-center">
                            <div className="flex-shrink-0 h-10 w-10 bg-blue-100 rounded-full flex items-center justify-center">
                              <User className="w-5 h-5 text-blue-600" />
                            </div>
                            <div className="ml-4">
                              <div className="text-sm font-medium text-gray-900">{user.displayName}</div>
                              <div className="text-sm text-gray-500">{user.samAccountName}</div>
                            </div>
                          </div>
                        </td>
                        <td className="px-4 py-4">
                          <div className="flex items-center text-sm text-gray-900">
                            <Mail className="w-4 h-4 mr-2 text-gray-400" />
                            {user.email || '-'}
                          </div>
                        </td>
                        <td className="px-4 py-4">
                          <div className="flex items-center text-sm text-gray-900">
                            <Briefcase className="w-4 h-4 mr-2 text-gray-400" />
                            {user.department || '-'}
                          </div>
                        </td>
                        <td className="px-4 py-4 text-sm text-gray-900">
                          {user.title || '-'}
                        </td>
                        <td className="px-4 py-4">
                          {user.enabled ? (
                            <Badge className="bg-green-500">
                              <CheckCircle className="w-3 h-3 mr-1" />
                              Enabled
                            </Badge>
                          ) : (
                            <Badge className="bg-red-500">
                              <XCircle className="w-3 h-3 mr-1" />
                              Disabled
                            </Badge>
                          )}
                        </td>
                        <td className="px-4 py-4">
                          <div className="flex items-center text-sm text-gray-500">
                            <Calendar className="w-4 h-4 mr-2 text-gray-400" />
                            {formatDate(user.lastLogon)}
                          </div>
                        </td>
                        <td className="px-4 py-4 text-right">
                          <Link to={`/active-directory/users/${user.id}`}>
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
