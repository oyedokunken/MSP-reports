import { useState, useEffect, useCallback } from 'react'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Badge } from '@/components/ui/badge'
import {
  Dialog,
  DialogContent,
  DialogHeader,
  DialogTitle,
  DialogDescription,
  DialogFooter,
} from '@/components/ui/dialog'
import {
  RefreshCw,
  CheckCircle2,
  XCircle,
  AlertCircle,
  Database,
  Server,
  Wifi,
  Mail,
  Clock,
  Shield,
  Activity,
  Zap,
  HardDrive,
  Globe,
} from 'lucide-react'
import { cn } from '@/lib/utils'
import { api } from '@/lib/api'
import { logger } from '@/lib/logger'

interface ServiceStatus {
  id: string
  name: string
  description: string
  status: 'operational' | 'degraded' | 'outage' | 'checking'
  icon: React.ElementType
  lastChecked: Date | null
  responseTime?: number
  progress?: number
}

const initialServices: ServiceStatus[] = [
  { id: 'api', name: 'API Server', description: 'Core API endpoints', status: 'operational', icon: Server, lastChecked: null },
  { id: 'database', name: 'Database', description: 'PostgreSQL / TimescaleDB', status: 'operational', icon: Database, lastChecked: null },
  { id: 'redis', name: 'Cache & Queue', description: 'Redis cache and job queue', status: 'operational', icon: Zap, lastChecked: null },
  { id: 'auth', name: 'Authentication', description: 'Login and session management', status: 'operational', icon: Shield, lastChecked: null },
  { id: 'integrations', name: 'Integrations', description: 'External API connections', status: 'operational', icon: Wifi, lastChecked: null },
  { id: 'email', name: 'Email Delivery', description: 'SMTP and notifications', status: 'operational', icon: Mail, lastChecked: null },
  { id: 'scheduler', name: 'Task Scheduler', description: 'Cron jobs and scheduled tasks', status: 'operational', icon: Clock, lastChecked: null },
  { id: 'storage', name: 'File Storage', description: 'Report and asset storage', status: 'operational', icon: HardDrive, lastChecked: null },
  { id: 'reports', name: 'Report Engine', description: 'PDF generation service', status: 'operational', icon: Activity, lastChecked: null },
  { id: 'external', name: 'External APIs', description: 'Third-party service connectivity', status: 'operational', icon: Globe, lastChecked: null },
]

const statusConfig = {
  operational: {
    label: 'Operational',
    color: 'text-green-600',
    bgColor: 'bg-green-100 dark:bg-green-900/30',
    borderColor: 'border-green-500',
    icon: CheckCircle2,
  },
  degraded: {
    label: 'Degraded',
    color: 'text-yellow-600',
    bgColor: 'bg-yellow-100 dark:bg-yellow-900/30',
    borderColor: 'border-yellow-500',
    icon: AlertCircle,
  },
  outage: {
    label: 'Outage',
    color: 'text-red-600',
    bgColor: 'bg-red-100 dark:bg-red-900/30',
    borderColor: 'border-red-500',
    icon: XCircle,
  },
  checking: {
    label: 'Checking...',
    color: 'text-blue-600',
    bgColor: 'bg-blue-100 dark:bg-blue-900/30',
    borderColor: 'border-blue-500',
    icon: RefreshCw,
  },
}

