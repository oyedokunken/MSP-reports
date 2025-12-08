/**
 * Settings API types
 * Matches backend settings endpoints
 */

import type { ApiSuccessResponse } from './common'

export interface CompanySettings {
    company_id: number
    company_name: string
    settings: {
        branding?: {
            logo_url?: string
            primary_color?: string
            secondary_color?: string
        }
        integrations?: {
            source_of_truth_integration_id?: number
            auto_sync_enabled?: boolean
            sync_frequency_minutes?: number
        }
        reports?: {
            default_delivery_method?: string
            retention_days?: number
        }
        licensing?: {
            license_enforcement?: boolean
        }
        [key: string]: unknown
    }
}

export type SettingsResponse = ApiSuccessResponse<CompanySettings>

export interface UpdateSettingsRequest {
    settings: Partial<CompanySettings['settings']>
}

export type UpdateSettingsResponse = ApiSuccessResponse<CompanySettings>
