import React from 'react'
import { CheckCircle2, XCircle, Clock } from 'lucide-react'
import type { ReportStatus } from './types'

export const getStatusIcon = (status: ReportStatus): React.ReactNode => {
  switch (status) {
    case 'completed':
      return <CheckCircle2 className="h-4 w-4 text-green-500" />
    case 'failed':
      return <XCircle className="h-4 w-4 text-red-500" />
    case 'pending':
      return <Clock className="h-4 w-4 text-yellow-500" />
    default:
      return null
  }
}

export const getStatusBadge = (status: ReportStatus): string => {
  const styles = {
    completed: 'bg-green-50 text-green-700',
    failed: 'bg-red-50 text-red-700',
    pending: 'bg-yellow-50 text-yellow-700',
  }
  return styles[status]
}

export const FREQUENCIES = [
  { value: 'daily', label: 'Daily' },
  { value: 'weekly', label: 'Weekly' },
  { value: 'monthly', label: 'Monthly' },
  { value: 'yearly', label: 'Yearly' },
  { value: 'on-demand', label: 'On-Demand Only' },
]


