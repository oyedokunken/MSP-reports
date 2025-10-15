import type { Stat } from '../types'

type StatsProps = {
  stats: Stat[]
}

export function CustomersStats ({ stats }: StatsProps) {
  return (
    <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
      {stats.map((stat) => (
        <div key={stat.label} className="rounded-lg border border-gray-200 dark:border-0 bg-white dark:bg-[hsl(var(--auth-card))] p-4 shadow-sm">
          <p className="text-sm text-muted-foreground">{stat.label}</p>
          <p className={`mt-2 text-2xl font-bold ${stat.color}`}>{stat.value}</p>
        </div>
      ))}
    </div>
  )
}


