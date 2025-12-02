import type { IntegrationPreview as IntegrationPreviewType } from '@/types/displayTemplate'
import { DynamicSection } from '@/components/dynamic/DynamicSection'

interface PreviewContentProps {
  preview: IntegrationPreviewType
  onSelectionChange: (sectionId: string, selectedIds: string[]) => void
}

export function PreviewContent({ preview, onSelectionChange }: PreviewContentProps) {
  return (
    <div className="space-y-6">
      {preview.template.sections.map((section) => (
        <DynamicSection
          key={section.id}
          section={section}
          data={preview.preview.data}
          onSelectionChange={onSelectionChange}
        />
      ))}
    </div>
  )
}
