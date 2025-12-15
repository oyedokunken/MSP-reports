export const formatDate = (dateString: string | null): string => {
  if (!dateString) return 'Never'
  return new Date(dateString).toLocaleString('en-US', {
    year: 'numeric',
    month: 'short',
    day: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
    timeZone: 'Australia/Darwin',
  })
}

export const formatFrequency = (frequency: string): string => {
  return frequency.charAt(0).toUpperCase() + frequency.slice(1)
}

export const formatReportType = (type: string): string => {
  return type.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase())
}
