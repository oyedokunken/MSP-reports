import { CardHeader, CardDescription, CardTitle } from '@/components/ui/card'
import { Badge } from '@/components/ui/badge'
import { Cable, CheckCircle2, XCircle } from 'lucide-react'
import type { CustomerIntegrationDetail } from '../types'

type IntegrationCardHeaderProps = {
    integration: CustomerIntegrationDetail
}

export function IntegrationCardHeader({ integration }: IntegrationCardHeaderProps) {
    return (
        <CardHeader className="bg-gradient-to-r from-primary/5 to-primary/10 border-b">
            <div className="flex items-start justify-between">
                <div className="flex-1">
                    <div className="flex items-center gap-2 mb-2">
                        <Cable className="h-5 w-5 text-primary" />
                        <CardTitle>{integration.display_name}</CardTitle>
                        <Badge variant="outline" className="capitalize">
                            {integration.integration_type}
                        </Badge>
                        <Badge variant={integration.active ? 'default' : 'secondary'}>
                            {integration.active ? (
                                <>
                                    <CheckCircle2 className="h-3 w-3 mr-1" />
                                    Active
                                </>
                            ) : (
                                <>
                                    <XCircle className="h-3 w-3 mr-1" />
                                    Inactive
                                </>
                            )}
                        </Badge>
                    </div>
                    <CardDescription>
                        External ID: {integration.external_id} • Last Seen:{' '}
                        {new Date(integration.last_seen_at).toLocaleString()}
                    </CardDescription>
                </div>
            </div>

            <div className="mt-4 p-3 bg-background/50 rounded-lg">
                <div className="flex items-center gap-4 text-sm">
                    <div className="flex items-center gap-2">
                        <span className="text-muted-foreground">Mapping Type:</span>
                        <Badge variant={integration.mapping.type === 'auto' ? 'default' : 'secondary'}>
                            {integration.mapping.type}
                        </Badge>
                    </div>
                    <div className="flex items-center gap-2">
                        <span className="text-muted-foreground">Confidence:</span>
                        <span className="font-medium">{integration.mapping.confidence_score}%</span>
                    </div>
                    <div className="flex items-center gap-2">
                        <span className="text-muted-foreground">Mapped:</span>
                        <span className="font-medium">
                            {new Date(integration.mapping.mapped_at).toLocaleDateString()}
                        </span>
                    </div>
                </div>
                {integration.mapping.notes && (
                    <p className="mt-2 text-sm text-muted-foreground">{integration.mapping.notes}</p>
                )}
            </div>
        </CardHeader>
    )
}
