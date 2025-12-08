/**
 * License API types
 * Matches backend LicenseController response structure
 */

import type { ApiSuccessResponse } from './common'

export interface License {
    license_id: number
    company_id: number
    license_type: string
    product_sku: string
    status: 'active' | 'expired' | 'suspended'
    expiry_date: string | null
    quantity: number | null
    metadata: {
        description?: string
        includes?: string[]
        deployment_type?: string
        customer?: string
        max_retention_months?: number | 'unlimited'
        [key: string]: unknown
    }
    created_at: string
    updated_at: string
}

export interface UserLicenseStatus {
    licensed: boolean
    current_users: number
    max_users: number
    remaining: number
}

export interface LicenseData {
    active_licenses: License[]
    expiring_soon: License[]
    user_license_status: UserLicenseStatus
    license_enforcement_enabled: boolean
}

export interface LicenseUsage {
    integrations: {
        active: number
        licensed: number
    }
    users: UserLicenseStatus
    retention: {
        max_months: number | 'unlimited'
        licensed: boolean
    }
    license_enforcement_enabled: boolean
}

export interface LicenseCheck {
    allowed: boolean
    reason?: string
}

// API Response Types
export type LicenseListResponse = ApiSuccessResponse<LicenseData>
export type LicenseUsageResponse = ApiSuccessResponse<LicenseUsage>
export type IntegrationLicenseCheckResponse = ApiSuccessResponse<LicenseCheck>
export type RetentionLicenseCheckResponse = ApiSuccessResponse<LicenseCheck>

export interface UserLicenseCheckResponse {
    status: 'success'
    data: {
        can_create: LicenseCheck
        license_status: UserLicenseStatus
    }
}