export default function StatusPage() {
  const [services, setServices] = useState<ServiceStatus[]>(initialServices)
  const [isRefreshing, setIsRefreshing] = useState(false)
  const [showResultModal, setShowResultModal] = useState(false)
  const [lastFullCheck, setLastFullCheck] = useState<Date | null>(null)

  const checkService = useCallback(async (serviceId: string): Promise<'operational' | 'degraded' | 'outage'> => {
    const startTime = Date.now()
    
    try {
      switch (serviceId) {
        case 'api':
          await api.get('/health')
          break
        case 'database':
          await api.get('/health/database')
          break
        case 'redis':
          await api.get('/health/redis')
          break
        case 'auth':
          // Just check if we can reach auth endpoint
          await api.get('/auth/config')
          break
        case 'integrations':
          await api.get('/integrations')
          break
        case 'email':
          await api.get('/health/email')
          break
        case 'scheduler':
          await api.get('/health/scheduler')
          break
        case 'storage':
          await api.get('/health/storage')
          break
        case 'reports':
          await api.get('/health/reports')
          break
        case 'external':
          await api.get('/health/external')
          break
        default:
          await api.get('/health')
      }
      
      const responseTime = Date.now() - startTime
      // If response time > 2s, consider degraded
      if (responseTime > 2000) {
        return 'degraded'
      }
      return 'operational'
    } catch (error) {
      logger.error(`Health check failed for ${serviceId}`, error)
      return 'outage'
    }
  }, [])

  const runHealthChecks = useCallback(async () => {
    setIsRefreshing(true)
    
    // Set all services to checking state with progress 0
    setServices(prev => prev.map(s => ({ ...s, status: 'checking' as const, progress: 0 })))
    
    // Check each service sequentially with progress animation
    const updatedServices: ServiceStatus[] = []
    
    for (let i = 0; i < initialServices.length; i++) {
      const service = initialServices[i]
      
      // Update progress for current service
      setServices(prev => prev.map((s, idx) => ({
        ...s,
        progress: idx < i ? 100 : idx === i ? 0 : undefined,
        status: idx < i ? (updatedServices[idx]?.status || 'operational') : idx === i ? 'checking' : s.status,
      })))
      
      // Animate progress from 0 to 100
      for (let p = 0; p <= 100; p += 20) {
        await new Promise(resolve => setTimeout(resolve, 50))
        setServices(prev => prev.map((s, idx) => 
          idx === i ? { ...s, progress: p } : s
        ))
      }
      
      // Actually check the service
      const status = await checkService(service.id)
      updatedServices.push({
        ...service,
        status,
        lastChecked: new Date(),
        progress: 100,
      })
      
      // Update with final status
      setServices(prev => prev.map((s, idx) => 
        idx === i ? updatedServices[i] : s
      ))
    }
    
    setServices(updatedServices)
    setLastFullCheck(new Date())
    setIsRefreshing(false)
    setShowResultModal(true)
  }, [checkService])

  // Initial check on mount
  useEffect(() => {
    runHealthChecks()
  }, []) // eslint-disable-line react-hooks/exhaustive-deps

  const operationalCount = services.filter(s => s.status === 'operational').length
  const degradedCount = services.filter(s => s.status === 'degraded').length
  const outageCount = services.filter(s => s.status === 'outage').length
  const allOperational = operationalCount === services.length

  const overallStatus = outageCount > 0 ? 'outage' : degradedCount > 0 ? 'degraded' : 'operational'
  const overallConfig = statusConfig[overallStatus]
  const OverallIcon = overallConfig.icon

  return (
    <div className="space-y-6">
      {/* Header */}
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-2xl font-bold flex items-center gap-2">
            <Activity className="h-6 w-6 text-primary" />
            System Status
          </h1>
          <p className="text-sm text-muted-foreground mt-1">
            Real-time health monitoring for all platform services
          </p>
        </div>
        <Button
          onClick={runHealthChecks}
          disabled={isRefreshing}
          className="gap-2"
        >
          <RefreshCw className={cn("h-4 w-4", isRefreshing && "animate-spin")} />
          {isRefreshing ? 'Checking...' : 'Refresh'}
        </Button>
      </div>

      {/* Overall Status Banner */}
      <Card className={cn("border-l-4", overallConfig.borderColor)}>
        <CardContent className="py-4">
          <div className="flex items-center justify-between">
            <div className="flex items-center gap-3">
              <div className={cn("h-12 w-12 rounded-full flex items-center justify-center", overallConfig.bgColor)}>
                <OverallIcon className={cn("h-6 w-6", overallConfig.color)} />
              </div>
              <div>
                <h2 className={cn("text-lg font-semibold", overallConfig.color)}>
                  {allOperational ? 'All Systems Operational' : overallConfig.label}
                </h2>
                <p className="text-sm text-muted-foreground">
                  {lastFullCheck 
                    ? `Last checked: ${lastFullCheck.toLocaleTimeString()}`
                    : 'Checking services...'}
                </p>
              </div>
            </div>
            <div className="flex gap-4 text-center">
              <div>
                <div className="text-2xl font-bold text-green-600">{operationalCount}</div>
                <div className="text-[10px] text-muted-foreground uppercase">Operational</div>
              </div>
              <div>
                <div className="text-2xl font-bold text-yellow-600">{degradedCount}</div>
                <div className="text-[10px] text-muted-foreground uppercase">Degraded</div>
              </div>
              <div>
                <div className="text-2xl font-bold text-red-600">{outageCount}</div>
                <div className="text-[10px] text-muted-foreground uppercase">Outage</div>
              </div>
            </div>
          </div>
        </CardContent>
      </Card>

      {/* Services Grid */}
      <div className="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-5 gap-3">
          {services.map((service) => {
            const config = statusConfig[service.status]
            const StatusIcon = config.icon
            const ServiceIcon = service.icon

            return (
              <Card 
                key={service.id} 
                className={cn(
                  "relative overflow-hidden transition-all duration-300",
                  service.status === 'checking' && "ring-2 ring-blue-500/50"
                )}
              >
                {/* Progress bar for checking state */}
                {service.status === 'checking' && service.progress !== undefined && (
                  <div 
                    className="absolute bottom-0 left-0 h-1 bg-blue-500 transition-all duration-100"
                    style={{ width: `${service.progress}%` }}
                  />
                )}
                
                <CardHeader className="pb-2">
                  <div className="flex items-center justify-between">
                    <div className={cn("h-10 w-10 rounded-lg flex items-center justify-center", config.bgColor)}>
                      <ServiceIcon className={cn("h-5 w-5", config.color)} />
                    </div>
                    <StatusIcon className={cn(
                      "h-5 w-5",
                      config.color,
                      service.status === 'checking' && "animate-spin"
                    )} />
                  </div>
                </CardHeader>
                <CardContent>
                  <CardTitle className="text-sm font-semibold mb-1">{service.name}</CardTitle>
                  <p className="text-xs text-muted-foreground mb-3">{service.description}</p>
                  <Badge 
                    variant="outline" 
                    className={cn("text-xs", config.color, config.bgColor, "border-transparent")}
                  >
                    {config.label}
                  </Badge>
                </CardContent>
              </Card>
            )
          })}
      </div>

      {/* Legend */}
      <div className="flex items-center justify-center gap-6 py-2">
        {Object.entries(statusConfig).filter(([key]) => key !== 'checking').map(([key, config]) => {
          const Icon = config.icon
          return (
            <div key={key} className="flex items-center gap-1.5">
              <Icon className={cn("h-3.5 w-3.5", config.color)} />
              <span className="text-xs font-medium text-muted-foreground">{config.label}</span>
            </div>
          )
        })}
      </div>

      {/* Result Modal */}
      <Dialog open={showResultModal} onOpenChange={setShowResultModal}>
        <DialogContent className="sm:max-w-md">
          <DialogHeader>
            <DialogTitle className="flex items-center gap-2">
              <CheckCircle2 className="h-6 w-6 text-green-600" />
              Health Check Complete
            </DialogTitle>
            <DialogDescription>
              All services have been checked successfully.
            </DialogDescription>
          </DialogHeader>
          
          <div className="py-6">
            <div className="grid grid-cols-3 gap-4 text-center">
              <div className="p-4 rounded-lg bg-green-100 dark:bg-green-900/30">
                <div className="text-4xl font-bold text-green-600">{operationalCount}</div>
                <div className="text-sm text-green-700 dark:text-green-400 font-medium mt-1">Operational</div>
              </div>
              <div className="p-4 rounded-lg bg-yellow-100 dark:bg-yellow-900/30">
                <div className="text-4xl font-bold text-yellow-600">{degradedCount}</div>
                <div className="text-sm text-yellow-700 dark:text-yellow-400 font-medium mt-1">Degraded</div>
              </div>
              <div className="p-4 rounded-lg bg-red-100 dark:bg-red-900/30">
                <div className="text-4xl font-bold text-red-600">{outageCount}</div>
                <div className="text-sm text-red-700 dark:text-red-400 font-medium mt-1">Outage</div>
              </div>
            </div>
            
            {allOperational && (
              <div className="mt-6 p-4 rounded-lg bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 text-center">
                <p className="text-green-700 dark:text-green-400 font-medium">
                  🎉 All systems are running smoothly!
                </p>
              </div>
            )}
          </div>

          <DialogFooter>
            <Button onClick={() => setShowResultModal(false)} className="w-full">
              Close
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </div>
  )
}
