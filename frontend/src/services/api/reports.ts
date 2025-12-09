/**
 * Type-safe API client for Report endpoints
 * Provides strongly-typed methods for all report-related API calls
 */

import apiClient from '@/lib/api'

export const reportApi = {
    /**
     * Download ITGlue License Consumption Report (Company-Wide)
     * GET /api/reports/itglue/license-consumption
     * 
     * @param month - Optional month (1-12), defaults to current month
     * @param year - Optional year (YYYY), defaults to current year
     * @returns Promise that triggers file download
     */
    async downloadLicenseConsumptionReport(month?: number, year?: number): Promise<void> {
        try {
            const params = new URLSearchParams()
            if (month) params.append('month', month.toString())
            if (year) params.append('year', year.toString())

            const url = `/reports/itglue/license-consumption${params.toString() ? `?${params.toString()}` : ''}`

            const response = await apiClient.get(url, {
                responseType: 'blob', // Important: Handle binary data
            })

            // Create blob from response
            const blob = new Blob([response.data], {
                type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            })

            // Generate filename with date
            const monthName = month 
                ? new Date(year || new Date().getFullYear(), month - 1).toLocaleString('default', { month: 'long' })
                : new Date().toLocaleString('default', { month: 'long' })
            const yearValue = year || new Date().getFullYear()
            const filename = `license-consumption-${monthName}-${yearValue}.xlsx`

            // Create download link and trigger download
            const downloadUrl = window.URL.createObjectURL(blob)
            const link = document.createElement('a')
            link.href = downloadUrl
            link.setAttribute('download', filename)
            document.body.appendChild(link)
            link.click()
            
            // Cleanup
            link.remove()
            window.URL.revokeObjectURL(downloadUrl)
        } catch (error) {
            console.error('Failed to download license consumption report:', error)
            throw error
        }
    },
}
