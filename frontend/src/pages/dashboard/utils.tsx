import { CheckCircle2, AlertCircle, XCircle } from 'lucide-react'
import React from 'react'

export const getIntegrationStatusBadge = (status: string, syncStatus: string): string => {
  if (status === 'error' || syncStatus === 'failed') return 'bg-red-50 text-red-700'
  if (syncStatus === 'success') return 'bg-green-50 text-green-700'
  return 'bg-yellow-50 text-yellow-700'
}

export const getIntegrationStatusText = (status: string, syncStatus: string): string => {
  if (status === 'error' || syncStatus === 'failed') return 'Error'
  if (syncStatus === 'success') return 'Active'
  return 'Warning'
}

export const getIntegrationStatusIcon = (status: string, syncStatus: string): React.ReactNode => {
  if (status === 'error' || syncStatus === 'failed') return <XCircle className="h-4 w-4 text-red-500" />
  if (syncStatus === 'success') return <CheckCircle2 className="h-4 w-4 text-green-500" />
  return <AlertCircle className="h-4 w-4 text-yellow-500" />
}


