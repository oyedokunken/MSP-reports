import { useState, useEffect } from 'react'
import { Button } from '@/components/ui/button'
import { logger } from '@/lib/logger'
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { Spinner } from '@/components/ui/spinner'
import { Users, ExternalLink } from 'lucide-react'
import { templateApi } from '@/lib/api/templates'
import type { ReportTemplate } from '../types/template'
import { useToast } from '@/components/ui/Toast'
import { useNavigate } from 'react-router-dom'

interface AppliedCustomersModalProps {
    open: boolean
    onOpenChange: (open: boolean) => void
    template: ReportTemplate | null
}

interface Customer {
    customer_id: number
    canonical_name: string
}

export function AppliedCustomersModal({ open, onOpenChange, template }: AppliedCustomersModalProps) {
    const toast = useToast()
    const navigate = useNavigate()
    const [isLoading, setIsLoading] = useState(false)
    const [customers, setCustomers] = useState<Customer[]>([])

    useEffect(() => {
        if (open && template) {
            loadAppliedCustomers()
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [open, template])

    const loadAppliedCustomers = async () => {
        if (!template) return
        setIsLoading(true)
        try {
            const response = await templateApi.getAppliedCustomers(template.template_id)
            setCustomers(response.data)
        } catch (err) {
            logger.error('Failed to fetch applied customers:', err)
            toast.error('Failed to load applied customers')
            setCustomers([])
        } finally {
            setIsLoading(false)
        }
    }

    const handleNavigateToCustomer = (customerId: number) => {
        navigate(`/platform/customers/${customerId}/configure-reports`)
        onOpenChange(false)
    }

    if (!template) return null

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="sm:max-w-[500px] max-h-[80vh] flex flex-col">
                <DialogHeader>
                    <div className="flex items-center gap-2">
                        <Users className="h-5 w-5 text-primary" />
                        <DialogTitle>Applied Customers</DialogTitle>
                    </div>
                    <DialogDescription>
                        Customers who are currently using "{template.template_name}"
                    </DialogDescription>
                </DialogHeader>

                <div className="flex-1 overflow-y-auto min-h-[200px] border rounded-lg mt-4">
                    {isLoading ? (
                        <div className="flex items-center justify-center h-full py-12">
                            <Spinner className="h-8 w-8" />
                        </div>
                    ) : customers.length === 0 ? (
                        <div className="flex flex-col items-center justify-center h-full py-12 text-muted-foreground">
                            <Users className="h-12 w-12 mb-4 opacity-20" />
                            <p className="text-sm">No customers have this template applied yet.</p>
                        </div>
                    ) : (
                        <div className="divide-y">
                            {customers.map((customer) => (
                                <div
                                    key={customer.customer_id}
                                    className="flex items-center justify-between p-4 hover:bg-muted/50 transition-colors"
                                >
                                    <div className="flex flex-col">
                                        <span className="font-medium text-sm">{customer.canonical_name}</span>
                                        <span className="text-xs text-muted-foreground">ID: {customer.customer_id}</span>
                                    </div>
                                    <Button
                                        variant="ghost"
                                        size="sm"
                                        onClick={() => handleNavigateToCustomer(customer.customer_id)}
                                        className="h-8 text-white hover:text-primary bg-primary hover:bg-primary/10"
                                    >
                                        <ExternalLink className="h-4 w-4 mr-2" />
                                        Configure
                                    </Button>
                                </div>
                            ))}
                        </div>
                    )}
                </div>

                <DialogFooter className="mt-6">
                    <Button variant="outline" onClick={() => onOpenChange(false)}>
                        Close
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    )
}
