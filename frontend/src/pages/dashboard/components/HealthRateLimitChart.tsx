import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card'
import { Skeleton } from '@/components/ui/skeleton'
import { Activity, ShieldAlert, Zap } from 'lucide-react'
import * as echarts from 'echarts'
import { useEffect, useRef } from 'react'
import type { ActivityTrendData, SystemStatus } from '@/types/api/monitoring'

interface HealthRateLimitChartProps {
  isLoading: boolean
  data: ActivityTrendData[]
  systemStatus: SystemStatus | null
}

export function HealthRateLimitChart({ isLoading, data, systemStatus }: HealthRateLimitChartProps) {
  const chartRef = useRef<HTMLDivElement>(null)
  const chartInstanceRef = useRef<echarts.ECharts | null>(null)

  useEffect(() => {
    if (!chartRef.current || isLoading || data.length === 0) return

    if (!chartInstanceRef.current) {
      chartInstanceRef.current = echarts.init(chartRef.current)
    }

    const chart = chartInstanceRef.current

    const dates = data.map(item => item.date)
    const failedSyncs = data.map(item => item.failed_syncs)
    const failedReports = data.map(item => item.failed_reports)
    const rateLimits = data.map(item => item.rate_limits)

    const isMobile = window.innerWidth < 640

    const option: echarts.EChartsOption = {
      tooltip: {
        trigger: 'axis',
        backgroundColor: 'rgba(0, 0, 0, 0.8)',
        borderColor: 'rgba(255, 255, 255, 0.1)',
        textStyle: {
          color: '#fff',
        },
      },
      legend: {
        data: ['Failed Syncs', 'Failed Reports', 'Rate Limits'],
        bottom: 0,
        textStyle: {
          color: 'rgba(148, 163, 184, 0.8)',
          fontSize: 11,
        },
      },
      grid: {
        left: isMobile ? '5%' : '3%',
        right: isMobile ? '5%' : '4%',
        bottom: '15%',
        top: '10%',
        containLabel: true,
      },
      xAxis: {
        type: 'category',
        data: dates,
        boundaryGap: false,
        axisLine: {
          lineStyle: {
            color: 'rgba(148, 163, 184, 0.3)',
          },
        },
        axisLabel: {
          color: 'rgba(148, 163, 184, 0.8)',
          fontSize: isMobile ? 9 : 11,
        },
        splitLine: {
          show: false,
        },
      },
      yAxis: {
        type: 'value',
        axisLine: {
          show: false,
        },
        axisLabel: {
          color: 'rgba(148, 163, 184, 0.8)',
          fontSize: isMobile ? 9 : 11,
        },
        splitLine: {
          lineStyle: {
            color: 'rgba(148, 163, 184, 0.1)',
            type: 'dashed',
          },
        },
      },
      series: [
        {
          name: 'Failed Syncs',
          type: 'line',
          smooth: true,
          data: failedSyncs,
          itemStyle: { color: '#ef4444' }, // red-500
          areaStyle: {
            color: new echarts.graphic.LinearGradient(0, 0, 0, 1, [
              { offset: 0, color: 'rgba(239, 68, 68, 0.2)' },
              { offset: 1, color: 'rgba(239, 68, 68, 0.05)' },
            ]),
          },
        },
        {
          name: 'Failed Reports',
          type: 'line',
          smooth: true,
          data: failedReports,
          itemStyle: { color: '#f59e0b' }, // amber-500
          areaStyle: {
            color: new echarts.graphic.LinearGradient(0, 0, 0, 1, [
              { offset: 0, color: 'rgba(245, 158, 11, 0.2)' },
              { offset: 1, color: 'rgba(245, 158, 11, 0.05)' },
            ]),
          },
        },
        {
          name: 'Rate Limits',
          type: 'line',
          smooth: true,
          data: rateLimits,
          itemStyle: { color: '#3b82f6' }, // blue-500
          areaStyle: {
            color: new echarts.graphic.LinearGradient(0, 0, 0, 1, [
              { offset: 0, color: 'rgba(59, 130, 246, 0.2)' },
              { offset: 1, color: 'rgba(59, 130, 246, 0.05)' },
            ]),
          },
        },
      ],
    }

    chart.setOption(option)

    const handleResize = () => {
      chart.resize()
    }
    window.addEventListener('resize', handleResize)

    return () => {
      window.removeEventListener('resize', handleResize)
    }
  }, [data, isLoading])

  useEffect(() => {
    return () => {
      if (chartInstanceRef.current) {
        chartInstanceRef.current.dispose()
        chartInstanceRef.current = null
      }
    }
  }, [])

  if (isLoading) {
    return (
      <Card className="h-full border-border/50 flex flex-col">
        <CardHeader className="pb-3">
          <div className="flex flex-col xl:flex-row xl:items-center xl:justify-between gap-3 xl:gap-0">
            <div className="flex-1">
              <div className="flex items-center gap-2">
                <Skeleton className="h-4 w-4 rounded" />
                <Skeleton className="h-5 w-40" />
              </div>
              <Skeleton className="h-3 w-48 mt-2" />
            </div>
            <div className="flex items-center gap-4">
              <div className="space-y-1">
                <Skeleton className="h-6 w-8 ml-auto" />
                <Skeleton className="h-2 w-16" />
              </div>
              <div className="space-y-1 border-l pl-4">
                <Skeleton className="h-6 w-8 ml-auto" />
                <Skeleton className="h-2 w-16" />
              </div>
              <div className="space-y-1 border-l pl-4">
                <Skeleton className="h-6 w-8 ml-auto" />
                <Skeleton className="h-2 w-16" />
              </div>
            </div>
          </div>
        </CardHeader>
        <CardContent className="flex-1 flex items-center justify-center px-6">
          <Skeleton className="h-full w-full min-h-[200px]" />
        </CardContent>
      </Card>
    )
  }

  const total24hRateLimits = systemStatus?.integrations.rate_limit_hits ?? 0
  const total24hSyncFailures = systemStatus?.integrations.failed_syncs ?? 0
  const total24hReportFailures = systemStatus?.reports.last_24_hours.failed ?? 0

  return (
    <Card className="h-full border-border/50 flex flex-col">
      <CardHeader className="pb-3">
        <div className="flex flex-col xl:flex-row xl:items-center xl:justify-between gap-3 xl:gap-0">
          <div className="flex-1">
            <CardTitle className="text-base font-medium flex items-center gap-2">
              <Activity className="h-4 w-4 text-primary" />
              <span>Operation Monitoring</span>
            </CardTitle>
            <CardDescription className="text-xs mt-1">
              7-day trends • {total24hSyncFailures > 0 || total24hReportFailures > 0 || total24hRateLimits > 0 ? (
                <span className="text-destructive font-medium">Issues detected (24h)</span>
              ) : (
                <span className="text-green-500 font-medium">System healthy (24h)</span>
              )}
            </CardDescription>
          </div>
          <div className="flex items-center gap-4">
            <div className="text-right">
              <div className="text-lg font-bold text-foreground flex items-center gap-1 justify-end">
                <ShieldAlert className="h-3 w-3 text-red-500" />
                {total24hSyncFailures}
              </div>
              <div className="text-[10px] text-muted-foreground whitespace-nowrap">Sync Fails (24h)</div>
            </div>
            <div className="text-right border-l pl-4">
              <div className="text-lg font-bold text-foreground flex items-center gap-1 justify-end">
                <ShieldAlert className="h-3 w-3 text-amber-500" />
                {total24hReportFailures}
              </div>
              <div className="text-[10px] text-muted-foreground whitespace-nowrap">Report Fails (24h)</div>
            </div>
            <div className="text-right border-l pl-4">
              <div className="text-lg font-bold text-foreground flex items-center gap-1 justify-end">
                <Zap className="h-3 w-3 text-blue-500" />
                {total24hRateLimits}
              </div>
              <div className="text-[10px] text-muted-foreground whitespace-nowrap">Rate Limits (24h)</div>
            </div>
          </div>
        </div>
      </CardHeader>
      <CardContent className="flex-1 min-h-0 px-2 sm:px-6">
        {data.length === 0 ? (
          <div className="flex flex-col items-center justify-center h-full text-center py-10">
            <Activity className="h-10 w-10 text-muted-foreground/50 mb-2" />
            <p className="text-sm font-medium text-foreground">No health data available</p>
          </div>
        ) : (
          <div ref={chartRef} className="w-full h-full min-h-[200px]" />
        )}
      </CardContent>
    </Card>
  )
}
