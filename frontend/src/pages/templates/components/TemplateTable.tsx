import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from "@/components/ui/table";
import { Button } from "@/components/ui/button";
import { Skeleton } from "@/components/ui/skeleton";
import {
  FileText,
  Edit,
  Trash2,
  Users,
  Calendar,
  Globe,
  MoreVertical,
  Play,
  Eye,
  CheckCircle2,
  XCircle
} from "lucide-react";
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu";
import type { ReportTemplate } from "../types/template";
import { cn } from "@/lib/utils";

interface TemplateTableProps {
  templates: ReportTemplate[];
  isLoading: boolean;
  onEdit: (template: ReportTemplate) => void;
  onDelete: (template: ReportTemplate) => void;
  onApply: (template: ReportTemplate) => void;
  onPreview: (template: ReportTemplate) => void;
  onViewApplied: (template: ReportTemplate) => void;
}

export function TemplateTable({
  templates,
  isLoading,
  onEdit,
  onDelete,
  onApply,
  onPreview,
  onViewApplied,
}: TemplateTableProps) {
  if (isLoading) {
    return (
      <div className="border rounded-xl overflow-hidden bg-card">
        <Table>
          <TableHeader>
            <TableRow className="bg-muted/50">
              <TableHead className="w-[300px]">Template Name</TableHead>
              <TableHead>Type</TableHead>
              <TableHead>Frequency</TableHead>
              <TableHead>Status</TableHead>
              <TableHead>Applied To</TableHead>
              <TableHead className="text-right">Actions</TableHead>
            </TableRow>
          </TableHeader>
          <TableBody>
            {[1, 2, 3, 4, 5].map((i) => (
              <TableRow key={i}>
                <TableCell>
                  <div className="flex items-center gap-3">
                    <Skeleton className="h-10 w-10 rounded-lg" />
                    <div className="space-y-2">
                      <Skeleton className="h-4 w-32" />
                      <Skeleton className="h-3 w-24" />
                    </div>
                  </div>
                </TableCell>
                <TableCell><Skeleton className="h-4 w-24" /></TableCell>
                <TableCell><Skeleton className="h-4 w-20" /></TableCell>
                <TableCell><Skeleton className="h-6 w-16 rounded-full" /></TableCell>
                <TableCell><Skeleton className="h-4 w-12" /></TableCell>
                <TableCell className="text-right"><Skeleton className="h-8 w-8 ml-auto rounded-md" /></TableCell>
              </TableRow>
            ))}
          </TableBody>
        </Table>
      </div>
    );
  }

  if (templates.length === 0) {
    return (
      <div className="border rounded-xl p-12 text-center bg-card">
        <FileText className="h-12 w-12 text-muted-foreground mx-auto mb-4" />
        <p className="text-lg font-medium">No Templates Found</p>
        <p className="text-sm text-muted-foreground mb-6">
          Create report templates to automate your client reporting
        </p>
      </div>
    );
  }

  return (
    <div className="border rounded-xl overflow-hidden bg-white dark:bg-slate-900 shadow-sm">
      <Table>
        <TableHeader>
          <TableRow className="bg-muted/50 hover:bg-muted/50">
            <TableHead className="w-[300px] font-semibold text-foreground">Template Name</TableHead>
            <TableHead className="font-semibold text-foreground">Type</TableHead>
            <TableHead className="font-semibold text-foreground">Frequency</TableHead>
            <TableHead className="font-semibold text-foreground">Status</TableHead>
            <TableHead className="font-semibold text-foreground text-center">Applied To</TableHead>
            <TableHead className="text-right font-semibold text-foreground">Actions</TableHead>
          </TableRow>
        </TableHeader>
        <TableBody>
          {templates.map((template) => (
            <TableRow key={template.template_id} className="transition-colors">
              <TableCell>
                <div className="flex items-center gap-3">
                  <div className={cn(
                    "rounded-lg p-2 flex-shrink-0",
                    template.customer_id ? "bg-primary/10" : "bg-purple-500/10"
                  )}>
                    {template.customer_id ? (
                      <FileText className="h-5 w-5 text-primary" />
                    ) : (
                      <Globe className="h-5 w-5 text-purple-600" />
                    )}
                  </div>
                  <div className="min-w-0">
                    <div className="font-medium text-foreground truncate max-w-[200px]" title={template.template_name}>
                      {template.template_name}
                    </div>
                    <div className="text-xs text-muted-foreground truncate">
                      {template.customer ? template.customer.canonical_name : 'Global Template'}
                    </div>
                  </div>
                </div>
              </TableCell>
              <TableCell>
                <span className="text-sm text-muted-foreground capitalize">
                  {template.report_type.replace(/_/g, ' ')}
                </span>
              </TableCell>
              <TableCell>
                <div className="flex items-center gap-2 text-sm text-muted-foreground">
                  <Calendar className="h-3.5 w-3.5" />
                  <span className="capitalize">{template.frequency}</span>
                </div>
              </TableCell>
              <TableCell>
                <div className="flex items-center gap-2">
                  {template.active ? (
                    <span className="inline-flex items-center gap-1.5 text-green-600 font-medium text-xs">
                      <CheckCircle2 className="h-3.5 w-3.5" />
                      Active
                    </span>
                  ) : (
                    <span className="inline-flex items-center gap-1.5 text-muted-foreground font-medium text-xs">
                      <XCircle className="h-3.5 w-3.5" />
                      Inactive
                    </span>
                  )}
                </div>
              </TableCell>
              <TableCell className="text-center">
                <Button
                  variant="ghost"
                  size="sm"
                  className="h-7 px-2 text-xs font-medium text-primary hover:text-primary hover:bg-primary/5"
                  onClick={() => onViewApplied(template)}
                >
                  <Users className="h-3.5 w-3.5 mr-1" />
                  {template.schedules_count || 0}
                </Button>
              </TableCell>
              <TableCell className="text-right">
                <div className="flex items-center justify-end gap-2">
                  <Button
                    size="sm"
                    variant="default"
                    className="h-8 px-3"
                    onClick={() => onApply(template)}
                  >
                    <Play className="h-3.5 w-3.5 mr-1" />
                    Apply
                  </Button>
                  
                  <DropdownMenu>
                    <DropdownMenuTrigger asChild>
                      <Button variant="outline" size="sm" className="h-8 w-8 p-0">
                        <MoreVertical className="h-4 w-4" />
                      </Button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="end">
                      <DropdownMenuItem onClick={() => onPreview(template)}>
                        <Eye className="mr-2 h-4 w-4" />
                        Preview Report
                      </DropdownMenuItem>
                      <DropdownMenuItem onClick={() => onEdit(template)}>
                        <Edit className="mr-2 h-4 w-4" />
                        Edit Template
                      </DropdownMenuItem>
                      <DropdownMenuSeparator />
                      <DropdownMenuItem
                        className="text-destructive focus:text-destructive"
                        onClick={() => onDelete(template)}
                      >
                        <Trash2 className="mr-2 h-4 w-4" />
                        Delete Template
                      </DropdownMenuItem>
                    </DropdownMenuContent>
                  </DropdownMenu>
                </div>
              </TableCell>
            </TableRow>
          ))}
        </TableBody>
      </Table>
    </div>
  );
}
