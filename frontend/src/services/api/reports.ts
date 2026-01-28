/**
 * Type-safe API client for Report endpoints
 * Provides strongly-typed methods for all report-related API calls
 */

import apiClient from '@/lib/api'
import { logger } from '@/lib/logger'

export const reportApi = {
    /**
     * Download ITGlue License Consumption Report (Company-Wide)
     * GET /api/reports/itglue/license-consumption
     * 
     * @param month - Optional month (1-12), defaults to current month
     * @param year - Optional year (YYYY), defaults to current year
     * @param emailTo - Optional email recipient
     * @param emailFrom - Optional email sender
     * @returns Promise that triggers file download or sends email
     */
    async downloadLicenseConsumptionReport(month?: number, year?: number, emailTo?: string, emailFrom?: string): Promise<void> {
        try {
            const params = new URLSearchParams()
            if (month) params.append('month', month.toString())
            if (year) params.append('year', year.toString())
            if (emailTo) params.append('email_to', emailTo)
            if (emailFrom) params.append('email_from', emailFrom)

            const url = `/reports/itglue/license-consumption${params.toString() ? `?${params.toString()}` : ''}`

            if (emailTo && emailFrom) {
                await apiClient.get(url)
                return
            }

            const response = await apiClient.get(url, {
                responseType: 'blob',
            })

            const blob = new Blob([response.data], {
                type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            })

            const monthName = month
                ? new Date(year || new Date().getFullYear(), month - 1).toLocaleString('default', { month: 'long' })
                : new Date().toLocaleString('default', { month: 'long' })
            const yearValue = year || new Date().getFullYear()
            const filename = `license-consumption-${monthName}-${yearValue}.xlsx`

            const downloadUrl = window.URL.createObjectURL(blob)
            const link = document.createElement('a')
            link.href = downloadUrl
            link.setAttribute('download', filename)
            document.body.appendChild(link)
            link.click()

            link.remove()
            window.URL.revokeObjectURL(downloadUrl)
        } catch (error) {
            logger.error('Failed to download license consumption report', error)
            throw error
        }
    },

    /**
     * Download LibreNMS Device Report (Company-Wide or Group)
     * GET /api/reports/librenms/devices
     * 
     * @param groupId - Optional group ID
     * @param emailTo - Optional email recipient
     * @param emailFrom - Optional email sender
     * @returns Promise that triggers file download or sends email
     */
    async downloadLibreNMSDeviceReport(groupId?: string, emailTo?: string, emailFrom?: string): Promise<void> {
        try {
            const params = new URLSearchParams()
            if (groupId) params.append('group_id', groupId)
            if (emailTo) params.append('email_to', emailTo)
            if (emailFrom) params.append('email_from', emailFrom)

            const url = `/reports/librenms/devices${params.toString() ? `?${params.toString()}` : ''}`

            const response = await apiClient.get(url, {
                responseType: 'blob',
            })

            const blob = new Blob([response.data], {
                type: 'application/pdf',
            })

            const filename = `librenms-devices-${new Date().toISOString().split('T')[0]}.pdf`

            const downloadUrl = window.URL.createObjectURL(blob)
            const link = document.createElement('a')
            link.href = downloadUrl
            link.setAttribute('download', filename)
            document.body.appendChild(link)
            link.click()

            link.remove()
            window.URL.revokeObjectURL(downloadUrl)
        } catch (error) {
            logger.error('Failed to download LibreNMS device report', error)
            throw error
        }
    },
}
