import type { ReportTypeDefinition } from '../types/template'
import { Badge } from '@/components/ui/badge'
import { FileText, Info } from 'lucide-react'
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip'

interface ReportTypeSelectorProps {
  reportTypes: ReportTypeDefinition[]
  selectedType: string | null
  onSelect: (type: string) => void
}

export function ReportTypeSelector({
  reportTypes,
  selectedType,
  onSelect,
}: ReportTypeSelectorProps) {
  if (reportTypes.length === 0) {
    return (
      <div className="text-center py-8 text-muted-foreground">
        <FileText className="h-12 w-12 mx-auto mb-2 opacity-50" />
        <p>No report types available for this customer</p>
        <p className="text-sm mt-1">Customer needs active integrations</p>
      </div>
    )
  }

  return (
    <div className="space-y-2">
      <label className="text-sm font-medium">Select Report Type</label>
      <div className="grid gap-2">
        {reportTypes.map((reportType) => {
          const isSelected = selectedType === reportType.type

          return (
            <button
              key={reportType.type}
              onClick={() => onSelect(reportType.type)}
              className={`
                p-4 rounded-lg border text-left transition-colors
                ${isSelected 
                  ? 'border-primary bg-primary/5' 
                  : 'border-border hover:border-primary/50'
                }
              `}
            >
              <div className="flex items-start justify-between">
                <div className="flex-1">
                  <div className="font-medium flex items-center gap-2">
                    {reportType.name}
                    <TooltipProvider>
                      <Tooltip>
                        <TooltipTrigger asChild>
                          <Info className="h-4 w-4 text-muted-foreground cursor-help" />
                        </TooltipTrigger>
                        <TooltipContent>
                          <p className="max-w-xs">{reportType.description}</p>
                        </TooltipContent>
                      </Tooltip>
                    </TooltipProvider>
                  </div>
                  <div className="flex gap-1 mt-2 flex-wrap">
                    {reportType.required_integrations.map((integration) => (
                      <Badge key={integration} variant="outline" className="text-xs">
                        Requires: {integration}
                      </Badge>
                    ))}
                  </div>
                </div>
                {isSelected && (
                  <div className="h-5 w-5 rounded-full bg-primary flex items-center justify-center flex-shrink-0">
                    <div className="h-2 w-2 rounded-full bg-white" />
                  </div>
                )}
              </div>
            </button>
          )
        })}
      </div>
    </div>
  )
}
