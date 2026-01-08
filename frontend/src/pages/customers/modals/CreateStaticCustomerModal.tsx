import { useState } from 'react'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { Spinner } from '@/components/ui/spinner'
import { CheckCircle2, UserPlus } from 'lucide-react'
import { apiClient } from '@/lib/apiClient'

interface CreateStaticCustomerModalProps {
    open: boolean
    onOpenChange: (open: boolean) => void
    onSuccess?: () => void
}

export function CreateStaticCustomerModal({
    open,
    onOpenChange,
    onSuccess
}: CreateStaticCustomerModalProps) {
    const [isSubmitting, setIsSubmitting] = useState(false)
    const [error, setError] = useState<string | null>(null)
    const [success, setSuccess] = useState(false)
    const [customerName, setCustomerName] = useState('')
    const [reportDestination, setReportDestination] = useState('')

    const handleSubmit = async (e: React.FormEvent) => {
        e.preventDefault()

        if (!customerName.trim()) {
            setError('Customer name is required')
            return
        }

        setIsSubmitting(true)
        setError(null)

        try {
            const payload: any = {
                canonical_name: customerName.trim(),
                status: 'active',
            }

            if (reportDestination.trim()) {
                payload.report_destination = reportDestination.trim()
            }

            await apiClient.post('/customers/static', payload)

            setSuccess(true)

            setTimeout(() => {
                setSuccess(false)
                setCustomerName('')
                setReportDestination('')
                onSuccess?.()
            }, 1500)
        } catch (err: any) {
            const errorMessage = err.response?.data?.message || 'Failed to create static customer'
            setError(errorMessage)
        } finally {
            setIsSubmitting(false)
        }
    }

    const handleClose = () => {
        if (!isSubmitting) {
            setCustomerName('')
            setReportDestination('')
            setError(null)
            setSuccess(false)
            onOpenChange(false)
        }
    }

    if (success) {
        return (
            <Dialog open={open} onOpenChange={handleClose}>
                <DialogContent className="sm:max-w-md">
                    <div className="flex flex-col items-center justify-center py-8">
                        <div className="mb-4 rounded-full bg-green-500/10 p-3">
                            <CheckCircle2 className="h-12 w-12 text-green-500" />
                        </div>
                        <DialogTitle className="text-2xl text-green-600 mb-2">
                            Customer Created!
                        </DialogTitle>
                        <DialogDescription className="text-center">
                            The static customer has been created successfully.
                        </DialogDescription>
                    </div>
                </DialogContent>
            </Dialog>
        )
    }

    return (
        <Dialog open={open} onOpenChange={handleClose}>
            <DialogContent className="sm:max-w-[500px]">
                <DialogHeader>
                    <div className="flex items-center gap-2">
                        <UserPlus className="h-5 w-5 text-primary" />
                        <DialogTitle>Create Static Customer</DialogTitle>
                    </div>
                    <DialogDescription>
                        Create a customer manually without linking to any integration. You can map integrations to this customer later.
                    </DialogDescription>
                </DialogHeader>

                <form onSubmit={handleSubmit} className="space-y-4">
                    <div>
                        <Label htmlFor="customerName" className="text-sm font-medium mb-2 block">
                            Customer Name <span className="text-destructive">*</span>
                        </Label>
                        <Input
                            id="customerName"
                            type="text"
                            placeholder="Enter customer name..."
                            value={customerName}
                            onChange={(e) => setCustomerName(e.target.value)}
                            disabled={isSubmitting}
                            className="w-full"
                            autoFocus
                        />
                        <p className="text-xs text-muted-foreground mt-1">
                            This will be the canonical name for the customer.
                        </p>
                    </div>

                    <div>
                        <Label htmlFor="reportDestination" className="text-sm font-medium mb-2 block">
                            Report Destination Email (Optional)
                        </Label>
                        <Input
                            id="reportDestination"
                            type="email"
                            placeholder="email@example.com"
                            value={reportDestination}
                            onChange={(e) => setReportDestination(e.target.value)}
                            disabled={isSubmitting}
                            className="w-full"
                        />
                        <p className="text-xs text-muted-foreground mt-1">
                            Email address where reports will be sent.
                        </p>
                    </div>

                    {error && (
                        <div className="rounded-lg border border-destructive/50 bg-destructive/10 p-3">
                            <p className="text-sm text-destructive font-medium">{error}</p>
                        </div>
                    )}

                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={handleClose}
                            disabled={isSubmitting}
                        >
                            Cancel
                        </Button>
                        <Button type="submit" disabled={isSubmitting || !customerName.trim()}>
                            {isSubmitting ? (
                                <>
                                    <Spinner className="mr-2 h-4 w-4" />
                                    Creating...
                                </>
                            ) : (
                                <>
                                    <UserPlus className="mr-2 h-4 w-4" />
                                    Create Customer
                                </>
                            )}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    )
}
