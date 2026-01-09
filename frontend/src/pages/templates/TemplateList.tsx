import { useState, useEffect } from 'react'
import FloatingLayout from '@/components/layout/FloatingLayout'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Badge } from '@/components/ui/badge'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Spinner } from '@/components/ui/spinner'
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuLabel,
  DropdownMenuSeparator,
  DropdownMenuCheckboxItem,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu'
import {
  FileText,
  Plus,
  Search,
  Settings,
  Trash2,
  Eye,
  Clock,
  Calendar,
  User,
  Filter,
  Globe,
  Users
} from 'lucide-react'
import { templateApi } from '@/lib/api/templates'
import type { ReportTemplate } from './types/template'
import { CreateTemplateModal } from '@/pages/templates/modals/CreateTemplateModal'
import { DeleteTemplateModal } from '@/pages/templates/modals/DeleteTemplateModal'
import { ApplyTemplateModal } from '@/pages/templates/modals/ApplyTemplateModal'
import { AppliedCustomersModal } from '@/pages/templates/modals/AppliedCustomersModal'
import { useToast } from '@/components/ui/Toast'
import { useNavigate } from 'react-router-dom'
import { Pagination } from '@/pages/customers/components/Pagination'

export default function TemplateList() {
  const toast = useToast()
  const navigate = useNavigate()
  const [templates, setTemplates] = useState<ReportTemplate[]>([])
  const [loading, setLoading] = useState(true)
  const [searchQuery, setSearchQuery] = useState('')
  const [statusFilter, setStatusFilter] = useState<string>('all')
  const [reportTypeFilter, setReportTypeFilter] = useState<string>('all')
  const [currentPage, setCurrentPage] = useState(1)
  const [perPage] = useState(9)
  const [createModalOpen, setCreateModalOpen] = useState(false)
  const [editingTemplate, setEditingTemplate] = useState<ReportTemplate | null>(null)
  const [deletingTemplate, setDeletingTemplate] = useState<ReportTemplate | null>(null)
  const [isDeleting, setIsDeleting] = useState(false)
  const [applyingTemplate, setApplyingTemplate] = useState<ReportTemplate | null>(null)
  const [viewingAppliedTemplate, setViewingAppliedTemplate] = useState<ReportTemplate | null>(null)

  useEffect(() => {
    loadTemplates()
  }, [])

  const loadTemplates = async () => {
    try {
      setLoading(true)
      const data = await templateApi.list()
      setTemplates(data)
    } catch (error) {
      toast.error('Failed to load templates')
      console.error(error)
    } finally {
      setLoading(false)
    }
  }

  const handleDeleteClick = (template: ReportTemplate) => {
    setDeletingTemplate(template)
  }

  const handleDeleteConfirm = async () => {
    if (!deletingTemplate) return

    try {
      setIsDeleting(true)
      const response = await templateApi.delete(deletingTemplate.template_id)

      // Show feedback about cascade deletion
      const schedulesDeleted = response?.schedules_deleted || 0
      if (schedulesDeleted > 0) {
        toast.success(`Template deleted successfully. ${schedulesDeleted} schedule${schedulesDeleted !== 1 ? 's' : ''} also removed.`)
      } else {
        toast.success('Template deleted successfully')
      }

      setDeletingTemplate(null)
      loadTemplates()
    } catch (error) {
      toast.error('Failed to delete template')
      console.error(error)
    } finally {
      setIsDeleting(false)
    }
  }

  const handleViewReports = (template: ReportTemplate) => {
    if (template.customer_id) {
      // Navigate to customer's configure reports page
      navigate(`/platform/customers/${template.customer_id}/configure-reports`)
    } else {
      // For global templates, open the apply modal
      setApplyingTemplate(template)
    }
  }

  const handleViewSchedule = (template: ReportTemplate) => {
    setEditingTemplate(template)
    setCreateModalOpen(true)
  }

  const handleModalClose = () => {
    setCreateModalOpen(false)
    setEditingTemplate(null)
    loadTemplates()
  }

  const filteredTemplates = (templates || []).filter(template => {
    const matchesSearch = template.template_name.toLowerCase().includes(searchQuery.toLowerCase()) ||
      template.report_type.toLowerCase().includes(searchQuery.toLowerCase()) ||
      template.description?.toLowerCase().includes(searchQuery.toLowerCase())

    const matchesStatus = statusFilter === 'all' ||
      (statusFilter === 'active' && template.active) ||
      (statusFilter === 'inactive' && !template.active)

    const matchesReportType = reportTypeFilter === 'all' || template.report_type === reportTypeFilter

    return matchesSearch && matchesStatus && matchesReportType
  })

  const uniqueReportTypes = Array.from(new Set(templates.map(t => t.report_type)))
  const activeFilterCount = (statusFilter !== 'all' ? 1 : 0) + (reportTypeFilter !== 'all' ? 1 : 0)

  // Pagination
  const totalPages = Math.ceil(filteredTemplates.length / perPage)
  const startIndex = (currentPage - 1) * perPage
  const endIndex = startIndex + perPage
  const paginatedTemplates = filteredTemplates.slice(startIndex, endIndex)

  // Reset to page 1 when filters change
  useEffect(() => {
    setCurrentPage(1)
  }, [searchQuery, statusFilter, reportTypeFilter])

  const getFrequencyLabel = (frequency: string) => {
    return frequency.charAt(0).toUpperCase() + frequency.slice(1).replace('-', ' ')
  }

  if (loading) {
    return (
      <FloatingLayout>
        <div className="flex items-center justify-center h-96">
          <Spinner className="h-8 w-8" />
        </div>
      </FloatingLayout>
    )
  }

  return (
    <FloatingLayout>
      <div className="space-y-6">
        <div className="space-y-4">
          <div>
            <h1 className="text-3xl font-bold tracking-tight">Report Templates</h1>
            <p className="text-muted-foreground mt-2">
              Create and manage customer-specific report templates
            </p>
          </div>

          {/* Filters and Search */}
          <div className="flex flex-col md:flex-row gap-3">
            {/* Count */}
            <div className="flex items-center text-sm text-muted-foreground whitespace-nowrap px-3 h-10 border rounded-md">
              {filteredTemplates.length} template{filteredTemplates.length !== 1 ? 's' : ''}
            </div>

            {/* Filter Dropdown */}
            <DropdownMenu>
              <DropdownMenuTrigger asChild>
                <Button variant="outline" className="w-full md:w-auto h-10 border">
                  <Filter className="h-4 w-4 mr-2" />
                  Filters
                  {activeFilterCount > 0 && (
                    <span className="ml-2 px-2 py-0.5 text-xs bg-primary text-primary-foreground rounded-full">
                      {activeFilterCount}
                    </span>
                  )}
                </Button>
              </DropdownMenuTrigger>
              <DropdownMenuContent align="start" className="w-56">
                {/* Status Section */}
                <DropdownMenuLabel>Status</DropdownMenuLabel>
                <DropdownMenuCheckboxItem
                  checked={statusFilter === 'active'}
                  onCheckedChange={() => setStatusFilter(statusFilter === 'active' ? 'all' : 'active')}
                >
                  Active
                </DropdownMenuCheckboxItem>
                <DropdownMenuCheckboxItem
                  checked={statusFilter === 'inactive'}
                  onCheckedChange={() => setStatusFilter(statusFilter === 'inactive' ? 'all' : 'inactive')}
                >
                  Inactive
                </DropdownMenuCheckboxItem>

                {/* Report Type Section */}
                {uniqueReportTypes.length > 0 && (
                  <>
                    <DropdownMenuSeparator />
                    <DropdownMenuLabel>Report Type</DropdownMenuLabel>
                    {uniqueReportTypes.map((type) => (
                      <DropdownMenuCheckboxItem
                        key={type}
                        checked={reportTypeFilter === type}
                        onCheckedChange={() => setReportTypeFilter(reportTypeFilter === type ? 'all' : type)}
                      >
                        {type.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase())}
                      </DropdownMenuCheckboxItem>
                    ))}
                  </>
                )}
              </DropdownMenuContent>
            </DropdownMenu>

            {/* Search */}
            <div className="flex-1 relative">
              <Search className="absolute left-3 top-1/2 transform -translate-y-1/2 h-4 w-4 text-muted-foreground" />
              <Input
                placeholder="Search by name or report type..."
                value={searchQuery}
                onChange={(e) => setSearchQuery(e.target.value)}
                className="pl-10 h-10"
              />
            </div>

            {/* Create Template Button */}
            <Button onClick={() => setCreateModalOpen(true)} className="h-10">
              <Plus className="h-4 w-4 mr-2" />
              Create Template
            </Button>
          </div>
        </div>

        {paginatedTemplates.length === 0 && filteredTemplates.length === 0 ? (
          <Card>
            <CardContent className="flex flex-col items-center justify-center py-12">
              <FileText className="h-12 w-12 text-muted-foreground mb-4" />
              <h3 className="text-lg font-semibold mb-2">No templates found</h3>
              <p className="text-sm text-muted-foreground mb-4">
                {searchQuery ? 'Try adjusting your search' : 'Get started by creating your first template'}
              </p>
              {/* {!searchQuery && (
              <Button onClick={() => setCreateModalOpen(true)}>
                <Plus className="mr-2 h-4 w-4" />
                Create Template
              </Button>
            )} */}
            </CardContent>
          </Card>
        ) : paginatedTemplates.length === 0 ? (
          <Card>
            <CardContent className="flex flex-col items-center justify-center py-12">
              <FileText className="h-12 w-12 text-muted-foreground mb-4" />
              <h3 className="text-lg font-semibold mb-2">No templates on this page</h3>
              <p className="text-sm text-muted-foreground mb-4">
                Try going to a different page or adjusting your filters
              </p>
            </CardContent>
          </Card>
        ) : (
          <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
            {paginatedTemplates.map((template) => (
              <Card key={template.template_id} className="hover:shadow-md transition-shadow">
                <CardHeader>
                  <div className="flex items-start justify-between">
                    <div className="flex-1">
                      <CardTitle className="text-lg">{template.template_name}</CardTitle>
                      {template.description && (
                        <CardDescription className="mt-1">{template.description}</CardDescription>
                      )}
                    </div>
                    <div className="flex gap-1">
                      {!template.customer_id && (
                        <Badge variant="default" className="bg-primary/10 text-primary border-primary/20">
                          <Globe className="h-3 w-3 mr-1" />
                          Global
                        </Badge>
                      )}
                      {!template.active && (
                        <Badge variant="secondary">Inactive</Badge>
                      )}
                    </div>
                  </div>
                </CardHeader>
                <CardContent className="space-y-4">
                  <div className="space-y-2 text-sm">
                    {template.customer && (
                      <div className="flex items-center gap-2 text-muted-foreground">
                        <User className="h-4 w-4" />
                        <span className="font-medium">{template.customer.canonical_name}</span>
                      </div>
                    )}
                    <div className="flex items-center gap-2 text-muted-foreground">
                      <FileText className="h-4 w-4" />
                      <span>{template.report_type.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase())}</span>
                    </div>
                    <div className="flex items-center gap-2 text-muted-foreground">
                      <Calendar className="h-4 w-4" />
                      <span>{getFrequencyLabel(template.frequency)}</span>
                    </div>
                    {template.time_of_day && (
                      <div className="flex items-center gap-2 text-muted-foreground">
                        <Clock className="h-4 w-4" />
                        <span>{template.time_of_day} ({template.timezone})</span>
                      </div>
                    )}
                  </div>

                  <div className="flex gap-2 pt-2 border-t">
                    <Button
                      size="sm"
                      className="flex-1"
                      onClick={() => handleViewReports(template)}
                    >
                      {template.customer_id ? (
                        <>
                          <Eye className="mr-2 h-4 w-4" />
                          View Reports
                        </>
                      ) : (
                        <>
                          <Users className="mr-2 h-4 w-4" />
                          Apply to Customers
                        </>
                      )}
                    </Button>
                    {!template.customer_id && (
                      <Button
                        variant="outline"
                        size="sm"
                        onClick={() => setViewingAppliedTemplate(template)}
                        title="View Applied Customers"
                      >
                        <Eye className="h-4 w-4" />
                      </Button>
                    )}
                    <Button
                      variant="outline"
                      size="sm"
                      onClick={() => handleViewSchedule(template)}
                      title="View Schedule"
                    >
                      <Settings className="h-4 w-4" />
                    </Button>
                    <Button
                      variant="outline"
                      size="sm"
                      onClick={() => handleDeleteClick(template)}
                      title="Delete Template"
                    >
                      <Trash2 className="h-4 w-4" />
                    </Button>
                  </div>
                </CardContent>
              </Card>
            ))}
          </div>
        )}

        {/* Pagination */}
        {!loading && filteredTemplates.length > perPage && (
          <Pagination
            currentPage={currentPage}
            totalPages={totalPages}
            total={filteredTemplates.length}
            onPageChange={setCurrentPage}
            itemLabel="templates"
            perPage={perPage}
          />
        )}

        <CreateTemplateModal
          open={createModalOpen}
          onOpenChange={setCreateModalOpen}
          template={editingTemplate}
          onSuccess={handleModalClose}
        />

        <DeleteTemplateModal
          open={!!deletingTemplate}
          onOpenChange={(open: boolean) => { if (!open) setDeletingTemplate(null) }}
          template={deletingTemplate}
          onConfirm={handleDeleteConfirm}
          isDeleting={isDeleting}
        />

        <ApplyTemplateModal
          open={!!applyingTemplate}
          onOpenChange={(open: boolean) => { if (!open) setApplyingTemplate(null) }}
          template={applyingTemplate}
          onSuccess={() => {
            setApplyingTemplate(null)
            loadTemplates()
          }}
        />

        <AppliedCustomersModal
          open={!!viewingAppliedTemplate}
          onOpenChange={(open: boolean) => { if (!open) setViewingAppliedTemplate(null) }}
          template={viewingAppliedTemplate}
        />
      </div>
    </FloatingLayout>
  )
}
