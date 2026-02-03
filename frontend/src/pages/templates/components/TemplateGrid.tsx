import {
  FileText,
  Users,
  Globe,
  Edit,
  Trash2,
  Eye,
  User,
  Repeat,
  Layout
} from "lucide-react";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Skeleton } from "@/components/ui/skeleton";
import { AnimatedCard } from "@/components/ui/AnimatedCard";
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from "@/components/ui/tooltip";
import type { ReportTemplate } from "../types/template";

interface TemplateGridProps {
  templates: ReportTemplate[];
  isLoading: boolean;
  onEdit: (template: ReportTemplate) => void;
  onDelete: (template: ReportTemplate) => void;
  onApply: (template: ReportTemplate) => void;
  onPreview: (template: ReportTemplate) => void;
  onViewApplied: (template: ReportTemplate) => void;
}

export function TemplateGrid({
  templates,
  isLoading,
  onEdit,
  onDelete,
  onApply,
  onPreview,
  onViewApplied,
}: TemplateGridProps) {
  const getFrequencyLabel = (frequency: string) => {
    return frequency.charAt(0).toUpperCase() + frequency.slice(1).replace('-', ' ')
  }

  if (isLoading) {
    return (
      <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
        {[1, 2, 3, 4, 5, 6, 7, 8].map((i) => (
          <AnimatedCard key={i} className="p-6 h-full flex flex-col">
            <div className="flex items-start justify-between gap-3">
              <div className="flex items-center gap-3 min-w-0 flex-1">
                <Skeleton className="h-10 w-10 rounded-lg flex-shrink-0" />
                <div className="min-w-0 flex-1 space-y-2">
                  <Skeleton className="h-5 w-3/4" />
                  <Skeleton className="h-3 w-1/2" />
                </div>
              </div>
              <Skeleton className="h-5 w-16 flex-shrink-0" />
            </div>

            <div className="mt-6 space-y-3 flex-1">
              <div className="flex items-center gap-2">
                <Skeleton className="h-4 w-4 rounded" />
                <Skeleton className="h-4 w-32" />
              </div>
              <div className="flex items-center gap-2">
                <Skeleton className="h-4 w-4 rounded" />
                <Skeleton className="h-4 w-24" />
              </div>
              <div className="flex items-center gap-2">
                <Skeleton className="h-4 w-4 rounded" />
                <Skeleton className="h-4 w-28" />
              </div>
            </div>

            <div className="mt-4 border-t border-border/50 pt-4">
              <div className="flex gap-2">
                <Skeleton className="flex-1 h-9" />
                <Skeleton className="h-9 w-9" />
                <Skeleton className="h-9 w-9" />
              </div>
            </div>
          </AnimatedCard>
        ))}
      </div>
    );
  }

  if (templates.length === 0) {
    return (
      <AnimatedCard className="p-12">
        <div className="flex flex-col items-center justify-center">
          <FileText className="h-12 w-12 text-muted-foreground mb-4" />
          <h3 className="text-lg font-semibold mb-2">No templates found</h3>
          <p className="text-sm text-muted-foreground mb-4">
            Try adjusting your search or filters
          </p>
        </div>
      </AnimatedCard>
    );
  }

  return (
    <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
      {templates.map((template) => (
        <AnimatedCard
          key={template.template_id}
          className="group relative h-full flex flex-col p-6"
          onClick={() => onPreview(template)}
          hoverScale={true}
        >
          <div className="flex items-start justify-between gap-3">
            <div className="flex items-center gap-3 min-w-0 flex-1">
              <div className="rounded-lg bg-primary/10 p-2 flex-shrink-0">
                <FileText className="h-6 w-6 text-primary" />
              </div>
              <div className="min-w-0 flex-1">
                <h3 className="font-semibold text-foreground break-words line-clamp-1">
                  {template.template_name}
                </h3>
                <p className="text-xs text-muted-foreground truncate">
                  {template.report_type.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase())}
                </p>
              </div>
            </div>
            <div className="flex items-center gap-2 flex-shrink-0">
              {!template.customer_id && (
                <Badge variant="default" className="bg-primary/10 text-primary border-primary/20 text-xs px-1.5 py-0.5">
                  <Globe className="h-3 w-3 mr-0.5" />
                  Global
                </Badge>
              )}
              <Badge
                variant={template.active ? "default" : "secondary"}
                className={`flex-shrink-0 ${!template.active
                  ? "bg-purple-600 text-white hover:bg-purple-700"
                  : ""
                  }`}
              >
                {template.active ? "Active" : "Inactive"}
              </Badge>
            </div>
          </div>

          <div className="mt-6 space-y-3 flex-1">
            {template.description && (
              <p className="text-sm text-muted-foreground line-clamp-2 mb-2">
                {template.description}
              </p>
            )}

            <div className="flex items-center gap-2 text-sm">
              <Repeat className="h-4 w-4 text-muted-foreground flex-shrink-0" />
              <span className="text-foreground">
                {getFrequencyLabel(template.frequency)} {template.time_of_day ? `• ${template.time_of_day}` : ''}
              </span>
            </div>

            {template.customer && (
              <div className="flex items-center gap-2 text-sm">
                <User className="h-4 w-4 text-muted-foreground flex-shrink-0" />
                <span className="text-foreground truncate">{template.customer.canonical_name}</span>
              </div>
            )}

            {template.included_sections && template.included_sections.length > 0 && (
              <div className="flex items-center gap-2 text-sm">
                <Layout className="h-4 w-4 text-muted-foreground flex-shrink-0" />
                <span className="text-foreground">{template.included_sections.length} sections</span>
              </div>
            )}
          </div>

          <div className="mt-4 border-t border-border/50 pt-4" onClick={(e) => e.stopPropagation()}>
            <div className="flex gap-2">
              <Button
                variant="outline"
                className="flex-1 flex items-center justify-center gap-2 rounded-md border-border bg-background text-foreground hover:bg-muted font-medium transition-all"
                onClick={(e) => {
                  e.stopPropagation()
                  onApply(template)
                }}
              >
                {template.customer_id ? (
                  <>
                    <Eye className="h-4 w-4 text-primary" />
                    <span>Apply Reports</span>
                  </>
                ) : (
                  <>
                    <Users className="h-4 w-4 text-primary" />
                    <span>Apply</span>
                  </>
                )}
              </Button>

              <TooltipProvider>
                <div className="flex gap-2">
                  {!template.customer_id && (
                    <Tooltip>
                      <TooltipTrigger asChild>
                        <Button
                          variant="outline"
                          size="sm"
                          className="h-9 w-9 p-0 text-muted-foreground hover:text-foreground"
                          onClick={(e) => {
                            e.stopPropagation()
                            onViewApplied(template)
                          }}
                        >
                          <Eye className="h-4 w-4" />
                        </Button>
                      </TooltipTrigger>
                      <TooltipContent>View Applied Customers</TooltipContent>
                    </Tooltip>
                  )}
                  
                  <Tooltip>
                    <TooltipTrigger asChild>
                      <Button
                        variant="outline"
                        size="sm"
                        className="h-9 w-9 p-0 text-primary hover:text-primary hover:bg-primary/10 hover:border-primary/50"
                        onClick={(e) => {
                          e.stopPropagation()
                          onEdit(template)
                        }}
                      >
                        <Edit className="h-4 w-4" />
                      </Button>
                    </TooltipTrigger>
                    <TooltipContent>Edit Template</TooltipContent>
                  </Tooltip>

                  <Tooltip>
                    <TooltipTrigger asChild>
                      <Button
                        variant="outline"
                        size="sm"
                        className="h-9 w-9 p-0 text-destructive hover:text-destructive-foreground hover:bg-destructive hover:border-destructive"
                        onClick={(e) => {
                          e.stopPropagation()
                          onDelete(template)
                        }}
                      >
                        <Trash2 className="h-4 w-4" />
                      </Button>
                    </TooltipTrigger>
                    <TooltipContent>Delete Template</TooltipContent>
                  </Tooltip>
                </div>
              </TooltipProvider>
            </div>
          </div>
        </AnimatedCard>
      ))}
    </div>
  );
}
