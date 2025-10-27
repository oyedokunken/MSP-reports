import React from 'react'
import { CheckCircle2, AlertCircle, XCircle } from 'lucide-react'

export const getStatusIcon = (status: string, syncStatus: string): React.ReactNode => {
  if (status === 'error' || syncStatus === 'failed') {
    return <XCircle className="h-5 w-5 text-red-500" />
  }
  if (syncStatus === 'success') {
    return <CheckCircle2 className="h-5 w-5 text-green-500" />
  }
  return <AlertCircle className="h-5 w-5 text-yellow-500" />
}

export const getStatusText = (status: string, syncStatus: string): { text: string, color: string } => {
  if (status === 'error' || syncStatus === 'failed') {
    return { text: 'Error', color: 'text-red-600 bg-red-50' }
  }
  if (syncStatus === 'success') {
    return { text: 'Active', color: 'text-green-600 bg-green-50' }
  }
  return { text: 'Warning', color: 'text-yellow-600 bg-yellow-50' }
}


