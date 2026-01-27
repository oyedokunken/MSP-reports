import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { TrendingUp } from 'lucide-react'
import * as echarts from 'echarts'
import { useEffect, useRef, useMemo } from 'react'

interface ActivityTrendChartProps {
  data?: Array<{
    date: string
    syncs: number
    reports: number
    jobs: number
  }>
}

export function ActivityTrendChart({ data }: ActivityTrendChartProps) {
  const chartRef = useRef<HTMLDivElement>(null)
  const chartInstanceRef = useRef<echarts.ECharts | null>(null)
  const chartData = useMemo(() => data || [], [data])

  useEffect(() => {
    if (!chartRef.current || chartData.length === 0) return

    // Initialize chart
    if (!chartInstanceRef.current) {
      chartInstanceRef.current = echarts.init(chartRef.current)
    }

    const chart = chartInstanceRef.current

    // Prepare data
    const dates = chartData.map(item => item.date)
    const syncsData = chartData.map(item => item.syncs)
    const reportsData = chartData.map(item => item.reports)
    const jobsData = chartData.map(item => item.jobs)

    // Chart options
    const option: echarts.EChartsOption = {
      tooltip: {
        trigger: 'axis',
        backgroundColor: 'rgba(0, 0, 0, 0.8)',
        borderColor: 'rgba(255, 255, 255, 0.1)',
        textStyle: {
          color: '#fff',
        },
        axisPointer: {
          type: 'cross',
          label: {
            backgroundColor: '#6a7985'
          }
        }
      },
      legend: {
        data: ['Integration Syncs', 'Reports Generated', 'Queue Jobs'],
        textStyle: {
          color: 'rgba(148, 163, 184, 0.8)',
        },
        top: 0,
      },
      grid: {
        left: '3%',
        right: '4%',
        bottom: '3%',
        top: '15%',
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
          fontSize: 11,
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
          fontSize: 11,
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
          name: 'Integration Syncs',
          type: 'line',
          smooth: true,
          symbol: 'circle',
          symbolSize: 6,
          data: syncsData,
          lineStyle: {
            width: 2,
            color: '#3b82f6',
          },
          itemStyle: {
            color: '#3b82f6',
            borderWidth: 2,
            borderColor: '#fff',
          },
          areaStyle: {
            color: new echarts.graphic.LinearGradient(0, 0, 0, 1, [
              { offset: 0, color: 'rgba(59, 130, 246, 0.3)' },
              { offset: 1, color: 'rgba(59, 130, 246, 0.05)' },
            ]),
          },
        },
        {
          name: 'Reports Generated',
          type: 'line',
          smooth: true,
          symbol: 'circle',
          symbolSize: 6,
          data: reportsData,
          lineStyle: {
            width: 2,
            color: '#22c55e',
          },
          itemStyle: {
            color: '#22c55e',
            borderWidth: 2,
            borderColor: '#fff',
          },
          areaStyle: {
            color: new echarts.graphic.LinearGradient(0, 0, 0, 1, [
              { offset: 0, color: 'rgba(34, 197, 94, 0.3)' },
              { offset: 1, color: 'rgba(34, 197, 94, 0.05)' },
            ]),
          },
        },
        {
          name: 'Queue Jobs',
          type: 'line',
          smooth: true,
          symbol: 'circle',
          symbolSize: 6,
          data: jobsData,
          lineStyle: {
            width: 2,
            color: '#f97316',
          },
          itemStyle: {
            color: '#f97316',
            borderWidth: 2,
            borderColor: '#fff',
          },
          areaStyle: {
            color: new echarts.graphic.LinearGradient(0, 0, 0, 1, [
              { offset: 0, color: 'rgba(249, 115, 22, 0.3)' },
              { offset: 1, color: 'rgba(249, 115, 22, 0.05)' },
            ]),
          },
        },
      ],
    }

    chart.setOption(option)

    // Handle resize
    const handleResize = () => {
      chart.resize()
    }
    window.addEventListener('resize', handleResize)

    return () => {
      window.removeEventListener('resize', handleResize)
    }
  }, [chartData])

  // Cleanup on unmount
  useEffect(() => {
    return () => {
      if (chartInstanceRef.current) {
        chartInstanceRef.current.dispose()
        chartInstanceRef.current = null
      }
    }
  }, [])

  return (
    <Card className="col-span-full">
      <CardHeader className="pb-3 sm:pb-6">
        <CardTitle className="flex items-center gap-2 text-lg sm:text-xl">
          <TrendingUp className="h-4 w-4 sm:h-5 sm:w-5" />
          Activity Trends
        </CardTitle>
        <CardDescription className="text-xs sm:text-sm">System activity over the last 7 days</CardDescription>
      </CardHeader>
      <CardContent className="px-2 sm:px-6">
        {chartData.length === 0 ? (
          <div className="flex flex-col items-center justify-center h-[300px] text-center">
            <TrendingUp className="h-10 w-10 sm:h-12 sm:w-12 text-muted-foreground/50 mb-4" />
            <p className="text-sm font-medium text-foreground">No activity data yet</p>
            <p className="text-xs text-muted-foreground mt-1">
              Activity trends will appear here once syncs and reports are generated
            </p>
          </div>
        ) : (
          <div ref={chartRef} className="w-full h-[300px]" />
        )}
      </CardContent>
    </Card>
  )
}
