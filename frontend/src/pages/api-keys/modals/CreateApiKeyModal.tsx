import { useState, useEffect } from 'react'
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { logger } from '@/lib/logger'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Checkbox } from '@/components/ui/checkbox'
import { Spinner } from '@/components/ui/spinner'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { CheckCircle2, Copy, AlertCircle } from 'lucide-react'
import { api } from '@/lib/api'

interface CreateApiKeyModalProps {
  open: boolean
  onOpenChange: (open: boolean) => void
  onSuccess: () => void
}

interface Customer {
  customer_id: number
  canonical_name: string
}

export function CreateApiKeyModal({ open, onOpenChange, onSuccess }: CreateApiKeyModalProps) {
  const [step, setStep] = useState<'form' | 'success'>('form')
  const [isSubmitting, setIsSubmitting] = useState(false)
  const [displayName, setDisplayName] = useState('')
  const [customerId, setCustomerId] = useState<string>('')
  const [ipWhitelist, setIpWhitelist] = useState('')
  const [generatedKey, setGeneratedKey] = useState('')
  const [customers, setCustomers] = useState<Customer[]>([])
  const [loadingCustomers, setLoadingCustomers] = useState(false)
  const [permissions, setPermissions] = useState({
    integrations: { view: false, manage: false, sync: false },
    customers: { view: false, edit: false, map: false },
    reports: { view: false, configure: false, generate: false, download: false },
  })

  // Load customers when modal opens
  useEffect(() => {
    if (open) {
      loadCustomers()
    }
  }, [open])

  const loadCustomers = async () => {
    setLoadingCustomers(true)
    try {
      const response = await api.get('/customers') as { data: { data: Customer[] } }
      if (response.data?.data) {
        setCustomers(response.data.data)
      }
    } catch (error) {
      logger.error('Failed to load customers:', error)
    } finally {
      setLoadingCustomers(false)
    }
  }

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault()
    setIsSubmitting(true)

    try {
      const response = await api.post<{ status: string; api_key: string }>('/api-keys', {
        display_name: displayName,
        customer_id: (customerId && customerId !== 'none') ? parseInt(customerId) : null,
        permissions,
        ip_whitelist: ipWhitelist ? ipWhitelist.split(',').map(ip => ip.trim()) : null,
      })

      if (response?.api_key) {
        setGeneratedKey(response.api_key)
        setStep('success')
      }
    } catch (error) {
      logger.error('Failed to create API key:', error)
    } finally {
      setIsSubmitting(false)
    }
  }

  const handleCopy = () => {
    navigator.clipboard.writeText(generatedKey)
  }

  const handleClose = () => {
    if (step === 'success') {
      onSuccess()
    }
    setStep('form')
    setDisplayName('')
    setCustomerId('')
    setIpWhitelist('')
    setGeneratedKey('')
    setPermissions({
      integrations: { view: false, manage: false, sync: false },
      customers: { view: false, edit: false, map: false },
      reports: { view: false, configure: false, generate: false, download: false },
    })
    onOpenChange(false)
  }

  const togglePermission = (category: keyof typeof permissions, action: string) => {
    setPermissions(prev => ({
      ...prev,
      [category]: {
        ...prev[category],
        [action]: !prev[category][action as keyof typeof prev[typeof category]]
      }
    }))
  }

  return (
    <Dialog open={open} onOpenChange={handleClose}>
      <DialogContent className="max-w-2xl max-h-[90vh] overflow-y-auto">
        {step === 'form' ? (
          <>
            <DialogHeader>
              <DialogTitle>Create API Key</DialogTitle>
              <DialogDescription>
                Generate a new API key for programmatic access to the platform
              </DialogDescription>
            </DialogHeader>

            <form onSubmit={handleSubmit} className="space-y-6">
              {/* Display Name */}
              <div className="space-y-2">
                <Label htmlFor="displayName">Display Name *</Label>
                <Input
                  id="displayName"
                  value={displayName}
                  onChange={(e) => setDisplayName(e.target.value)}
                  placeholder="e.g., Production Reporting API"
                  required
                />
                <p className="text-sm text-muted-foreground">
                  A friendly name to identify this API key
                </p>
              </div>

              {/* Customer Scope (for AD/Exchange agents) */}
              <div className="space-y-2">
                <Label htmlFor="customerId">Customer Scope (Optional)</Label>
                <Select value={customerId} onValueChange={setCustomerId}>
                  <SelectTrigger>
                    <SelectValue placeholder={loadingCustomers ? "Loading..." : "Select a customer (for agent keys)"} />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem value="none">No customer (company-wide)</SelectItem>
                    {customers.map((customer) => (
                      <SelectItem key={customer.customer_id} value={customer.customer_id.toString()}>
                        {customer.canonical_name}
                      </SelectItem>
                    ))}
                  </SelectContent>
                </Select>
                <p className="text-sm text-muted-foreground">
                  For AD/Exchange sync agents, select the customer this key will sync data for.
                </p>
              </div>

              {/* IP Whitelist */}
              <div className="space-y-2">
                <Label htmlFor="ipWhitelist">IP Whitelist (Optional)</Label>
                <Input
                  id="ipWhitelist"
                  value={ipWhitelist}
                  onChange={(e) => setIpWhitelist(e.target.value)}
                  placeholder="e.g., 192.168.1.100, 10.0.0.50"
                />
                <p className="text-sm text-muted-foreground">
                  Comma-separated list of allowed IP addresses. Leave empty to allow all IPs.
                </p>
              </div>

              {/* Permissions */}
              <div className="space-y-4">
                <Label>Permissions *</Label>

                {/* Integrations */}
                <div className="space-y-2">
                  <p className="text-sm font-medium">Integrations</p>
                  <div className="space-y-2 pl-4">
                    {['view', 'manage', 'sync'].map(action => (
                      <div key={action} className="flex items-center space-x-2">
                        <Checkbox
                          id={`integrations-${action}`}
                          checked={permissions.integrations[action as keyof typeof permissions.integrations]}
                          onCheckedChange={() => togglePermission('integrations', action)}
                        />
                        <label
                          htmlFor={`integrations-${action}`}
                          className="text-sm cursor-pointer"
                        >
                          {action.charAt(0).toUpperCase() + action.slice(1)}
                        </label>
                      </div>
                    ))}
                  </div>
                </div>

                {/* Customers */}
                <div className="space-y-2">
                  <p className="text-sm font-medium">Customers</p>
                  <div className="space-y-2 pl-4">
                    {['view', 'edit', 'map'].map(action => (
                      <div key={action} className="flex items-center space-x-2">
                        <Checkbox
                          id={`customers-${action}`}
                          checked={permissions.customers[action as keyof typeof permissions.customers]}
                          onCheckedChange={() => togglePermission('customers', action)}
                        />
                        <label
                          htmlFor={`customers-${action}`}
                          className="text-sm cursor-pointer"
                        >
                          {action.charAt(0).toUpperCase() + action.slice(1)}
                        </label>
                      </div>
                    ))}
                  </div>
                </div>

                {/* Reports */}
                <div className="space-y-2">
                  <p className="text-sm font-medium">Reports</p>
                  <div className="space-y-2 pl-4">
                    {['view', 'configure', 'generate', 'download'].map(action => (
                      <div key={action} className="flex items-center space-x-2">
                        <Checkbox
                          id={`reports-${action}`}
                          checked={permissions.reports[action as keyof typeof permissions.reports]}
                          onCheckedChange={() => togglePermission('reports', action)}
                        />
                        <label
                          htmlFor={`reports-${action}`}
                          className="text-sm cursor-pointer"
                        >
                          {action.charAt(0).toUpperCase() + action.slice(1)}
                        </label>
                      </div>
                    ))}
                  </div>
                </div>
              </div>

              <DialogFooter>
                <Button type="button" variant="outline" onClick={handleClose}>
                  Cancel
                </Button>
                <Button type="submit" disabled={isSubmitting || !displayName}>
                  {isSubmitting ? (
                    <>
                      <Spinner className="mr-2" />
                      Creating...
                    </>
                  ) : (
                    'Create API Key'
                  )}
                </Button>
              </DialogFooter>
            </form>
          </>
        ) : (
          <>
            <DialogHeader>
              <DialogTitle className="flex items-center gap-2 text-green-600">
                <CheckCircle2 className="h-5 w-5" />
                API Key Created Successfully
              </DialogTitle>
              <DialogDescription>
                Copy your API key now - it will not be shown again
              </DialogDescription>
            </DialogHeader>

            <div className="space-y-4">
              {/* Warning */}
              <div className="flex gap-3 p-4 bg-amber-50 dark:bg-amber-950/20 border border-amber-200 dark:border-amber-900 rounded-lg">
                <AlertCircle className="h-5 w-5 text-amber-600 dark:text-amber-500 flex-shrink-0 mt-0.5" />
                <div className="space-y-1">
                  <p className="text-sm font-medium text-amber-900 dark:text-amber-200">
                    Save this key immediately
                  </p>
                  <p className="text-sm text-amber-800 dark:text-amber-300">
                    This is the only time you'll see this key. Store it securely - if lost, you'll need to create a new one.
                  </p>
                </div>
              </div>

              {/* API Key Display */}
              <div className="space-y-2">
                <Label>Your API Key</Label>
                <div className="flex gap-2">
                  <Input
                    value={generatedKey}
                    readOnly
                    className="font-mono text-sm"
                  />
                  <Button onClick={handleCopy} variant="outline">
                    <Copy className="h-4 w-4" />
                  </Button>
                </div>
              </div>

              {/* Display Name */}
              <div className="space-y-2">
                <Label>Display Name</Label>
                <Input value={displayName} readOnly />
              </div>
            </div>

            <DialogFooter>
              <Button onClick={handleClose}>
                Done
              </Button>
            </DialogFooter>
          </>
        )}
      </DialogContent>
    </Dialog>
  )
}
