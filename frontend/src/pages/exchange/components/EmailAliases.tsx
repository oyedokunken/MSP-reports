import { useEffect, useState } from 'react'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Badge } from '@/components/ui/badge'
import { Skeleton } from '@/components/ui/skeleton'
import { Mail, Star } from 'lucide-react'

interface EmailAlias {
  id: number
  emailAddress: string
  addressType: string
  isPrimary: boolean
}

interface EmailAliasesProps {
  mailboxId: number
}

export function EmailAliases({ mailboxId }: EmailAliasesProps) {
  const [aliases, setAliases] = useState<EmailAlias[]>([])
  const [isLoading, setIsLoading] = useState(true)

  useEffect(() => {
    // TODO: Implement API endpoint
    // fetchAliases()
    setIsLoading(false)
    setAliases([])
  }, [mailboxId])

  if (isLoading) {
    return (
      <Card>
        <CardHeader>
          <CardTitle className="flex items-center">
            <Mail className="w-5 h-5 mr-2" />
            Email Aliases
          </CardTitle>
        </CardHeader>
        <CardContent>
          <div className="space-y-3">
            {[1, 2, 3].map((i) => (
              <div key={i} className="flex items-center justify-between p-3 border rounded">
                <Skeleton className="h-4 w-64" />
                <Skeleton className="h-6 w-16" />
              </div>
            ))}
          </div>
        </CardContent>
      </Card>
    )
  }

  return (
    <Card>
      <CardHeader>
        <CardTitle className="flex items-center">
          <Mail className="w-5 h-5 mr-2" />
          Email Aliases ({aliases.length})
        </CardTitle>
      </CardHeader>
      <CardContent>
        {aliases.length === 0 ? (
          <div className="text-center py-8">
            <Mail className="w-12 h-12 text-muted-foreground mx-auto mb-4" />
            <p className="text-muted-foreground">No email aliases found</p>
          </div>
        ) : (
          <div className="space-y-3">
            {aliases.map((alias) => (
              <div
                key={alias.id}
                className="flex items-center justify-between p-3 bg-muted/50 rounded-lg hover:bg-muted transition-colors"
              >
                <div className="flex items-center space-x-3">
                  <Mail className="w-4 h-4 text-muted-foreground" />
                  <div>
                    <p className="text-sm font-medium text-foreground">{alias.emailAddress}</p>
                    <p className="text-xs text-muted-foreground">{alias.addressType}</p>
                  </div>
                </div>
                {alias.isPrimary && (
                  <Badge className="bg-blue-500">
                    <Star className="w-3 h-3 mr-1" />
                    Primary
                  </Badge>
                )}
              </div>
            ))}
          </div>
        )}
      </CardContent>
    </Card>
  )
}
