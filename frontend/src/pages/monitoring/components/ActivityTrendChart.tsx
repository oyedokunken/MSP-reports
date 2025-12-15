import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { TrendingUp } from 'lucide-react'
import { Line, Area, XAxis, YAxis, CartesianGrid, Tooltip, Legend, ResponsiveContainer, ComposedChart } from 'recharts'

interface ActivityTrendChartProps {
  data?: Array<{
    date: string
    syncs: number
    reports: number
    jobs: number
  }>
}

// Generate sample data for the last 7 days
const generateSampleData = () => {
  const data = []
  const today = new Date()
  
  for (let i = 6; i >= 0; i--) {
    const date = new Date(today)
    date.setDate(date.getDate() - i)
    
    data.push({
      date: date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' }),
      syncs: Math.floor(Math.random() * 30) + 20,
      reports: Math.floor(Math.random() * 50) + 30,
      jobs: Math.floor(Math.random() * 100) + 50,
    })
  }
  
  return data
}

export function ActivityTrendChart({ data }: ActivityTrendChartProps) {
  const chartData = data || generateSampleData()
  const isMobile = window.innerWidth < 640

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
        <div className="w-full overflow-x-auto">
          <ResponsiveContainer width="100%" height={isMobile ? 200 : 300} minWidth={280}>
          <ComposedChart 
            data={chartData} 
            margin={isMobile ? { top: 5, right: 5, left: 5, bottom: 5 } : { top: 5, right: 20, left: 10, bottom: 5 }}
            style={{ outline: 'none' }}
          >
            <defs>
              <linearGradient id="colorSyncs" x1="0" y1="0" x2="0" y2="1">
                <stop offset="5%" stopColor="hsl(var(--primary))" stopOpacity={0.3}/>
                <stop offset="95%" stopColor="hsl(var(--primary))" stopOpacity={0}/>
              </linearGradient>
              <linearGradient id="colorReports" x1="0" y1="0" x2="0" y2="1">
                <stop offset="5%" stopColor="hsl(142.1 76.2% 36.3%)" stopOpacity={0.3}/>
                <stop offset="95%" stopColor="hsl(142.1 76.2% 36.3%)" stopOpacity={0}/>
              </linearGradient>
              <linearGradient id="colorJobs" x1="0" y1="0" x2="0" y2="1">
                <stop offset="5%" stopColor="hsl(24.6 95% 53.1%)" stopOpacity={0.3}/>
                <stop offset="95%" stopColor="hsl(24.6 95% 53.1%)" stopOpacity={0}/>
              </linearGradient>
            </defs>
            <CartesianGrid strokeDasharray="3 3" className="stroke-muted" />
            <XAxis 
              dataKey="date" 
              className="text-xs"
              tick={{ fill: 'hsl(var(--muted-foreground))', fontSize: isMobile ? 10 : 12 }}
            />
            <YAxis 
              className="text-xs"
              tick={{ fill: 'hsl(var(--muted-foreground))', fontSize: isMobile ? 10 : 12 }}
            />
            <Tooltip 
              contentStyle={{
                backgroundColor: 'hsl(var(--background))',
                border: '1px solid hsl(var(--border))',
                borderRadius: '8px',
              }}
              labelStyle={{ color: 'hsl(var(--foreground))' }}
            />
            <Legend 
              wrapperStyle={{ paddingTop: isMobile ? '10px' : '20px', fontSize: isMobile ? '11px' : '14px' }}
              iconType="line"
            />
            <Area 
              type="monotone" 
              dataKey="syncs" 
              fill="url(#colorSyncs)" 
              stroke="none"
            />
            <Area 
              type="monotone" 
              dataKey="reports" 
              fill="url(#colorReports)" 
              stroke="none"
            />
            <Area 
              type="monotone" 
              dataKey="jobs" 
              fill="url(#colorJobs)" 
              stroke="none"
            />
            <Line 
              type="monotone" 
              dataKey="syncs" 
              stroke="hsl(var(--primary))" 
              strokeWidth={isMobile ? 1.5 : 2}
              name="Integration Syncs"
              dot={isMobile ? false : { fill: 'hsl(var(--primary))' }}
            />
            <Line 
              type="monotone" 
              dataKey="reports" 
              stroke="hsl(142.1 76.2% 36.3%)" 
              strokeWidth={isMobile ? 1.5 : 2}
              name="Reports Generated"
              dot={isMobile ? false : { fill: 'hsl(142.1 76.2% 36.3%)' }}
            />
            <Line 
              type="monotone" 
              dataKey="jobs" 
              stroke="hsl(24.6 95% 53.1%)" 
              strokeWidth={isMobile ? 1.5 : 2}
              name="Queue Jobs"
              dot={isMobile ? false : { fill: 'hsl(24.6 95% 53.1%)' }}
            />
          </ComposedChart>
        </ResponsiveContainer>
        </div>
      </CardContent>
    </Card>
  )
}
