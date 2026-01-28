import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card'
import { Skeleton } from '@/components/ui/skeleton'
import { FileText, TrendingUp } from 'lucide-react'
import * as echarts from 'echarts'
import { useEffect, useRef } from 'react'

interface ReportsGeneratedChartProps {
  isLoading: boolean
  data: {
    date: string
    count: number
  }[]
}

export function ReportsGeneratedChart({ isLoading, data }: ReportsGeneratedChartProps) {
  const chartRef = useRef<HTMLDivElement>(null)
  const chartInstanceRef = useRef<echarts.ECharts | null>(null)

  useEffect(() => {
    if (!chartRef.current || isLoading || data.length === 0) return

    if (!chartInstanceRef.current) {
      chartInstanceRef.current = echarts.init(chartRef.current)
    }

    const chart = chartInstanceRef.current

    const dates = data.map(item => item.date)
    const counts = data.map(item => item.count)

    const isMobile = window.innerWidth < 640

    const option: echarts.EChartsOption = {
      tooltip: {
        trigger: 'axis',
        backgroundColor: 'rgba(0, 0, 0, 0.8)',
        borderColor: 'rgba(255, 255, 255, 0.1)',
        textStyle: {
          color: '#fff',
        },
        // eslint-disable-next-line @typescript-eslint/no-explicit-any
        formatter: (params: any) => {
          const param = Array.isArray(params) ? params[0] : params
          return `
            <div style="padding: 4px 8px;">
              <div style="font-weight: 600; margin-bottom: 4px;">${param.name}</div>
              <div style="display: flex; align-items: center; gap: 8px;">
                <span style="display: inline-block; width: 10px; height: 10px; border-radius: 50%; background: ${param.color};"></span>
                <span>${param.value} reports generated</span>
              </div>
            </div>
          `
        },
      },
      grid: {
        left: isMobile ? '5%' : '3%',
        right: isMobile ? '5%' : '4%',
        bottom: isMobile ? '5%' : '3%',
        top: isMobile ? '5%' : '10%',
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
          interval: isMobile ? 'auto' : 0,
          rotate: isMobile ? 45 : 0,
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
          name: 'Reports Generated',
          type: 'line',
          smooth: true,
          symbol: 'circle',
          symbolSize: isMobile ? 4 : 8,
          data: counts,
          lineStyle: {
            width: isMobile ? 2 : 3,
            color: new echarts.graphic.LinearGradient(0, 0, 1, 0, [
              { offset: 0, color: '#3b82f6' },
              { offset: 1, color: '#8b5cf6' },
            ]),
          },
          itemStyle: {
            color: '#3b82f6',
            borderWidth: isMobile ? 1 : 2,
            borderColor: '#fff',
          },
          areaStyle: {
            color: new echarts.graphic.LinearGradient(0, 0, 0, 1, [
              { offset: 0, color: 'rgba(59, 130, 246, 0.3)' },
              { offset: 1, color: 'rgba(59, 130, 246, 0.05)' },
            ]),
          },
          emphasis: {
            focus: 'series',
            itemStyle: {
              color: '#3b82f6',
              borderWidth: 3,
              borderColor: '#fff',
              shadowBlur: 10,
              shadowColor: 'rgba(59, 130, 246, 0.5)',
            },
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
      <Card className="h-full border-border/50">
        <CardHeader>
          <div className="flex items-center justify-between">
            <div>
              <Skeleton className="h-6 w-48 mb-2" />
              <Skeleton className="h-4 w-64" />
            </div>
            <Skeleton className="h-10 w-10 rounded-lg" />
          </div>
        </CardHeader>
        <CardContent>
          <Skeleton className="h-[400px] w-full" />
        </CardContent>
      </Card>
    )
  }

  const totalReports = data.reduce((sum, item) => sum + item.count, 0)
  const avgPerMonth = data.length > 0 ? (totalReports / data.length).toFixed(1) : '0'

  return (
    <Card className="h-full border-border/50 flex flex-col">
      <CardHeader className="pb-3 sm:pb-6">
        <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 sm:gap-0">
          <div className="flex-1">
            <CardTitle className="text-base sm:text-lg font-medium flex items-center gap-2">
              <FileText className="h-4 w-4 sm:h-5 sm:w-5 text-primary" />
              <span className="text-sm sm:text-base">Reports Generated (6 Months)</span>
            </CardTitle>
            <CardDescription className="text-xs sm:text-sm mt-1">
              Track report generation trends over the last 6 months
            </CardDescription>
          </div>
          <div className="flex items-center gap-4 sm:text-right">
            <div>
              <div className="text-xl sm:text-2xl font-bold text-foreground">{totalReports}</div>
              <div className="text-xs text-muted-foreground flex items-center gap-1">
                <TrendingUp className="h-3 w-3" />
                {avgPerMonth} avg/month
              </div>
            </div>
          </div>
        </div>
      </CardHeader>
      <CardContent className="flex-1 min-h-0 px-2 sm:px-6">
        {data.length === 0 ? (
          <div className="flex flex-col items-center justify-center h-full text-center">
            <FileText className="h-10 w-10 sm:h-12 sm:w-12 text-muted-foreground/50 mb-4" />
            <p className="text-sm font-medium text-foreground">No reports generated yet</p>
            <p className="text-xs text-muted-foreground mt-1">
              Reports will appear here once they are generated
            </p>
          </div>
        ) : (
          <div ref={chartRef} className="w-full h-full min-h-[250px] sm:min-h-[400px]" />
        )}
      </CardContent>
    </Card>
  )
}
