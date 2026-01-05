import { useState, useEffect } from 'react'
import FloatingLayout from '@/components/layout/FloatingLayout'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Badge } from '@/components/ui/badge'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Spinner } from '@/components/ui/spinner'
import { 
  FileText, 
  Plus, 
  Search, 
  Edit, 
  Trash2, 
  Eye,
  Clock,
  Calendar,
  User
} from 'lucide-react'
import { templateApi } from '@/lib/api/templates'
import type { ReportTemplate } from './types/template'
import { CreateTemplateModal } from '@/pages/templates/modals/CreateTemplateModal'
import { DeleteTemplateModal } from '@/pages/templates/modals/DeleteTemplateModal'
import { useToast } from '@/components/ui/Toast'

export default function TemplateList() {
  const toast = useToast()
  const [templates, setTemplates] = useState<ReportTemplate[]>([])
  const [loading, setLoading] = useState(true)
  const [searchQuery, setSearchQuery] = useState('')
  const [createModalOpen, setCreateModalOpen] = useState(false)
  const [editingTemplate, setEditingTemplate] = useState<ReportTemplate | null>(null)
  const [deletingTemplate, setDeletingTemplate] = useState<ReportTemplate | null>(null)
  const [isDeleting, setIsDeleting] = useState(false)

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
      await templateApi.delete(deletingTemplate.template_id)
      toast.success('Template deleted successfully')
      setDeletingTemplate(null)
      loadTemplates()
    } catch (error) {
      toast.error('Failed to delete template')
      console.error(error)
    } finally {
      setIsDeleting(false)
    }
  }

  const handleEdit = (template: ReportTemplate) => {
    setEditingTemplate(template)
    setCreateModalOpen(true)
  }

  const handleView = (template: ReportTemplate) => {
    // TODO: Navigate to template detail view showing schedules and generated reports
    console.log('View template:', template)
    toast.info('Template detail view coming soon')
  }

  const handleModalClose = () => {
    setCreateModalOpen(false)
    setEditingTemplate(null)
    loadTemplates()
  }

  const filteredTemplates = (templates || []).filter(template =>
    template.template_name.toLowerCase().includes(searchQuery.toLowerCase()) ||
    template.report_type.toLowerCase().includes(searchQuery.toLowerCase()) ||
    template.description?.toLowerCase().includes(searchQuery.toLowerCase())
  )

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
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-3xl font-bold tracking-tight">Report Templates</h1>
          <p className="text-muted-foreground mt-1">
            Create and manage customer-specific report templates
          </p>
        </div>
        <Button onClick={() => setCreateModalOpen(true)}>
          <Plus className="mr-2 h-4 w-4" />
          Create Template
        </Button>
      </div>

      <div className="flex items-center gap-4">
        <div className="relative flex-1 max-w-sm">
          <Search className="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground" />
          <Input
            placeholder="Search templates..."
            value={searchQuery}
            onChange={(e) => setSearchQuery(e.target.value)}
            className="pl-9"
          />
        </div>
        <div className="text-sm text-muted-foreground">
          {filteredTemplates.length} template{filteredTemplates.length !== 1 ? 's' : ''}
        </div>
      </div>

      {filteredTemplates.length === 0 ? (
        <Card>
          <CardContent className="flex flex-col items-center justify-center py-12">
            <FileText className="h-12 w-12 text-muted-foreground mb-4" />
            <h3 className="text-lg font-semibold mb-2">No templates found</h3>
            <p className="text-sm text-muted-foreground mb-4">
              {searchQuery ? 'Try adjusting your search' : 'Get started by creating your first template'}
            </p>
            {!searchQuery && (
              <Button onClick={() => setCreateModalOpen(true)}>
                <Plus className="mr-2 h-4 w-4" />
                Create Template
              </Button>
            )}
          </CardContent>
        </Card>
      ) : (
        <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
          {filteredTemplates.map((template) => (
            <Card key={template.template_id} className="hover:shadow-md transition-shadow">
              <CardHeader>
                <div className="flex items-start justify-between">
                  <div className="flex-1">
                    <CardTitle className="text-lg">{template.template_name}</CardTitle>
                    {template.description && (
                      <CardDescription className="mt-1">{template.description}</CardDescription>
                    )}
                  </div>
                  {!template.active && (
                    <Badge variant="secondary">Inactive</Badge>
                  )}
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
                    variant="outline"
                    size="sm"
                    className="flex-1"
                    onClick={() => handleView(template)}
                  >
                    <Eye className="mr-2 h-4 w-4" />
                    View
                  </Button>
                  <Button
                    variant="outline"
                    size="sm"
                    onClick={() => handleEdit(template)}
                  >
                    <Edit className="h-4 w-4" />
                  </Button>
                  <Button
                    variant="outline"
                    size="sm"
                    onClick={() => handleDeleteClick(template)}
                  >
                    <Trash2 className="h-4 w-4" />
                  </Button>
                </div>
              </CardContent>
            </Card>
          ))}
        </div>
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
      </div>
    </FloatingLayout>
  )
}
