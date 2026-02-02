import { useState, useEffect } from 'react'
import FloatingLayout from '@/components/layout/FloatingLayout'
import { logger } from '@/lib/logger'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuLabel,
  DropdownMenuSeparator,
  DropdownMenuCheckboxItem,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu'
import {
  Plus,
  Search,
  Filter,
  LayoutGrid,
  List
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
import { Pagination } from '@/pages/customers/components/Pagination'
import { Tabs, TabsList, TabsTrigger } from '@/components/ui/tabs'
import { TemplateGrid } from './components/TemplateGrid'
import { TemplateTable } from './components/TemplateTable'

export default function TemplateList() {
  const toast = useToast()
  const [templates, setTemplates] = useState<ReportTemplate[]>([])
  const [loading, setLoading] = useState(true)
  const [searchQuery, setSearchQuery] = useState('')
  const [statusFilter, setStatusFilter] = useState<string>('all')
  const [reportTypeFilter, setReportTypeFilter] = useState<string>('all')
  const [view, setView] = useState<'grid' | 'table'>('grid')
  const [currentPage, setCurrentPage] = useState(1)
  const [perPage, setPerPage] = useState(9)
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
      const response = await templateApi.list()
      setTemplates(response.data)
    } catch (error) {
      toast.error('Failed to load templates')
      logger.error('Failed to load templates', error as Error)
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

      const schedulesDeleted = response.data?.schedules_deleted || 0
      if (schedulesDeleted > 0) {
        toast.success(`Template deleted successfully. ${schedulesDeleted} schedule${schedulesDeleted !== 1 ? 's' : ''} also removed.`)
      } else {
        toast.success('Template deleted successfully')
      }

      setDeletingTemplate(null)
      loadTemplates()
    } catch (error) {
      toast.error('Failed to delete template')
      logger.error('Failed to delete template', error as Error)
    } finally {
      setIsDeleting(false)
    }
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

  const totalPages = Math.ceil(filteredTemplates.length / perPage)
  const startIndex = (currentPage - 1) * perPage
  const endIndex = startIndex + perPage
  const paginatedTemplates = filteredTemplates.slice(startIndex, endIndex)

  useEffect(() => {
    setPerPage(view === 'grid' ? 9 : 10)
    setCurrentPage(1)
  }, [view])




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

          <div className="flex flex-col sm:flex-row gap-3">
            <div className="hidden lg:flex items-center text-sm text-muted-foreground whitespace-nowrap px-3 h-10 border rounded-md bg-white dark:bg-slate-900">
              {filteredTemplates.length} template{filteredTemplates.length !== 1 ? 's' : ''}
            </div>

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

            <Tabs value={view} onValueChange={(v) => setView(v as 'grid' | 'table')} className="w-full sm:w-auto">
              <TabsList className="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 p-1 h-10 w-full justify-start">
                <TabsTrigger value="grid" className="data-[state=active]:bg-primary data-[state=active]:text-primary-foreground data-[state=active]:shadow-sm px-3 rounded-md flex-1 sm:flex-initial">
                  <LayoutGrid className="h-4 w-4" />
                  <span className="ml-2">Grid</span>
                </TabsTrigger>
                <TabsTrigger value="table" className="data-[state=active]:bg-primary data-[state=active]:text-primary-foreground data-[state=active]:shadow-sm px-3 rounded-md flex-1 sm:flex-initial">
                  <List className="h-4 w-4" />
                  <span className="ml-2">Table</span>
                </TabsTrigger>
              </TabsList>
            </Tabs>

            <div className="flex-1 relative">
              <Search className="absolute left-3 top-1/2 transform -translate-y-1/2 h-4 w-4 text-muted-foreground" />
              <Input
                placeholder="Search by name or report type..."
                value={searchQuery}
                onChange={(e) => setSearchQuery(e.target.value)}
                className="pl-10 h-10"
              />
            </div>

            <Button onClick={() => setCreateModalOpen(true)} className="w-full sm:w-auto h-10">
              <Plus className="h-4 w-4 sm:mr-2" />
              <span className="hidden sm:inline">Create Template</span>
              <span className="sm:hidden">Create</span>
            </Button>
          </div>
        </div>

        {view === 'grid' ? (
          <TemplateGrid
            templates={paginatedTemplates}
            isLoading={loading}
            onEdit={(t) => {
              setEditingTemplate(t)
              setEditModalOpen(true)
            }}
            onDelete={handleDeleteClick}
            onApply={setApplyingTemplate}
            onPreview={setPreviewingTemplate}
            onViewApplied={setViewingAppliedTemplate}
          />
        ) : (
          <TemplateTable
            templates={paginatedTemplates}
            isLoading={loading}
            onEdit={(t) => {
              setEditingTemplate(t)
              setEditModalOpen(true)
            }}
            onDelete={handleDeleteClick}
            onApply={setApplyingTemplate}
            onPreview={setPreviewingTemplate}
            onViewApplied={setViewingAppliedTemplate}
          />
        )}

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
