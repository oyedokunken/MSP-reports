import { Skeleton } from '@/components/ui/skeleton'
import { Card, CardContent, CardHeader } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { ArrowLeft } from 'lucide-react'

export function ConfigureReportsLoading() {
    return (
        <div className="space-y-6">
            <div className="flex items-center justify-between">
                <div className="flex items-center space-x-4">
                    <Skeleton className="h-9 w-20" />
                    <div>
                        <Skeleton className="h-8 w-64" />
                        <Skeleton className="h-4 w-48 mt-2" />
                    </div>
                </div>
            </div>
            <Card>
                <CardHeader>
                    <Skeleton className="h-6 w-48" />
                    <Skeleton className="h-4 w-full mt-2" />
                </CardHeader>
                <CardContent className="space-y-6">
                    {[1, 2, 3].map((i) => (
                        <div key={i} className="space-y-4 p-4 border rounded-lg">
                            <div className="flex items-center justify-between">
                                <Skeleton className="h-5 w-40" />
                                <Skeleton className="h-6 w-12" />
                            </div>
                            <div className="grid grid-cols-2 gap-4">
                                <Skeleton className="h-10 w-full" />
                                <Skeleton className="h-10 w-full" />
                            </div>
                        </div>
                    ))}
                </CardContent>
            </Card>
        </div>
    )
}

export function ConfigureReportsNotFound({ onBack }: { onBack: () => void }) {
    return (
        <div className="flex flex-col items-center justify-center h-64">
            <p className="text-lg font-medium text-muted-foreground">Customer not found</p>
            <Button onClick={onBack} className="mt-4">
                <ArrowLeft className="h-4 w-4 mr-2" />
                Go Back
            </Button>
        </div>
    )
}
