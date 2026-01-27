import { useState, useEffect } from 'react'
import { Button } from '@/components/ui/button'
import { logger } from '@/lib/logger'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { Spinner } from '@/components/ui/spinner'
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { Badge } from '@/components/ui/badge'
import { Checkbox } from '@/components/ui/checkbox'
import { Mail, Lock, User as UserIcon, CheckCircle2, Eye, EyeOff, Users, X } from 'lucide-react'
import { api } from '@/lib/api'
import { useToast } from '@/components/ui/Toast'
import { customerApi } from '@/services/api/customers'
import { userApi } from '@/services/api/users'
import type { Customer } from '@/types/api/customer'
import type { Role } from '@/types/api/user'

interface CreateUserModalProps {
  open: boolean
  onOpenChange: (open: boolean) => void
  onSuccess?: () => void
}

interface CreateUserForm {
  name: string
  email: string
  password: string
  role_id: number
  status: 'active' | 'inactive' | 'locked'
  auth_method: 'local' | 'saml' | 'm365' | 'ldap'
  scoped_customers?: number[]
  force_password_change?: boolean
}

// Password strength calculation
function calculatePasswordStrength(password: string): { score: number; label: string; color: string } {
  let score = 0
  
  if (password.length >= 6) score += 1
  if (password.length >= 8) score += 1
  if (password.length >= 12) score += 1
  if (/[a-z]/.test(password)) score += 1
  if (/[A-Z]/.test(password)) score += 1
  if (/[0-9]/.test(password)) score += 1
  if (/[^a-zA-Z0-9]/.test(password)) score += 1
  
  if (score <= 2) return { score, label: 'Weak', color: 'bg-red-500' }
  if (score <= 4) return { score, label: 'Fair', color: 'bg-orange-500' }
  if (score <= 5) return { score, label: 'Good', color: 'bg-yellow-500' }
  return { score, label: 'Strong', color: 'bg-green-500' }
}

