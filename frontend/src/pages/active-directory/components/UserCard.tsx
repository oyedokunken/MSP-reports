import { Link } from 'react-router-dom'
import { Card, CardContent } from '@/components/ui/card'
import { Badge } from '@/components/ui/badge'
import { User, Mail, Briefcase, CheckCircle, XCircle } from 'lucide-react'

interface UserCardProps {
  user: {
    id: number
    displayName: string
    email: string
    department: string
    title: string
    enabled: boolean
    samAccountName: string
  }
}

export function UserCard({ user }: UserCardProps) {
  return (
    <Link to={`/active-directory/users/${user.id}`}>
      <Card className="hover:shadow-lg transition-shadow cursor-pointer">
        <CardContent className="pt-6">
          <div className="flex items-start justify-between mb-4">
            <div className="flex items-center space-x-3">
              <div className="flex-shrink-0 h-12 w-12 bg-blue-100 rounded-full flex items-center justify-center">
                <User className="w-6 h-6 text-blue-600" />
              </div>
              <div>
                <h3 className="text-lg font-semibold text-gray-900">{user.displayName}</h3>
                <p className="text-sm text-gray-500">{user.samAccountName}</p>
              </div>
            </div>
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
          </div>

          <div className="space-y-2">
            {user.email && (
              <div className="flex items-center text-sm text-gray-600">
                <Mail className="w-4 h-4 mr-2 text-gray-400" />
                <span className="truncate">{user.email}</span>
              </div>
            )}
            
            {user.department && (
              <div className="flex items-center text-sm text-gray-600">
                <Briefcase className="w-4 h-4 mr-2 text-gray-400" />
                <span>{user.department}</span>
              </div>
            )}
            
            {user.title && (
              <div className="flex items-center text-sm text-gray-600">
                <Briefcase className="w-4 h-4 mr-2 text-gray-400" />
                <span className="truncate">{user.title}</span>
              </div>
            )}
          </div>
        </CardContent>
      </Card>
    </Link>
  )
}
