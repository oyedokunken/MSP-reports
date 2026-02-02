import {
  FileText,
  Users,
  Calendar,
  Globe,
  Edit,
  Trash2,
  Eye,
  Clock,
  User
} from "lucide-react";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent } from "@/components/ui/card";
import { Skeleton } from "@/components/ui/skeleton";
import { AnimatedCard } from "@/components/ui/AnimatedCard";
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
      <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
        {[1, 2, 3, 4, 5, 6, 7, 8].map((i) => (
          <Card key={i} className="relative overflow-hidden flex flex-col p-0">
            <div className="pt-3 px-4 pb-2 space-y-2">
              <div className="flex items-start justify-between gap-2">
                <Skeleton className="h-5 w-3/4" />
                <Skeleton className="h-5 w-16" />
              </div>
              <Skeleton className="h-4 w-full" />
              <Skeleton className="h-4 w-2/3" />
            </div>

            <CardContent className="px-4 pb-4 pt-0 space-y-3 flex-1 flex flex-col">
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
    );
  }

  if (templates.length === 0) {
    return (
      <Card>
        <CardContent className="flex flex-col items-center justify-center py-12">
          <FileText className="h-12 w-12 text-muted-foreground mb-4" />
          <h3 className="text-lg font-semibold mb-2">No templates found</h3>
          <p className="text-sm text-muted-foreground mb-4">
            Try adjusting your search or filters
          </p>
        </CardContent>
      </Card>
    );
  }

  return (
    <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
      {templates.map((template) => (
        <AnimatedCard
          key={template.template_id}
          className="group relative overflow-hidden flex flex-col p-0"
          onClick={() => onPreview(template)}
          hoverScale={true}
        >
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

          <CardContent className="px-4 pb-4 pt-0 space-y-3 flex-1 flex flex-col">
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

            <div className="flex gap-1.5 pt-3 border-t border-border/50" onClick={(e) => e.stopPropagation()}>
              <Button
                size="sm"
                className="flex-1 h-8 text-xs"
                onClick={(e) => {
                  e.stopPropagation()
                  onApply(template)
                }}
              >
                {template.customer_id ? (
                  <>
                    <Eye className="h-3.5 w-3.5 sm:mr-1.5" />
                    <span className="hidden sm:inline">Apply Reports</span>
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
                      onViewApplied(template)
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
                    onEdit(template)
                  }}
                  title="Edit Template"
                >
                  <Edit className="h-3.5 w-3.5" />
                </Button>
                <Button
                  variant="outline"
                  size="sm"
                  className="h-8 w-8 p-0 hover:bg-destructive hover:text-destructive-foreground hover:border-destructive"
                  onClick={(e) => {
                    e.stopPropagation()
                    onDelete(template)
                  }}
                  title="Delete Template"
                >
                  <Trash2 className="h-3.5 w-3.5" />
                </Button>
              </div>
            </div>
          </CardContent>
        </AnimatedCard>
      ))}
    </div>
  );
}