export function CreateUserModal({ open, onOpenChange, onSuccess }: CreateUserModalProps) {
  const toast = useToast()
  const [isSubmitting, setIsSubmitting] = useState(false)
  const [error, setError] = useState('')
  const [success, setSuccess] = useState(false)
  const [showPassword, setShowPassword] = useState(false)

  const [formData, setFormData] = useState<CreateUserForm>({
    name: '',
    email: '',
    password: '',
    role_id: 2,
    status: 'active',
    auth_method: 'local',
    scoped_customers: [],
    force_password_change: true,
  })

  // Calculate password strength
  const passwordStrength = calculatePasswordStrength(formData.password)

  const [selectedCustomers, setSelectedCustomers] = useState<number[]>([])
  const [customerSearch, setCustomerSearch] = useState('')
  const [roles, setRoles] = useState<Role[]>([])
  const [loadingRoles, setLoadingRoles] = useState(true)
  const [customers, setCustomers] = useState<Customer[]>([])
  const [isLoadingCustomers, setIsLoadingCustomers] = useState(false)
  const [currentPage, setCurrentPage] = useState(1)
  const [totalPages, setTotalPages] = useState(1)
  const [totalCustomers, setTotalCustomers] = useState(0)
  const [searchDebounce, setSearchDebounce] = useState<number | null>(null)
  const [isSelectingAll, setIsSelectingAll] = useState(false)

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
      setTotalCustomers(response.pagination?.total || 0)
    } catch (err) {
      logger.error('Failed to fetch customers:', err)
      toast.error('Error', 'Failed to load customers')
    } finally {
      setIsLoadingCustomers(false)
    }
  }

  // Fetch roles on mount
  useEffect(() => {
    const fetchRoles = async () => {
      try {
        setLoadingRoles(true)
        const response = await userApi.getRoles()
        if (response.data) {
          setRoles(response.data)
        }
      } catch (error) {
        logger.error('Failed to fetch roles:', error)
        toast.error('Error', 'Failed to load roles')
      } finally {
        setLoadingRoles(false)
      }
    }

    fetchRoles()
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [])

  // Load initial customers
  useEffect(() => {
    if (open) {
      fetchCustomers(1)
      setCurrentPage(1)
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [open])

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault()
    setError('')
    setIsSubmitting(true)

    try {
      const payload = {
        ...formData,
        scoped_customers: selectedCustomers.length > 0 ? selectedCustomers : undefined,
      }
      const response = await api.post('/users', payload)

      if (response.data) {
        setSuccess(true)
        toast.success('User Created', `${formData.name} has been created successfully`)
        // Reset form after 1 second and close
        setTimeout(() => {
          setSuccess(false)
          setFormData({
            name: '',
            email: '',
            password: '',
            role_id: 2,
            status: 'active',
            auth_method: 'local',
            scoped_customers: [],
            force_password_change: true,
          })
          setSelectedCustomers([])
          setCustomerSearch('')
          setCurrentPage(1)
          if (searchDebounce) {
            clearTimeout(searchDebounce)
            setSearchDebounce(null)
          }
          onOpenChange(false)
          onSuccess?.()
        }, 1000)
      }
    } catch (error) {
      const errorMessage = error instanceof Error ? error.message : 'Failed to create user'
      setError(errorMessage)
    } finally {
      setIsSubmitting(false)
    }
  }

  const handleInputChange = (field: keyof CreateUserForm, value: string | number | boolean) => {
    setFormData(prev => ({ ...prev, [field]: value }))
  }

  const handleClose = () => {
    if (!isSubmitting) {
      setError('')
      setSuccess(false)
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

  // Select all customers on current page
  const selectCurrentPage = () => {
    const currentPageIds = customers.map(c => c.customer_id)
    setSelectedCustomers(prev => {
      const newSelection = [...prev]
      currentPageIds.forEach(id => {
        if (!newSelection.includes(id)) {
          newSelection.push(id)
        }
      })
      return newSelection
    })
  }

  // Deselect all customers on current page
  const deselectCurrentPage = () => {
    const currentPageIds = customers.map(c => c.customer_id)
    setSelectedCustomers(prev => prev.filter(id => !currentPageIds.includes(id)))
  }

  // Check if all customers on current page are selected
  const isCurrentPageAllSelected = () => {
    if (customers.length === 0) return false
    return customers.every(c => selectedCustomers.includes(c.customer_id))
  }

  // Check if some customers on current page are selected
  const isCurrentPagePartiallySelected = () => {
    if (customers.length === 0) return false
    const selectedOnPage = customers.filter(c => selectedCustomers.includes(c.customer_id))
    return selectedOnPage.length > 0 && selectedOnPage.length < customers.length
  }

  // Select all customers across all pages
  const selectAllCustomers = async () => {
    try {
      setIsSelectingAll(true)
      // Fetch all customer IDs from the API
      const response = await customerApi.getAllCustomerIds()
      if (response.data) {
        setSelectedCustomers(response.data)
        toast.success('Selected All', `${response.data.length} customers selected`)
      }
    } catch (err) {
      logger.error('Failed to select all customers:', err)
      toast.error('Error', 'Failed to select all customers')
    } finally {
      setIsSelectingAll(false)
    }
  }

  // Deselect all customers
  const deselectAllCustomers = () => {
    setSelectedCustomers([])
  }

  const getCustomerName = (customerId: number) => {
    const customer = customers.find(c => c.customer_id === customerId)
    return customer?.canonical_name || `Customer ${customerId}`
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

  if (success) {
    return (
      <Dialog open={open} onOpenChange={handleClose}>
        <DialogContent className="sm:max-w-md">
          <div className="flex flex-col items-center justify-center py-8">
            <div className="mb-4 rounded-full bg-green-500/10 p-3">
              <CheckCircle2 className="h-12 w-12 text-green-500" />
            </div>
            <DialogTitle className="text-2xl text-green-600 mb-2">User Created!</DialogTitle>
            <DialogDescription className="text-center">
              The user account has been created successfully.
            </DialogDescription>
          </div>
        </DialogContent>
      </Dialog>
    )
  }

  return (
    <Dialog open={open} onOpenChange={handleClose}>
      <DialogContent className="sm:max-w-[900px] max-h-[90vh] overflow-y-auto [&::-webkit-scrollbar]:hidden [-ms-overflow-style:none] [scrollbar-width:none]">
        <DialogHeader>
          <div className="flex items-center gap-2">
            <UserIcon className="h-5 w-5 text-primary" />
            <DialogTitle>Create New User</DialogTitle>
          </div>
          <DialogDescription>
            Add a new user to your organization
          </DialogDescription>
        </DialogHeader>

        <form onSubmit={handleSubmit} className="space-y-4">
          {/* Two Column Grid */}
          <div className="grid grid-cols-2 gap-6 divide-x divide-border">
            {/* Left Column */}
            <div className="space-y-4 pr-6">
              {/* Name */}
              <div className="space-y-2">
                <Label htmlFor="modal-name">Full Name *</Label>
                <div className="relative">
                  <UserIcon className="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground" />
                  <Input
                    id="modal-name"
                    type="text"
                    placeholder="John Doe"
                    value={formData.name}
                    onChange={(e) => handleInputChange('name', e.target.value)}
                    className="pl-10"
                    required
                    disabled={isSubmitting}
                  />
                </div>
              </div>

              {/* Email */}
              <div className="space-y-2">
                <Label htmlFor="modal-email">Email Address *</Label>
                <div className="relative">
                  <Mail className="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground" />
                  <Input
                    id="modal-email"
                    type="email"
                    placeholder="john.doe@company.com"
                    value={formData.email}
                    onChange={(e) => handleInputChange('email', e.target.value)}
                    className="pl-10"
                    required
                    disabled={isSubmitting}
                  />
                </div>
              </div>

              {/* Password */}
              <div className="space-y-2">
                <Label htmlFor="modal-password">Password *</Label>
                <div className="relative">
                  <Lock className="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground" />
                  <Input
                    id="modal-password"
                    type={showPassword ? "text" : "password"}
                    placeholder="Minimum 6 characters"
                    value={formData.password}
                    onChange={(e) => handleInputChange('password', e.target.value)}
                    className="pl-10 pr-10"
                    required
                    minLength={6}
                    disabled={isSubmitting}
                  />
                  <button
                    type="button"
                    onClick={() => setShowPassword(!showPassword)}
                    className="absolute right-3 top-1/2 -translate-y-1/2 text-muted-foreground hover:text-foreground transition-colors"
                    tabIndex={-1}
                  >
                    {showPassword ? (
                      <EyeOff className="h-4 w-4" />
                    ) : (
                      <Eye className="h-4 w-4" />
                    )}
                  </button>
                </div>
                {/* Password Strength Indicator */}
                {formData.password && (
                  <div className="space-y-1">
                    <div className="flex items-center justify-between text-xs">
                      <span className="text-muted-foreground">Password Strength</span>
                      <span className={`font-medium ${
                        passwordStrength.label === 'Weak' ? 'text-red-500' :
                        passwordStrength.label === 'Fair' ? 'text-orange-500' :
                        passwordStrength.label === 'Good' ? 'text-yellow-600' :
                        'text-green-500'
                      }`}>{passwordStrength.label}</span>
                    </div>
                    <div className="h-1.5 w-full bg-muted rounded-full overflow-hidden">
                      <div 
                        className={`h-full transition-all duration-300 ${passwordStrength.color}`}
                        style={{ width: `${(passwordStrength.score / 7) * 100}%` }}
                      />
                    </div>
                    <p className="text-[10px] text-muted-foreground">
                      Use 8+ characters with uppercase, lowercase, numbers, and symbols
                    </p>
                  </div>
                )}
              </div>

              {/* Force Password Change */}
              <div className="flex items-center space-x-2">
                <Checkbox
                  id="force-password-change"
                  checked={formData.force_password_change}
                  onCheckedChange={(checked) => handleInputChange('force_password_change', checked === true)}
                  disabled={isSubmitting}
                />
                <label
                  htmlFor="force-password-change"
                  className="text-sm font-medium leading-none peer-disabled:cursor-not-allowed peer-disabled:opacity-70 cursor-pointer"
                >
                  Require password change on first login
                </label>
              </div>

              {/* Role */}
              <div className="space-y-2">
                <Label htmlFor="modal-role">Role *</Label>
                <Select
                  value={formData.role_id.toString()}
                  onValueChange={(value: string) => handleInputChange('role_id', parseInt(value))}
                  disabled={isSubmitting}
                >
                  <SelectTrigger id="modal-role">
                    <SelectValue placeholder="Select a role" />
                  </SelectTrigger>
                  <SelectContent>
                    {loadingRoles ? (
                      <SelectItem value="0" disabled>Loading roles...</SelectItem>
                    ) : roles.length > 0 ? (
                      roles.map((role) => (
                        <SelectItem key={role.id} value={role.id.toString()}>
                          {role.name}
                        </SelectItem>
                      ))
                    ) : (
                      <SelectItem value="0" disabled>No roles available</SelectItem>
                    )}
                  </SelectContent>
                </Select>
              </div>

              {/* Status */}
              <div className="space-y-2">
                <Label htmlFor="modal-status">Status *</Label>
                <Select
                  value={formData.status}
                  onValueChange={(value: string) => handleInputChange('status', value)}
                  disabled={isSubmitting}
                >
                  <SelectTrigger id="modal-status">
                    <SelectValue placeholder="Select status" />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem value="active">Active</SelectItem>
                    <SelectItem value="inactive">Inactive</SelectItem>
                    <SelectItem value="locked">Locked</SelectItem>
                  </SelectContent>
                </Select>
              </div>
            </div>

            {/* Right Column */}
            <div className="space-y-4 pl-6">
              {/* Auth Method */}
              <div className="space-y-2">
                <Label htmlFor="modal-auth">Authentication Method *</Label>
                <Select
                  value={formData.auth_method}
                  onValueChange={(value: string) => handleInputChange('auth_method', value)}
                  disabled={isSubmitting}
                >
                  <SelectTrigger id="modal-auth">
                    <SelectValue placeholder="Select authentication method" />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem value="local">Local (Email/Password)</SelectItem>
                    <SelectItem value="saml">SAML</SelectItem>
                    <SelectItem value="m365">Microsoft 365</SelectItem>
                    <SelectItem value="ldap">LDAP</SelectItem>
                  </SelectContent>
                </Select>
              </div>

              {/* Customer Scoping */}
              <div className="space-y-2">
                <Label htmlFor="modal-customers" className="flex items-center gap-2">
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
                  id="modal-customers"
                  type="text"
                  placeholder="Search customers..."
                  value={customerSearch}
                  onChange={(e) => handleSearchChange(e.target.value)}
                  disabled={isSubmitting || isLoadingCustomers}
                />

                {/* Bulk Selection Controls */}
                <div className="flex flex-wrap items-center gap-3 p-2 bg-muted/30 rounded-md">
                  <div className="flex items-center gap-2">
                    <Button
                      type="button"
                      variant="outline"
                      size="sm"
                      onClick={selectedCustomers.length === totalCustomers && totalCustomers > 0 ? deselectAllCustomers : selectAllCustomers}
                      disabled={isSubmitting || isLoadingCustomers || isSelectingAll || totalCustomers === 0}
                      className="text-xs"
                    >
                      {isSelectingAll ? (
                        <>
                          <Spinner className="h-3 w-3 mr-1" />
                          Loading...
                        </>
                      ) : selectedCustomers.length === totalCustomers && totalCustomers > 0 ? (
                        'Deselect All'
                      ) : (
                        `Select All (${totalCustomers})`
                      )}
                    </Button>
                  </div>
                  <div className="flex items-center gap-2">
                    <Checkbox
                      id="select-current-page"
                      checked={isCurrentPageAllSelected()}
                      onCheckedChange={(checked) => {
                        if (checked) {
                          selectCurrentPage()
                        } else {
                          deselectCurrentPage()
                        }
                      }}
                      disabled={isSubmitting || isLoadingCustomers || customers.length === 0}
                      className={isCurrentPagePartiallySelected() ? 'data-[state=unchecked]:bg-primary/30' : ''}
                    />
                    <label htmlFor="select-current-page" className="text-xs text-muted-foreground cursor-pointer">
                      Select page {currentPage}/{totalPages}
                    </label>
                  </div>
                  {selectedCustomers.length > 0 && (
                    <span className="text-xs text-primary font-medium ml-auto">
                      {selectedCustomers.length} selected
                    </span>
                  )}
                </div>

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
                        <label
                          key={customer.customer_id}
                          className="flex items-center gap-2 p-2 hover:bg-muted/50 cursor-pointer"
                        >
                          <Checkbox
                            checked={selectedCustomers.includes(customer.customer_id)}
                            onCheckedChange={() => toggleCustomer(customer.customer_id)}
                          />
                          <span className="text-sm flex-1">{customer.canonical_name}</span>
                        </label>
                      ))}
                    </div>
                  )}
                </div>
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
          </div>

          {/* Error Message */}
          {error && (
            <div className="rounded-lg border border-destructive/50 bg-destructive/10 p-3">
              <p className="text-sm text-destructive font-medium">{error}</p>
            </div>
          )}

          <DialogFooter className="mt-6">
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
                  Creating...
                </>
              ) : (
                'Create User'
              )}
            </Button>
          </DialogFooter>
        </form>
      </DialogContent>
    </Dialog>
  )
}
