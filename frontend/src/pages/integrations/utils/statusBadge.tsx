import React from 'react'
import { CheckCircle2, AlertCircle, XCircle } from 'lucide-react'

export const getStatusIcon = (status: string, syncStatus: string): React.ReactNode => {
  if (syncStatus === 'success') {
    return <CheckCircle2 className="h-5 w-5 text-green-500" />
  }
  if (syncStatus === 'failed') {
    return <XCircle className="h-5 w-5 text-red-500" />
  }
  if (status === 'error') {
    return <XCircle className="h-5 w-5 text-red-500" />
  }
  return <AlertCircle className="h-5 w-5 text-yellow-500" />
}

export const getStatusText = (status: string, syncStatus: string): { text: string, color: string } => {
  if (syncStatus === 'failed') {
    return { text: 'Sync Failed', color: 'text-red-600 bg-red-50' }
  }

  if (status === 'error') {
    return { text: 'Error', color: 'text-red-600 bg-red-50' }
  }
  if (status === 'active') {
    return { text: 'Active', color: 'text-green-600 bg-green-50' }
  }
  if (status === 'disconnected') {
    return { text: 'Disconnected', color: 'text-gray-600 bg-gray-50' }
  }
  if (status === 'importing_historical') {
    return { text: 'Importing', color: 'text-blue-600 bg-blue-50' }
  }
  return { text: 'Unknown', color: 'text-gray-600 bg-gray-50' }
}


