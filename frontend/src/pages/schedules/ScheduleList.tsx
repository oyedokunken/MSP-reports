import { useState } from 'react'
import FloatingLayout from '@/components/layout/FloatingLayout'
import { Calendar, Clock, Download, Edit2, Trash2, CheckCircle2, XCircle } from 'lucide-react'

export default function ScheduleList() {
  const [schedules, setSchedules] = useState([
    {
      id: 1,
      title: 'Monthly Executive Summary',
      customer: 'TechCorp Solutions',
      frequency: 'Monthly',
      detail: 'Day 1',
      time: '08:00',
      timezone: 'America/New_York',
      next: 'In 22 hours',
      enabled: true,
    },
    {
      id: 2,
      title: 'Backup Status Report',
      customer: 'TechCorp Solutions',
      frequency: 'Weekly',
      detail: 'Every Mon',
      time: '09:00',
      timezone: 'America/New_York',
      next: 'In 10 hours',
      enabled: true,
    },
  ])

  const recentRuns = [
    { id: 101, status: 'completed' as const, date: 'Sep 15, 2025, 05:18 AM' },
    { id: 102, status: 'completed' as const, date: 'Oct 8, 2025, 05:18 AM' },
    {
      id: 103,
      status: 'failed' as const,
      date: 'Oct 1, 2025, 05:18 AM',
      error: 'Failed to fetch backup data from Veeam API',
    },
  ]

  const toggleSchedule = (id: number) => {
    setSchedules((prev) => prev.map((s) => (s.id === id ? { ...s, enabled: !s.enabled } : s)))
  }

  return (
    <FloatingLayout>
      <div className="space-y-6">
        {/* Header */}
        <div className="flex items-center justify-between">
          <div>
            <h1 className="text-3xl font-bold text-foreground">Schedules</h1>
            <p className="mt-2 text-muted-foreground">Manage automated report generation and delivery</p>
          </div>
          <button className="flex items-center text-white gap-2 rounded-md bg-primary px-4 py-2 text-sm font-medium text-primary-foreground hover:bg-primary/90">
            + Create Schedule
          </button>
        </div>

        {/* Content */}
        <div className="grid gap-4 lg:grid-cols-3">
          {/* Schedule Cards */}
          <div className="space-y-4 lg:col-span-2">
            {schedules.map((s) => (
              <div key={s.id} className="rounded-lg border border-gray-200 dark:border-0 bg-white dark:bg-[hsl(var(--auth-card))] p-5 shadow-sm">
                <div className="flex items-start justify-between">
                  <div>
                    <h3 className="text-lg font-semibold text-foreground flex items-center gap-3">
                      {s.title}
                      <button
                        onClick={() => toggleSchedule(s.id)}
                        className={`relative inline-flex h-6 w-11 items-center rounded-full transition-colors ${
                          s.enabled ? 'bg-blue-600' : 'bg-gray-300 dark:bg-gray-700'
                        }`}
                        aria-label={s.enabled ? 'Disable schedule' : 'Enable schedule'}
                      >
                        <span
                          className={`inline-block h-5 w-5 transform rounded-full bg-white transition ${
                            s.enabled ? 'translate-x-5' : 'translate-x-1'
                          }`}
                        />
                      </button>
                    </h3>
                    <p className="mt-1 text-sm text-muted-foreground">{s.customer}</p>
                  </div>
                </div>

                <div className="mt-4 grid gap-4 sm:grid-cols-3">
                  <div className="flex items-center gap-2">
                    <Calendar className="h-4 w-4 text-muted-foreground" />
                    <div>
                      <p className="text-xs text-muted-foreground">{s.frequency}</p>
                      <p className="text-sm font-medium text-foreground">{s.detail}</p>
                    </div>
                  </div>

                  <div className="flex items-center gap-2">
                    <Clock className="h-4 w-4 text-muted-foreground" />
                    <div>
                      <p className="text-xs text-muted-foreground">Time</p>
                      <p className="text-sm font-medium text-foreground">{s.time}</p>
                    </div>
                  </div>

                  <div className="flex items-center gap-2">
                    <Calendar className="h-4 w-4 text-muted-foreground" />
                    <div>
                      <p className="text-xs text-muted-foreground">Timezone</p>
                      <p className="text-sm font-medium text-foreground">{s.timezone}</p>
                    </div>
                  </div>
                </div>

                <div className="mt-4 rounded-lg border border-border bg-muted/30 p-3">
                  <p className="text-xs text-muted-foreground">Next run</p>
                  <p className="text-sm font-medium text-foreground">{s.next}</p>
                </div>

                <div className="mt-4 flex items-center gap-2">
                  <button className="inline-flex items-center gap-2 rounded-md border border-border bg-background px-3 py-1.5 text-sm hover:bg-muted">
                    <Edit2 className="h-4 w-4" /> Edit
                  </button>
                  <button className="inline-flex items-center gap-2 rounded-md border border-red-200 text-red-600 dark:border-red-900/40 dark:text-red-400 bg-background px-3 py-1.5 text-sm hover:bg-red-50 dark:hover:bg-red-900/10">
                    <Trash2 className="h-4 w-4" /> Delete
                  </button>
                </div>
              </div>
            ))}
          </div>

          {/* Recent Runs */}
          <div className="rounded-lg border border-gray-200 dark:border-0 bg-white dark:bg-[hsl(var(--auth-card))] p-5 shadow-sm">
            <h3 className="text-base font-semibold text-foreground">Recent Runs</h3>
            <div className="mt-4 space-y-3">
              {recentRuns.map((r) => (
                <div key={r.id} className="rounded-lg border border-border p-3">
                  <div className="flex items-center justify-between">
                    <span
                      className={`inline-flex items-center gap-2 rounded-full px-2 py-0.5 text-xs font-medium ${
                        r.status === 'completed'
                          ? 'bg-green-50 text-green-700'
                          : r.status === 'failed'
                          ? 'bg-red-50 text-red-700'
                          : 'bg-yellow-50 text-yellow-700'
                      }`}
                    >
                      {r.status === 'completed' ? (
                        <CheckCircle2 className="h-3.5 w-3.5" />
                      ) : (
                        <XCircle className="h-3.5 w-3.5" />
                      )}
                      {r.status.charAt(0).toUpperCase() + r.status.slice(1)}
                    </span>
                    <button className="rounded-md border border-border bg-background p-2 hover:bg-muted" title="Download report">
                      <Download className="h-4 w-4" />
                    </button>
                  </div>
                  <p className="mt-2 text-sm text-foreground">{r.date}</p>
                  {r.status === 'failed' && r.error && (
                    <p className="mt-1 text-sm text-red-600 dark:text-red-400">{r.error}</p>
                  )}
                </div>
              ))}
            </div>
          </div>
        </div>
      </div>
    </FloatingLayout>
  )
}
