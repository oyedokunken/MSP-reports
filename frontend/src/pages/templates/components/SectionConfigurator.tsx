import type { ReportSection } from '../types/template'
import { Checkbox } from '@/components/ui/checkbox'
import { Label } from '@/components/ui/label'
import { Info } from 'lucide-react'
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip'

interface SectionConfiguratorProps {
  sections: ReportSection[]
  selectedSections: string[]
  onSectionsChange: (sections: string[]) => void
}

export function SectionConfigurator({
  sections,
  selectedSections,
  onSectionsChange,
}: SectionConfiguratorProps) {
  const handleToggle = (sectionId: string) => {
    if (selectedSections.includes(sectionId)) {
      onSectionsChange(selectedSections.filter(id => id !== sectionId))
    } else {
      onSectionsChange([...selectedSections, sectionId])
    }
  }

  return (
    <div className="space-y-3">
      <label className="text-sm font-medium">Report Sections</label>
      <div className="space-y-2 border rounded-lg p-4">
        {sections.length > 0 ? (
          sections.map((section) => (
            <div key={section.id} className="flex items-start space-x-3 py-2">
              <Checkbox
                id={`section-${section.id}`}
                checked={selectedSections.includes(section.id)}
                onCheckedChange={() => handleToggle(section.id)}
              />
              <div className="flex-1">
                <Label
                  htmlFor={`section-${section.id}`}
                  className="text-sm font-medium cursor-pointer flex items-center gap-2"
                >
                  {section.label}
                  {section.description && (
                    <TooltipProvider>
                      <Tooltip>
                        <TooltipTrigger asChild>
                          <Info className="h-3 w-3 text-muted-foreground cursor-help" />
                        </TooltipTrigger>
                        <TooltipContent>
                          <p className="max-w-xs text-xs">{section.description}</p>
                        </TooltipContent>
                      </Tooltip>
                    </TooltipProvider>
                  )}
                </Label>
                {section.requires && (
                  <p className="text-xs text-muted-foreground mt-1">
                    Requires: {section.requires}
                  </p>
                )}
              </div>
            </div>
          ))
        ) : (
          <div className="text-center py-4 text-muted-foreground italic">
            No sections available for this report type.
          </div>
        )}
      </div>
    </div>
  )
}
