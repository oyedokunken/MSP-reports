import { useState, useEffect } from 'react'
import FloatingLayout from '@/components/layout/FloatingLayout'
import { logger } from '@/lib/logger'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Badge } from '@/components/ui/badge'
import { Card, CardContent } from '@/components/ui/card'
import { Skeleton } from '@/components/ui/skeleton'
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
import { EditTemplateModal } from '@/pages/templates/modals/EditTemplateModal'
import { DeleteTemplateModal } from '@/pages/templates/modals/DeleteTemplateModal'
import { ApplyTemplateModal } from '@/pages/templates/modals/ApplyTemplateModal'
import { AppliedCustomersModal } from '@/pages/templates/modals/AppliedCustomersModal'
import { TemplatePreviewModal } from './modals/TemplatePreviewModal'
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
  const [editModalOpen, setEditModalOpen] = useState(false)
  const [editingTemplate, setEditingTemplate] = useState<ReportTemplate | null>(null)
  const [deletingTemplate, setDeletingTemplate] = useState<ReportTemplate | null>(null)
  const [isDeleting, setIsDeleting] = useState(false)
  const [applyingTemplate, setApplyingTemplate] = useState<ReportTemplate | null>(null)
  const [viewingAppliedTemplate, setViewingAppliedTemplate] = useState<ReportTemplate | null>(null)
  const [previewingTemplate, setPreviewingTemplate] = useState<ReportTemplate | null>(null)

  useEffect(() => {
    loadTemplates()
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [])

  const loadTemplates = async () => {
    try {
      setLoading(true)
      const data = await templateApi.list()
      setTemplates(data)
    } catch (error) {
      toast.error('Failed to load templates')
      logger.error(error)
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
      logger.error(error)
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
    setEditModalOpen(true)
  }

  const handleModalClose = () => {
    setCreateModalOpen(false)
    setEditingTemplate(null)
    loadTemplates()
  }

  const handleEditModalClose = () => {
    setEditModalOpen(false)
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


  return (
    <FloatingLayout>
      <div className="space-y-6">
        <div className="space-y-4">
          <div>
            <h1 className="text-2xl md:text-3xl font-bold tracking-tight">Report Templates</h1>
            <p className="text-muted-foreground mt-1 md:mt-2 text-sm md:text-base">
              Create and manage customer-specific report templates
            </p>
          </div>

          {/* Filters and Search */}
          <div className="flex flex-col sm:flex-row gap-3">
            {/* Count - Hidden on mobile */}
            <div className="hidden sm:flex items-center text-sm text-muted-foreground whitespace-nowrap px-3 h-10 border rounded-md">
              {filteredTemplates.length} template{filteredTemplates.length !== 1 ? 's' : ''}
            </div>

            {/* Filter Dropdown */}
            <DropdownMenu>
              <DropdownMenuTrigger asChild>
                <Button variant="outline" className="w-full sm:w-auto h-10 border">
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

            {/* Create Button */}
            <Button onClick={() => setCreateModalOpen(true)} className="w-full sm:w-auto h-10">
              <Plus className="h-4 w-4 sm:mr-2" />
              <span className="hidden sm:inline">Create Template</span>
              <span className="sm:hidden">Create</span>
            </Button>
          </div>
        </div>

        {loading ? (
          <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
            {[1, 2, 3, 4, 5, 6, 7, 8].map((i) => (
              <Card key={i} className="relative overflow-hidden flex flex-col p-0">
                {/* Header */}
                <div className="pt-3 px-4 pb-2 space-y-2">
                  <div className="flex items-start justify-between gap-2">
                    <Skeleton className="h-5 w-3/4" />
                    <Skeleton className="h-5 w-16" />
                  </div>
                  <Skeleton className="h-4 w-full" />
                  <Skeleton className="h-4 w-2/3" />
                </div>

                {/* Content */}
                <CardContent className="px-4 pb-4 pt-0 space-y-3 flex-1 flex flex-col">
                  {/* Metadata */}
                  <div className="space-y-2 text-sm flex-1">
                    <div className="flex items-center gap-2">
                      <Skeleton className="h-3.5 w-3.5 rounded" />
                      <Skeleton className="h-3 w-32" />
                    </div>
                    <div className="flex items-center gap-2">
                      <Skeleton className="h-3.5 w-3.5 rounded" />
                      <Skeleton className="h-3 w-24" />
                    </div>
                    <div className="flex items-center gap-2">
                      <Skeleton className="h-3.5 w-3.5 rounded" />
                      <Skeleton className="h-3 w-28" />
                    </div>
                  </div>

                  {/* Actions */}
                  <div className="flex gap-1.5 pt-3">
                    <Skeleton className="flex-1 h-8" />
                    <Skeleton className="h-8 w-8" />
                    <Skeleton className="h-8 w-8" />
                    <Skeleton className="h-8 w-8" />
                  </div>
                </CardContent>
              </Card>
            ))}
          </div>
        ) : paginatedTemplates.length === 0 && filteredTemplates.length === 0 ? (
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
          <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
            {paginatedTemplates.map((template) => (
              <Card
                key={template.template_id}
                className="group relative overflow-hidden hover:shadow-lg hover:scale-[1.02] hover:border-primary/50 transition-all duration-200 border-border/50 flex flex-col p-0 cursor-pointer"
                onClick={() => setPreviewingTemplate(template)}
              >
                {/* Top Accent Bar */}
                <div className={`absolute top-0 left-0 right-0 h-1 ${template.active ? 'bg-primary' : 'bg-muted'}`} />

                {/* Header */}
                <div className="pt-3 px-4 pb-2 space-y-2">
                  <div className="flex items-start justify-between gap-2">
                    <h3 className="font-semibold text-base line-clamp-1 flex-1">
                      {template.template_name}
                    </h3>
                    <div className="flex gap-1 flex-shrink-0">
                      {!template.customer_id && (
                        <Badge variant="default" className="bg-primary/10 text-primary border-primary/20 text-xs px-1.5 py-0.5">
                          <Globe className="h-3 w-3 mr-0.5" />
                          Global
                        </Badge>
                      )}
                      {!template.active && (
                        <Badge variant="secondary" className="text-xs px-1.5 py-0.5">Inactive</Badge>
                      )}
                    </div>
                  </div>

                  {template.description && (
                    <p className="text-sm text-muted-foreground line-clamp-2">
                      {template.description}
                    </p>
                  )}
                </div>

                {/* Content */}
                <CardContent className="px-4 pb-4 pt-0 space-y-3 flex-1 flex flex-col">
                  {/* Metadata */}
                  <div className="space-y-2 text-sm flex-1">
                    <div className="flex items-center gap-2 text-muted-foreground">
                      <FileText className="h-3.5 w-3.5 flex-shrink-0" />
                      <span className="truncate text-xs">
                        {template.report_type.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase())}
                      </span>
                    </div>

                    <div className="flex items-center gap-2 text-muted-foreground">
                      <Calendar className="h-3.5 w-3.5 flex-shrink-0" />
                      <span className="text-xs">{getFrequencyLabel(template.frequency)}</span>
                    </div>

                    {template.customer && (
                      <div className="flex items-center gap-2 text-muted-foreground">
                        <User className="h-3.5 w-3.5 flex-shrink-0" />
                        <span className="truncate text-xs">{template.customer.canonical_name}</span>
                      </div>
                    )}

                    {template.time_of_day && (
                      <div className="flex items-center gap-2 text-muted-foreground">
                        <Clock className="h-3.5 w-3.5 flex-shrink-0" />
                        <span className="text-xs">{template.time_of_day}</span>
                      </div>
                    )}

                    {template.included_sections && template.included_sections.length > 0 && (
                      <div className="flex items-center gap-2 text-muted-foreground">
                        <FileText className="h-3.5 w-3.5 flex-shrink-0" />
                        <span className="text-xs">{template.included_sections.length} sections</span>
                      </div>
                    )}
                  </div>

                  {/* Actions */}
                  <div className="flex gap-1.5 pt-3 border-t border-border/50" onClick={(e) => e.stopPropagation()}>
                    <Button
                      size="sm"
                      className="flex-1 h-8 text-xs"
                      onClick={(e) => {
                        e.stopPropagation()
                        handleViewReports(template)
                      }}
                    >
                      {template.customer_id ? (
                        <>
                          <Eye className="h-3.5 w-3.5 sm:mr-1.5" />
                          <span className="hidden sm:inline">Configure Reports</span>
                        </>
                      ) : (
                        <>
                          <Users className="h-3.5 w-3.5 sm:mr-1.5" />
                          <span className="hidden sm:inline">Apply</span>
                        </>
                      )}
                    </Button>

                    <div className="flex gap-1">
                      {!template.customer_id && (
                        <Button
                          variant="outline"
                          size="sm"
                          className="h-8 w-8 p-0"
                          onClick={(e) => {
                            e.stopPropagation()
                            setViewingAppliedTemplate(template)
                          }}
                          title="View Applied Customers"
                        >
                          <Eye className="h-3.5 w-3.5" />
                        </Button>
                      )}
                      <Button
                        variant="outline"
                        size="sm"
                        className="h-8 w-8 p-0"
                        onClick={(e) => {
                          e.stopPropagation()
                          handleViewSchedule(template)
                        }}
                        title="Edit Template"
                      >
                        <Settings className="h-3.5 w-3.5" />
                      </Button>
                      <Button
                        variant="outline"
                        size="sm"
                        className="h-8 w-8 p-0 hover:bg-destructive hover:text-destructive-foreground hover:border-destructive"
                        onClick={(e) => {
                          e.stopPropagation()
                          handleDeleteClick(template)
                        }}
                        title="Delete Template"
                      >
                        <Trash2 className="h-3.5 w-3.5" />
                      </Button>
                    </div>
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
          onSuccess={handleModalClose}
        />

        <EditTemplateModal
          open={editModalOpen}
          onOpenChange={setEditModalOpen}
          template={editingTemplate}
          onSuccess={handleEditModalClose}
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

        <TemplatePreviewModal
          open={!!previewingTemplate}
          onClose={() => setPreviewingTemplate(null)}
          template={previewingTemplate}
          onEdit={() => {
            setEditingTemplate(previewingTemplate)
            setPreviewingTemplate(null)
            setEditModalOpen(true)
          }}
        />
      </div>
    </FloatingLayout>
  )
}
