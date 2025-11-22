import { Link } from 'react-router-dom'
import { Card, CardContent } from '@/components/ui/card'
import { Badge } from '@/components/ui/badge'
import { Shield, Users } from 'lucide-react'

interface GroupCardProps {
  group: {
    id: number
    name: string
    samAccountName: string
    groupCategory: string
    groupScope: string
    description: string
    memberCount: number
  }
}

export function GroupCard({ group }: GroupCardProps) {
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

  return (
    <Link to={`/active-directory/groups/${group.id}`}>
      <Card className="hover:shadow-lg transition-shadow cursor-pointer">
        <CardContent className="pt-6">
          <div className="flex items-start justify-between mb-4">
            <div className="flex items-center space-x-3">
              <div className="flex-shrink-0 h-12 w-12 bg-blue-100 rounded-full flex items-center justify-center">
                <Shield className="w-6 h-6 text-blue-600" />
              </div>
              <div>
                <h3 className="text-lg font-semibold text-gray-900">{group.name}</h3>
                <p className="text-sm text-gray-500">{group.samAccountName}</p>
              </div>
            </div>
          </div>

          <div className="space-y-3">
            <div className="flex items-center space-x-2">
              {getCategoryBadge(group.groupCategory)}
              {getScopeBadge(group.groupScope)}
            </div>

            {group.description && (
              <p className="text-sm text-gray-600 line-clamp-2">{group.description}</p>
            )}

            <div className="flex items-center text-sm text-gray-600 pt-2 border-t">
              <Users className="w-4 h-4 mr-2 text-gray-400" />
              <span>{group.memberCount} {group.memberCount === 1 ? 'member' : 'members'}</span>
            </div>
          </div>
        </CardContent>
      </Card>
    </Link>
  )
}
