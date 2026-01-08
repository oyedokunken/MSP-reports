import { useState, useEffect } from 'react'
import type { UserWithPermissions } from '../types'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { Spinner } from '@/components/ui/spinner'
import { Badge } from '@/components/ui/badge'
import { Checkbox } from '@/components/ui/checkbox'
import { CheckCircle2, User, AlertCircle, Users, X } from 'lucide-react'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { api } from '@/lib/api'
import { useToast } from '@/components/ui/Toast'
import { customerApi } from '@/services/api/customers'
import type { Customer } from '@/types/api/customer'

interface EditUserModalProps {
  open: boolean
  onOpenChange: (open: boolean) => void
  user: UserWithPermissions | null
  onSuccess?: () => void
}

export function EditUserModal({
  open,
  onOpenChange,
  user,
  onSuccess
}: EditUserModalProps) {
  const [isSubmitting, setIsSubmitting] = useState(false)
  const [error, setError] = useState('')
  const [success, setSuccess] = useState(false)
  const [name, setName] = useState('')
  const [email, setEmail] = useState('')
  const [roleId, setRoleId] = useState('')
  const [selectedCustomers, setSelectedCustomers] = useState<number[]>([])
  const [customerSearch, setCustomerSearch] = useState('')
  const [customers, setCustomers] = useState<Customer[]>([])
  const [isLoadingCustomers, setIsLoadingCustomers] = useState(false)
  const [currentPage, setCurrentPage] = useState(1)
  const [totalPages, setTotalPages] = useState(1)
  const [searchDebounce, setSearchDebounce] = useState<number | null>(null)
  const toast = useToast()

  // Fetch customers with pagination
  const fetchCustomers = async (page: number = 1, search: string = '') => {
    try {
      setIsLoadingCustomers(true)
      const response = await customerApi.getCustomers({
        page,
        search: search || undefined
      })
      setCustomers(response.data || [])
      setCurrentPage(response.pagination?.current_page || 1)
      setTotalPages(response.pagination?.last_page || 1)
    } catch (err) {
      console.error('Failed to fetch customers:', err)
      toast.error('Error', 'Failed to load customers')
    } finally {
      setIsLoadingCustomers(false)
    }
  }

  // Load initial customers
  useEffect(() => {
    if (open) {
      fetchCustomers(1)
      setCurrentPage(1)
    }
  }, [open])

  useEffect(() => {
    if (open && user) {
      setName(user.name || '')
      setEmail(user.email || '')
      setRoleId(user.role_id?.toString() || user.role?.role_id?.toString() || '2')
      setSelectedCustomers(user.scoped_customers || [])
      setError('')
      setSuccess(false)
    }
  }, [open, user])

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault()

    if (!user) return

    if (!name.trim()) {
      setError('Please enter a name')
      return
    }

    if (!email.trim()) {
      setError('Please enter an email')
      return
    }

    setError('')
    setIsSubmitting(true)

    try {
      await api.patch(`/users/${user.user_id}`, {
        name: name.trim(),
        email: email.trim(),
        role_id: parseInt(roleId),
        scoped_customers: selectedCustomers.length > 0 ? selectedCustomers : undefined,
      })

      setSuccess(true)
      setTimeout(() => {
        setSuccess(false)
        onOpenChange(false)
        onSuccess?.()
      }, 2000)
    } catch (error) {
      const errorMessage = error instanceof Error ? error.message : 'Failed to update user'
      setError(errorMessage)
    } finally {
      setIsSubmitting(false)
    }
  }

  const handleClose = () => {
    if (!isSubmitting) {
      setSelectedCustomers([])
      setCustomerSearch('')
      setCurrentPage(1)
      if (searchDebounce) {
        clearTimeout(searchDebounce)
        setSearchDebounce(null)
      }
      onOpenChange(false)
    }
  }

  const toggleCustomer = (customerId: number) => {
    setSelectedCustomers(prev => 
      prev.includes(customerId)
        ? prev.filter(id => id !== customerId)
        : [...prev, customerId]
    )
  }

  const removeCustomer = (customerId: number) => {
    setSelectedCustomers(prev => prev.filter(id => id !== customerId))
  }

  const handleSearchChange = (search: string) => {
    setCustomerSearch(search)

    // Clear existing timeout
    if (searchDebounce) {
      clearTimeout(searchDebounce)
    }

    // Set new timeout to search after 500ms of no typing
    const timeout = setTimeout(() => {
      setCurrentPage(1)
      fetchCustomers(1, search)
    }, 500)

    setSearchDebounce(timeout)
  }

  const handlePageChange = (newPage: number) => {
    if (newPage >= 1 && newPage <= totalPages) {
      fetchCustomers(newPage, customerSearch)
    }
  }

  const getCustomerName = (customerId: number) => {
    const customer = customers.find(c => c.customer_id === customerId)
    return customer?.canonical_name || `Customer ${customerId}`
  }

  if (success) {
    return (
      <Dialog open={open} onOpenChange={handleClose}>
        <DialogContent className="sm:max-w-md">
          <div className="flex flex-col items-center justify-center py-8">
            <div className="mb-4 rounded-full bg-green-500/10 p-3">
              <CheckCircle2 className="h-12 w-12 text-green-500" />
            </div>
            <DialogTitle className="text-2xl text-green-600 mb-2">User Updated!</DialogTitle>
            <DialogDescription className="text-center">
              The user has been updated successfully.
            </DialogDescription>
          </div>
        </DialogContent>
      </Dialog>
    )
  }

  if (!user) return null

  return (
    <Dialog open={open} onOpenChange={handleClose}>
      <DialogContent className="sm:max-w-md">
        <DialogHeader>
          <div className="flex items-center gap-2">
            <User className="h-5 w-5 text-primary" />
            <DialogTitle>Edit User</DialogTitle>
          </div>
          <DialogDescription>
            Update user details for {user.name}
          </DialogDescription>
        </DialogHeader>

        <form onSubmit={handleSubmit} className="space-y-4">
          {/* Name */}
          <div>
            <Label htmlFor="name" className="text-sm font-medium mb-2 block">
              Full Name
            </Label>
            <Input
              id="name"
              type="text"
              placeholder="John Doe"
              value={name}
              onChange={(e) => setName(e.target.value)}
              disabled={isSubmitting}
              className="w-full"
            />
          </div>

          {/* Email */}
          <div>
            <Label htmlFor="email" className="text-sm font-medium mb-2 block">
              Email Address
            </Label>
            <Input
              id="email"
              type="email"
              placeholder="john@company.com"
              value={email}
              onChange={(e) => setEmail(e.target.value)}
              disabled={isSubmitting}
              className="w-full"
            />
          </div>

          {/* Role */}
          <div>
            <Label htmlFor="role" className="text-sm font-medium mb-2 block">
              Role
            </Label>
            <Select value={roleId} onValueChange={setRoleId} disabled={isSubmitting}>
              <SelectTrigger className="w-full">
                <SelectValue placeholder="Select role" />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="1">Admin</SelectItem>
                <SelectItem value="2">Manager</SelectItem>
                <SelectItem value="3">Viewer</SelectItem>
                <SelectItem value="4">Technician</SelectItem>
              </SelectContent>
            </Select>
          </div>

          {/* Customer Scoping */}
          <div className="space-y-2">
            <Label htmlFor="edit-customers" className="flex items-center gap-2">
              <Users className="h-4 w-4" />
              Customer Access Scope
            </Label>
            <p className="text-xs text-muted-foreground">
              Leave empty for full access. Select specific customers to restrict this user's access.
            </p>
            
            {/* Selected Customers */}
            {selectedCustomers.length > 0 && (
              <div className="flex flex-wrap gap-2 p-2 border rounded-md bg-muted/30">
                {selectedCustomers.map(customerId => (
                    <Badge key={customerId} variant="secondary" className="pl-2 pr-1">
                      {getCustomerName(customerId)}
                      <button
                        type="button"
                        onClick={() => removeCustomer(customerId)}
                        className="ml-1 hover:bg-destructive/20 rounded-sm p-0.5"
                      >
                        <X className="h-3 w-3" />
                      </button>
                    </Badge>
                ))}
              </div>
            )}

            {/* Customer Search */}
            <Input
              id="edit-customers"
              type="text"
              placeholder="Search customers..."
              value={customerSearch}
              onChange={(e) => handleSearchChange(e.target.value)}
              disabled={isSubmitting || isLoadingCustomers}
            />

            {/* Customer List */}
            <div className="border rounded-md max-h-48 overflow-y-auto">
              {isLoadingCustomers ? (
                <div className="p-4 text-center text-sm text-muted-foreground">
                  <Spinner className="h-4 w-4 mx-auto mb-2" />
                  Loading customers...
                </div>
              ) : customers.length === 0 ? (
                <div className="p-4 text-center text-sm text-muted-foreground">
                  No customers found
                </div>
              ) : (
                <div className="divide-y">
                  {customers.map(customer => (
                    <div
                      key={customer.customer_id}
                      className="flex items-center gap-2 p-2 hover:bg-muted/50 cursor-pointer"
                      onClick={() => toggleCustomer(customer.customer_id)}
                    >
                      <Checkbox
                        checked={selectedCustomers.includes(customer.customer_id)}
                        onCheckedChange={() => toggleCustomer(customer.customer_id)}
                        onClick={(e) => e.stopPropagation()}
                      />
                      <span className="text-sm flex-1">{customer.canonical_name}</span>
                    </div>
                  ))}
                </div>
              )}
            </div>

            {/* Pagination */}
            {totalPages > 1 && (
              <div className="flex items-center justify-between text-sm text-muted-foreground mt-2">
                <button
                  type="button"
                  onClick={() => handlePageChange(currentPage - 1)}
                  disabled={currentPage === 1 || isLoadingCustomers}
                  className="px-3 py-1 border rounded hover:bg-muted disabled:opacity-50 disabled:cursor-not-allowed"
                >
                  Previous
                </button>
                <span>
                  Page {currentPage} of {totalPages}
                </span>
                <button
                  type="button"
                  onClick={() => handlePageChange(currentPage + 1)}
                  disabled={currentPage === totalPages || isLoadingCustomers}
                  className="px-3 py-1 border rounded hover:bg-muted disabled:opacity-50 disabled:cursor-not-allowed"
                >
                  Next
                </button>
              </div>
            )}
          </div>

          {error && (
            <div className="rounded-lg border border-destructive/50 bg-destructive/10 p-3 flex items-start gap-2">
              <AlertCircle className="h-4 w-4 text-destructive mt-0.5 flex-shrink-0" />
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
            <Button type="submit" disabled={isSubmitting}>
              {isSubmitting ? (
                <>
                  <Spinner className="mr-2 h-4 w-4" />
                  Updating...
                </>
              ) : (
                'Update User'
              )}
            </Button>
          </DialogFooter>
        </form>
      </DialogContent>
    </Dialog>
  )
}
