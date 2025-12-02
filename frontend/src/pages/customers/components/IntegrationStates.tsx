import { Spinner } from '@/components/ui/spinner'
import { Card, CardContent } from '@/components/ui/card'
import { XCircle, Cable } from 'lucide-react'

type LoadingStateProps = {
    isLoading: boolean
}

export function LoadingState({ isLoading }: LoadingStateProps) {
    if (!isLoading) return null

    return (
        <div className="flex items-center justify-center py-12">
            <Spinner className="h-8 w-8" />
        </div>
    )
}

type ErrorStateProps = {
    error: string | null
    customerId?: string
}

export function ErrorState({ error, customerId }: ErrorStateProps) {
    if (!error) return null

    return (
        <Card className="border-destructive">
            <CardContent className="pt-6">
                <div className="space-y-2">
                    <div className="flex items-center gap-2 text-destructive">
                        <XCircle className="h-5 w-5" />
                        <p className="font-medium">{error}</p>
                    </div>
                    <p className="text-sm text-muted-foreground">
                        Customer ID: {customerId} | Endpoint: /api/customers/{customerId}/integrations
                    </p>
                    <p className="text-xs text-muted-foreground">
                        Check the browser console for more details.
                    </p>
                </div>
            </CardContent>
        </Card>
    )
}

type EmptyStateProps = {
    show: boolean
}

export function EmptyState({ show }: EmptyStateProps) {
    if (!show) return null

    return (
        <Card>
            <CardContent className="pt-6">
                <div className="text-center py-8 text-muted-foreground">
                    <Cable className="h-12 w-12 mx-auto mb-2 opacity-50" />
                    <p>No integrations found for this customer</p>
                </div>
            </CardContent>
        </Card>
    )
}
