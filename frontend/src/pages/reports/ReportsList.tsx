import { useEffect, useState } from 'react'
import { useSearchParams, useNavigate } from 'react-router-dom'
import FloatingLayout from '@/components/layout/FloatingLayout'
import { useToast } from '@/components/ui/Toast'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Badge } from '@/components/ui/badge'
import { Spinner } from '@/components/ui/spinner'
import { Input } from '@/components/ui/input'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { FileText, Download, CheckCircle2, XCircle, AlertCircle, RefreshCw, Clock, List, Search, Filter } from 'lucide-react'
import { api } from '@/lib/api'
import apiClient from '@/lib/api'

interface GeneratedReport {
  generated_report_id: number
  customer_id: number
  customer_name: string
  report_type: string
  generated_at: string
  status: string
  file_size: number
  delivered: boolean
  delivered_at: string | null
  delivery_method: string
  error_message: string | null
}

export default function ReportsList() {
  const toast = useToast()
  const navigate = useNavigate()
  const [searchParams] = useSearchParams()
  const customerId = searchParams.get('customer')
  
  const [isLoading, setIsLoading] = useState(true)
  const [reports, setReports] = useState<GeneratedReport[]>([])
  const [downloading, setDownloading] = useState<number | null>(null)
  const [customerName, setCustomerName] = useState<string>('')
  const [searchQuery, setSearchQuery] = useState('')
  const [statusFilter, setStatusFilter] = useState<string>('all')
  const [reportTypeFilter, setReportTypeFilter] = useState<string>('all')

  // ✅ Correctly wrapped in useEffect
  useEffect(() => {
    fetchReportHistory()
  }, [customerId])

  const fetchReportHistory = async () => {
    try {
      setIsLoading(true)
      const url = customerId 
        ? `/reports/history?customer_id=${customerId}`
        : '/reports/history'
      const response = await api.get<{ reports: GeneratedReport[] }>(url)
      const data = (response as any).data || response
      const reportsList = data.reports || []
      setReports(reportsList)
      
      // Set customer name from first report if filtering by customer
      if (customerId && reportsList.length > 0) {
        setCustomerName(reportsList[0].customer_name)
      }
    } catch (err: any) {
      toast.error('Failed to load report history', err.response?.data?.message || err.message)
    } finally {
      setIsLoading(false)
    }
  }

  // Filter reports based on search and filters
  const filteredReports = reports.filter(report => {
    // Search filter
    if (searchQuery && !report.customer_name.toLowerCase().includes(searchQuery.toLowerCase())) {
      return false
    }
    
    // Status filter
    if (statusFilter !== 'all' && report.status !== statusFilter) {
      return false
    }
    
    // Report type filter
    if (reportTypeFilter !== 'all' && report.report_type !== reportTypeFilter) {
      return false
    }
    
    return true
  })

  // Get unique report types for filter dropdown
  const uniqueReportTypes = Array.from(new Set(reports.map(r => r.report_type)))

  const handleDownload = async (reportId: number) => {
    try {
      setDownloading(reportId)
      
      // Use apiClient directly for blob downloads (bypass the api wrapper)
      const response = await apiClient.get(`/reports/${reportId}/download`, {
        responseType: 'blob',
      })

      const blob = response.data
      
      // Extract filename from Content-Disposition header or use default
      const contentDisposition = response.headers['content-disposition']
      let filename = `report_${reportId}.pdf`
      
      if (contentDisposition) {
        const filenameMatch = contentDisposition.match(/filename[^;=\n]*=((['"]).*?\2|[^;\n]*)/)
        if (filenameMatch && filenameMatch[1]) {
          filename = filenameMatch[1].replace(/['"]/g, '')
        }
      }
      
      const url = window.URL.createObjectURL(blob)
      const link = document.createElement('a')
      link.href = url
      link.setAttribute('download', filename)
      document.body.appendChild(link)
      link.click()
      link.remove()
      window.URL.revokeObjectURL(url)

      toast.success('Download Started', 'PDF report is downloading...')
    } catch (err: any) {
      toast.error('Download Failed', err.response?.data?.message || err.message)
    } finally {
      setDownloading(null)
    }
  }

  const formatDate = (dateString: string | null) => {
    if (!dateString) return 'Never'
    return new Date(dateString).toLocaleString('en-US', {
      year: 'numeric',
      month: 'short',
      day: 'numeric',
      hour: '2-digit',
      minute: '2-digit',
    })
  }

  const formatFileSize = (bytes: number) => {
    if (bytes < 1024) return `${bytes} B`
    if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(1)} KB`
    return `${(bytes / (1024 * 1024)).toFixed(1)} MB`
  }

  const getStatusBadge = (status: string) => {
    const variants: Record<
      string,
      { variant: 'default' | 'secondary' | 'destructive'; icon: any }
    > = {
      completed: { variant: 'default', icon: CheckCircle2 },
      pending: { variant: 'secondary', icon: Clock },
      generating: { variant: 'secondary', icon: RefreshCw },
      failed: { variant: 'destructive', icon: XCircle },
    }
    const config = variants[status] || { variant: 'secondary', icon: AlertCircle }
    const Icon = config.icon

    return (
      <Badge variant={config.variant} className="flex items-center gap-1 w-fit">
        <Icon className="h-3 w-3" />
        {status.charAt(0).toUpperCase() + status.slice(1)}
      </Badge>
    )
  }

  return (
    <FloatingLayout>
      <div className="space-y-6">
        {/* Header */}
        <div className="flex items-center justify-between">
          <div>
            <h1 className="text-3xl font-bold text-foreground">
              {customerId && customerName ? `${customerName} - Reports` : 'Generated Reports'}
            </h1>
            <p className="mt-2 text-muted-foreground">
              {customerId ? `View and download reports for ${customerName || 'this customer'}` : 'View and download generated reports'}
            </p>
          </div>
          {customerId && (
            <Button variant="outline" onClick={() => navigate('/reports')}>
              <List className="h-4 w-4 mr-2" />
              View All Reports
            </Button>
          )}
        </div>

        {/* Search and Filters */}
        <div className="flex flex-col md:flex-row gap-3">
          {/* Search */}
          <div className="flex-1 relative">
            <Search className="absolute left-3 top-1/2 transform -translate-y-1/2 h-4 w-4 text-muted-foreground" />
            <Input
              placeholder="Search by customer name..."
              value={searchQuery}
              onChange={(e) => setSearchQuery(e.target.value)}
              className="pl-10"
            />
          </div>
          
          {/* Status Filter */}
          <Select value={statusFilter} onValueChange={setStatusFilter}>
            <SelectTrigger className="w-full md:w-[180px]">
              <SelectValue placeholder="Status" />
            </SelectTrigger>
            <SelectContent>
              <SelectItem value="all">All Status</SelectItem>
              <SelectItem value="completed">Completed</SelectItem>
              <SelectItem value="pending">Pending</SelectItem>
              <SelectItem value="generating">Generating</SelectItem>
              <SelectItem value="failed">Failed</SelectItem>
            </SelectContent>
          </Select>
          
          {/* Report Type Filter */}
          <Select value={reportTypeFilter} onValueChange={setReportTypeFilter}>
            <SelectTrigger className="w-full md:w-[200px]">
              <SelectValue placeholder="Report Type" />
            </SelectTrigger>
            <SelectContent>
              <SelectItem value="all">All Report Types</SelectItem>
              {uniqueReportTypes.map(type => (
                <SelectItem key={type} value={type}>
                  {type.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase())}
                </SelectItem>
              ))}
            </SelectContent>
          </Select>
        </div>
        
        {/* Active Filters Display */}
        {(searchQuery || statusFilter !== 'all' || reportTypeFilter !== 'all') && (
          <div className="flex items-center gap-2 text-sm text-muted-foreground">
            <Filter className="h-4 w-4" />
            <span>Showing {filteredReports.length} of {reports.length} reports</span>
          </div>
        )}

        {/* Content */}
        {isLoading ? (
          <div className="flex items-center justify-center py-12">
            <Spinner className="h-8 w-8" />
          </div>
        ) : reports.length === 0 ? (
          <Card>
            <CardContent className="flex flex-col items-center justify-center py-12">
              <FileText className="h-12 w-12 text-muted-foreground mb-4" />
              <p className="text-lg font-medium">No Generated Reports</p>
              <p className="text-sm text-muted-foreground">
                Reports will appear here once generated
              </p>
            </CardContent>
          </Card>
        ) : filteredReports.length === 0 ? (
          <Card>
            <CardContent className="flex flex-col items-center justify-center py-12">
              <FileText className="h-12 w-12 text-muted-foreground mb-4" />
              <p className="text-lg font-medium">No Reports Match Filters</p>
              <p className="text-sm text-muted-foreground">
                Try adjusting your search or filters
              </p>
            </CardContent>
          </Card>
        ) : (
          <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            {filteredReports.map((report) => (
              <Card key={report.generated_report_id} className="flex flex-col">
                <CardHeader className="pb-3">
                  <div className="flex items-start justify-between gap-2">
                    <div className="min-w-0 flex-1">
                      <CardTitle className="text-base truncate">
                        {report.customer_name || 'Unknown Customer'}
                      </CardTitle>
                      <CardDescription className="text-xs mt-1 truncate">
                        {report.report_type || 'Custom Report'}
                      </CardDescription>
                    </div>
                    {getStatusBadge(report.status)}
                  </div>
                </CardHeader>
                <CardContent className="flex-1 space-y-3">
                  <div className="space-y-2 text-xs">
                    <div className="flex justify-between">
                      <span className="text-muted-foreground">Generated</span>
                      <span className="font-medium">{formatDate(report.generated_at)}</span>
                    </div>
                    <div className="flex justify-between">
                      <span className="text-muted-foreground">File Size</span>
                      <span className="font-medium">{formatFileSize(report.file_size)}</span>
                    </div>
                    <div className="flex justify-between">
                      <span className="text-muted-foreground">Delivery</span>
                      <span className="font-medium capitalize">
                        {report.delivery_method}
                      </span>
                    </div>
                    <div className="flex justify-between">
                      <span className="text-muted-foreground">Delivered</span>
                      <span className="font-medium">
                        {report.delivered ? 'Yes' : 'Not yet'}
                      </span>
                    </div>
                  </div>

                  {report.error_message && (
                    <div className="p-2 rounded bg-destructive/10 border border-destructive/50">
                      <p className="text-xs text-destructive font-medium line-clamp-2">
                        {report.error_message}
                      </p>
                    </div>
                  )}

                  <Button
                    size="sm"
                    className="w-full"
                    onClick={() => handleDownload(report.generated_report_id)}
                    disabled={
                      downloading === report.generated_report_id ||
                      report.status !== 'completed'
                    }
                  >
                    {downloading === report.generated_report_id ? (
                      <>
                        <RefreshCw className="h-4 w-4 mr-2 animate-spin" />
                        Downloading...
                      </>
                    ) : (
                      <>
                        <Download className="h-4 w-4 mr-2" />
                        Download
                      </>
                    )}
                  </Button>
                </CardContent>
              </Card>
            ))}
          </div>
        )}
      </div>
    </FloatingLayout>
  )
}
